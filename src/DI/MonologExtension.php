<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\DI;

use Kdyby\Monolog\CustomChannel;
use Kdyby\Monolog\Handler\FallbackNetteHandler;
use Kdyby\Monolog\Logger as KdybyLogger;
use Kdyby\Monolog\Processor\PriorityProcessor;
use Kdyby\Monolog\Processor\TracyExceptionProcessor;
use Kdyby\Monolog\Processor\TracyUrlProcessor;
use Kdyby\Monolog\Tracy\BlueScreenRenderer;
use Kdyby\Monolog\Tracy\MonologAdapter;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\Helpers as DIHelpers;
use Nette\DI\Statement;
use Nette\PhpGenerator\ClassType as ClassTypeGenerator;
use Nette\PhpGenerator\PhpLiteral;
use Psr\Log\LoggerAwareInterface;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Integrates the Monolog seamlessly into your Nette Framework application.
 */
class MonologExtension extends \Nette\DI\CompilerExtension
{

	use \Kdyby\StrictObjects\Scream;

	const TAG_HANDLER = 'monolog.handler';
	const TAG_PROCESSOR = 'monolog.processor';
	const TAG_PRIORITY = 'monolog.priority';

	/**
	 * @var mixed[]
	 */
	private $defaults = [
		'handlers' => [],
		'processors' => [],
		'name' => 'app',
		'hookToTracy' => TRUE,
		'tracyBaseUrl' => NULL,
		'usePriorityProcessor' => TRUE,
		// 'registerFallback' => TRUE,
		'accessPriority' => ILogger::INFO,
		'channels' => [],
	];

	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		$config['logDir'] = self::resolveLogDir($builder->parameters);
		self::createDirectory($config['logDir']);
		$this->setConfig($config);

		if (!isset($builder->parameters[$this->name]) || (is_array($builder->parameters[$this->name]) && !isset($builder->parameters[$this->name]['name']))) {
			$builder->parameters[$this->name]['name'] = $config['name'];
		}

		if (!isset($builder->parameters['logDir'])) { // BC
			$builder->parameters['logDir'] = $config['logDir'];
		}

		$builder->addDefinition($this->prefix('logger'))
			->setClass(KdybyLogger::class, [$config['name']]);

		// Tracy adapter
		$builder->addDefinition($this->prefix('adapter'))
			->setClass(MonologAdapter::class, [
				'monolog' => $this->prefix('@logger'),
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				'email' => Debugger::$email,
				'accessPriority' => $config['accessPriority'],
			])
			->addTag('logger');

		// The renderer has to be separate, to solve circural service dependencies
		$builder->addDefinition($this->prefix('blueScreenRenderer'))
			->setClass(BlueScreenRenderer::class, [
				'directory' => $config['logDir'],
			])
			->setAutowired(FALSE)
			->addTag('logger');

		if ($config['hookToTracy'] === TRUE && $builder->hasDefinition('tracy.logger')) {
			// TracyExtension initializes the logger from DIC, if definition is changed
			$builder->removeDefinition($existing = 'tracy.logger');
			$builder->addAlias($existing, $this->prefix('adapter'));
		}

		$this->loadHandlers($config);
		$this->loadProcessors($config);
		$this->loadChannels($config);
	}

	protected function loadHandlers(array $config)
	{
		$builder = $this->getContainerBuilder();

		foreach ($config['handlers'] as $handlerName => $implementation) {
			Compiler::loadDefinitions($builder, [
				$serviceName = $this->prefix('handler.' . $handlerName) => $implementation,
			]);

			$builder->getDefinition($serviceName)
				->addTag(self::TAG_HANDLER)
				->addTag(self::TAG_PRIORITY, is_numeric($handlerName) ? $handlerName : 0);
		}
	}

	protected function loadProcessors(array $config)
	{
		$builder = $this->getContainerBuilder();

		if ($config['usePriorityProcessor'] === TRUE) {
			// change channel name to priority if available
			$builder->addDefinition($this->prefix('processor.priorityProcessor'))
				->setClass(PriorityProcessor::class)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 20);
		}

		$builder->addDefinition($this->prefix('processor.tracyException'))
			->setClass(TracyExceptionProcessor::class, [
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
			])
			->addTag(self::TAG_PROCESSOR)
			->addTag(self::TAG_PRIORITY, 100);

		if ($config['tracyBaseUrl'] !== NULL) {
			$builder->addDefinition($this->prefix('processor.tracyBaseUrl'))
				->setClass(TracyUrlProcessor::class, [
					'baseUrl' => $config['tracyBaseUrl'],
					'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				])
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 10);
		}

		foreach ($config['processors'] as $processorName => $implementation) {
			Compiler::loadDefinitions($builder, [
				$serviceName = $this->prefix('processor.' . $processorName) => $implementation,
			]);

			$builder->getDefinition($serviceName)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, is_numeric($processorName) ? $processorName : 0);
		}
	}

	protected function loadChannels(array $config): void
	{
		$builder = $this->getContainerBuilder();

		foreach ($config['channels'] as $channel) {
			$builder->addDefinition($this->prefix('logger.' . $channel))
				->setFactory(CustomChannel::class, [$channel, $this->prefix('@logger')]);
		}
	}

	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$logger = $builder->getDefinition($this->prefix('logger'));

		foreach ($handlers = $this->findByTagSorted(self::TAG_HANDLER) as $serviceName => $meta) {
			$logger->addSetup('pushHandler', ['@' . $serviceName]);
		}

		foreach ($this->findByTagSorted(self::TAG_PROCESSOR) as $serviceName => $meta) {
			$logger->addSetup('pushProcessor', ['@' . $serviceName]);
		}

		$config = $this->getConfig(['registerFallback' => empty($handlers)] + $this->getConfig($this->defaults));

		if ($config['registerFallback']) {
			$logger->addSetup('pushHandler', [
				new Statement(FallbackNetteHandler::class, [
					'appName' => $config['name'],
					'logDir' => $config['logDir'],
				]),
			]);
		}

		foreach ($builder->findByType(LoggerAwareInterface::class) as $service) {
			$service->addSetup('setLogger', ['@' . $this->prefix('logger')]);
		}
	}

	protected function findByTagSorted($tag)
	{
		$builder = $this->getContainerBuilder();

		$services = $builder->findByTag($tag);
		uksort($services, function ($nameA, $nameB) use ($builder) {
			$pa = $builder->getDefinition($nameA)->getTag(self::TAG_PRIORITY) ?: 0;
			$pb = $builder->getDefinition($nameB)->getTag(self::TAG_PRIORITY) ?: 0;
			return $pa > $pb ? 1 : ($pa < $pb ? -1 : 0);
		});

		return $services;
	}

	public function afterCompile(ClassTypeGenerator $class)
	{
		$initialize = $class->getMethod('initialize');

		if (empty(Debugger::$logDirectory)) {
			$initialize->addBody('?::$logDirectory = ?;', [new PhpLiteral(Debugger::class), $this->config['logDir']]);
		}
	}

	public static function register(Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

	/**
	 * @return string
	 */
	private static function resolveLogDir(array $parameters)
	{
		if (isset($parameters['logDir'])) {
			return DIHelpers::expand('%logDir%', $parameters);
		}

		if (Debugger::$logDirectory !== NULL) {
			return Debugger::$logDirectory;
		}

		return DIHelpers::expand('%appDir%/../log', $parameters);
	}

	/**
	 * @param string $logDir
	 */
	private static function createDirectory($logDir)
	{
		if (!@mkdir($logDir, 0777, TRUE) && !is_dir($logDir)) {
			throw new \RuntimeException(sprintf('Log dir %s cannot be created', $logDir));
		}
	}

}
