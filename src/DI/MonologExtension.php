<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\DI;

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
		'accessPriority' => \Tracy\ILogger::INFO,
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
			->setClass(\Kdyby\Monolog\Logger::class, [$config['name']]);

		// Tracy adapter
		$builder->addDefinition($this->prefix('adapter'))
			->setClass(\Kdyby\Monolog\Tracy\MonologAdapter::class, [
				'monolog' => $this->prefix('@logger'),
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				'email' => \Tracy\Debugger::$email,
				'accessPriority' => $config['accessPriority'],
			])
			->addTag('logger');

		// The renderer has to be separate, to solve circural service dependencies
		$builder->addDefinition($this->prefix('blueScreenRenderer'))
			->setClass(\Kdyby\Monolog\Tracy\BlueScreenRenderer::class, [
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
	}

	protected function loadHandlers(array $config)
	{
		$builder = $this->getContainerBuilder();

		foreach ($config['handlers'] as $handlerName => $implementation) {
			\Nette\DI\Compiler::loadDefinitions($builder, [
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
				->setClass(\Kdyby\Monolog\Processor\PriorityProcessor::class)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 20);
		}

		$builder->addDefinition($this->prefix('processor.tracyException'))
			->setClass(\Kdyby\Monolog\Processor\TracyExceptionProcessor::class, [
				'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
			])
			->addTag(self::TAG_PROCESSOR)
			->addTag(self::TAG_PRIORITY, 100);

		if ($config['tracyBaseUrl'] !== NULL) {
			$builder->addDefinition($this->prefix('processor.tracyBaseUrl'))
				->setClass(\Kdyby\Monolog\Processor\TracyUrlProcessor::class, [
					'baseUrl' => $config['tracyBaseUrl'],
					'blueScreenRenderer' => $this->prefix('@blueScreenRenderer'),
				])
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 10);
		}

		foreach ($config['processors'] as $processorName => $implementation) {
			\Nette\DI\Compiler::loadDefinitions($builder, [
				$serviceName = $this->prefix('processor.' . $processorName) => $implementation,
			]);

			$builder->getDefinition($serviceName)
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, is_numeric($processorName) ? $processorName : 0);
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
				new \Nette\DI\Statement(\Kdyby\Monolog\Handler\FallbackNetteHandler::class, [
					'appName' => $config['name'],
					'logDir' => $config['logDir'],
				]),
			]);
		}

		foreach ($builder->findByType(\Psr\Log\LoggerAwareInterface::class) as $service) {
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

	public function afterCompile(\Nette\PhpGenerator\ClassType $class)
	{
		$initialize = $class->getMethod('initialize');

		if (empty(\Tracy\Debugger::$logDirectory)) {
			$initialize->addBody('?::$logDirectory = ?;', [
				new \Nette\PhpGenerator\PhpLiteral(\Tracy\Debugger::class),
				$this->config['logDir'],
			]);
		}
	}

	public static function register(\Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, \Nette\DI\Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

	/**
	 * @return string
	 */
	private static function resolveLogDir(array $parameters)
	{
		if (isset($parameters['logDir'])) {
			return \Nette\DI\Helpers::expand('%logDir%', $parameters);
		}

		if (\Tracy\Debugger::$logDirectory !== NULL) {
			return \Tracy\Debugger::$logDirectory;
		}

		return \Nette\DI\Helpers::expand('%appDir%/../log', $parameters);
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
