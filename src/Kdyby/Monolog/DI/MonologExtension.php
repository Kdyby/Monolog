<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\DI;

use Nette;
use Nette\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Statement;
use Nette\PhpGenerator as Code;
use Tracy\Debugger;



/**
 * Integrates the Monolog seamlessly into your Nette Framework application.
 *
 * @author Martin Bažík <martin@bazo.sk>
 * @author Filip Procházka <filip@prochazka.su>
 */
class MonologExtension extends CompilerExtension
{

	const TAG_HANDLER = 'monolog.handler';
	const TAG_PROCESSOR = 'monolog.processor';
	const TAG_PRIORITY = 'monolog.priority';

	private $defaults = [
		'handlers' => [],
		'processors' => [],
		'name' => 'app',
		'hookToTracy' => TRUE,
		'tracyBaseUrl' => NULL,
		'usePriorityProcessor' => TRUE,
		'registerFallback' => NULL,
	];



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		if (!isset($builder->parameters[$this->name]) || (is_array($builder->parameters[$this->name]) && !isset($builder->parameters[$this->name]['name']))) {
			$builder->parameters[$this->name]['name'] = $config['name'];
		}

		$builder->addDefinition($this->prefix('logger'))
			->setClass('Kdyby\Monolog\Logger', [$config['name']]);

		if (!isset($builder->parameters['logDir'])) {
			if (Debugger::$logDirectory) {
				$builder->parameters['logDir'] = Debugger::$logDirectory;

			} else if(isset($builder->parameters['appDir'])) {
				$builder->parameters['logDir'] = $builder->parameters['appDir'] . '/../log';
			} else {
				throw new \LogicException('Cannot determine your logDir automatically. Please provide `logDir` in your configuration.'); // todo: better type and more concrete advice
			}
		}

		if (!@mkdir($builder->parameters['logDir']) && !is_dir($builder->parameters['logDir'])) { // this is race-condition free
			throw new \RuntimeException('Cannot create log directory.'); // todo: better exception
		}

		$this->loadHandlers($config);
		$this->loadProcessors($config);

		// Tracy adapter
		$builder->addDefinition($this->prefix('adapter'))
			->setClass('Kdyby\Monolog\Diagnostics\MonologAdapter', [$this->prefix('@logger')])
			->addTag('logger');

		if ($builder->hasDefinition('tracy.logger')) { // since Nette 2.3
			$builder->removeDefinition($existing = 'tracy.logger');

			if (method_exists($builder, 'addAlias')) { // since Nette 2.3
				$builder->addAlias($existing, $this->prefix('adapter'));

			} else { // old way of providing BC
				$builder->addDefinition($existing)
					->setFactory($this->prefix('@adapter'));
			}
		}
	}



	/**
	 * @param $config
	 */
	protected function loadHandlers(array $config)
	{
		$builder = $this->getContainerBuilder();

		foreach ($config['handlers'] as $handlerName => $implementation) {
			Compiler::loadDefinitions($builder, [
				$serviceName = $this->prefix('handler.' . $handlerName) => $implementation
			]);

			$builder->getDefinition($serviceName)
				->addTag(self::TAG_HANDLER)
				->addTag(self::TAG_PRIORITY, is_numeric($handlerName) ? $handlerName : 0);
		}
	}



	/**
	 * @param $config
	 */
	protected function loadProcessors(array $config)
	{
		$builder = $this->getContainerBuilder();

		if ($config['usePriorityProcessor'] === TRUE) {
			// change channel name to priority if available
			$builder->addDefinition($this->prefix('processor.priorityProcessor'))
				->setClass('Kdyby\Monolog\Processor\PriorityProcessor')
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 20);
		}

		$builder->addDefinition($this->prefix('processor.tracyException'))
			->setClass('Kdyby\Monolog\Processor\TracyExceptionProcessor', [$builder->parameters['logDir']])
			->addTag(self::TAG_PROCESSOR)
			->addTag(self::TAG_PRIORITY, 100);

		if ($config['tracyBaseUrl'] !== NULL) {
			$builder->addDefinition($this->prefix('processor.tracyBaseUrl'))
				->setClass('Kdyby\Monolog\Processor\TracyUrlProcessor', [$config['tracyBaseUrl']])
				->addTag(self::TAG_PROCESSOR)
				->addTag(self::TAG_PRIORITY, 10);
		}

		foreach ($config['processors'] as $processorName => $implementation) {
			Compiler::loadDefinitions($builder, [
				$serviceName = $this->prefix('processor.' . $processorName) => $implementation
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

		$config = $this->validateConfig(['registerFallback' => empty($handlers)] + $this->validateConfig($this->defaults));

		if ($config['registerFallback']) {
			$logger->addSetup('pushHandler', [
				new Statement('Kdyby\Monolog\Handler\FallbackNetteHandler', [$config['name'], $builder->parameters['logDir']])
			]);
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



	public function afterCompile(Code\ClassType $class)
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);

		$initialize = $class->getMethod('initialize');

		if ($config['hookToTracy'] === TRUE) {
			if (method_exists('Tracy\Debugger', 'setLogger')) {
				$code = '\Tracy\Debugger::setLogger($this->getService(?));';

			} elseif (method_exists('Nette\Diagnostics\Debugger', 'setLogger')) {
				$code = '\Nette\Diagnostics\Debugger::setLogger($this->getService(?));';

			} else {
				$code = '\Nette\Diagnostics\Debugger::$logger = $this->getService(?);';
			}

			$initialize->addBody($code, [$this->prefix('adapter')]);
		}

		if (empty(Debugger::$logDirectory)) {
			$initialize->addBody('Tracy\Debugger::$logDirectory = ?;', [$builder->parameters['logDir']]);
		}
	}



	public static function register(Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

}
