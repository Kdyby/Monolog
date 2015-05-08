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

	private $defaults = array(
		'handlers' => array(),
		'processors' => array(),
		'name' => 'app',
		'hookToTracy' => TRUE,
		// 'registerFallback' => TRUE,
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		if (array_key_exists($this->name, $builder->parameters)) {
			$builder->parameters[$this->name]['name'] = $config['name'];
		} else {
			$builder->parameters[$this->name] = array('name' => $config['name']);
		}

		$builder->addDefinition($this->prefix('logger'))
			->setClass('Kdyby\Monolog\Logger', array($config['name']));

		// change channel name to priority if available
		$builder->addDefinition($this->prefix('processor.priorityProcessor'))
			->setClass('Kdyby\Monolog\Processor\PriorityProcessor')
			->addTag(self::TAG_PROCESSOR);

		if (!isset($builder->parameters['logDir'])) {
			if (Debugger::$logDirectory) {
				$builder->parameters['logDir'] = Debugger::$logDirectory;

			} else {
				$builder->parameters['logDir'] = $builder->expand('%appDir%/../log');
			}
		}

		if (!is_dir($builder->parameters['logDir'])) {
			@mkdir($builder->parameters['logDir']);
		}

		// handlers
		foreach ($config['handlers'] as $handlerName => $implementation) {
			$this->compiler->parseServices($builder, array(
				'services' => array($serviceName = $this->prefix('handler.' . $handlerName) => $implementation),
			));

			$builder->getDefinition($serviceName)->addTag(self::TAG_HANDLER);
		}

		// processors
		foreach ($config['processors'] as $processorName => $implementation) {
			$this->compiler->parseServices($builder, array(
				'services' => array($serviceName = $this->prefix('processor.' . $processorName) => $implementation),
			));

			$builder->getDefinition($serviceName)->addTag(self::TAG_PROCESSOR);
		}

		// Tracy adapter
		$builder->addDefinition($this->prefix('adapter'))
			->setClass('Kdyby\Monolog\Diagnostics\MonologAdapter', array($this->prefix('@logger')))
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



	public function beforeCompile()
	{
		$builder = $this->getContainerBuilder();
		$logger = $builder->getDefinition($this->prefix('logger'));

		foreach ($handlers = $builder->findByTag(self::TAG_HANDLER) as $serviceName => $meta) {
			$logger->addSetup('pushHandler', array('@' . $serviceName));
		}

		foreach ($builder->findByTag(self::TAG_PROCESSOR) as $serviceName => $meta) {
			$logger->addSetup('pushProcessor', array('@' . $serviceName));
		}

		$config = $this->getConfig(array('registerFallback' => empty($handlers)) + $this->getConfig($this->defaults));

		if ($config['registerFallback']) {
			$logger->addSetup('pushHandler', array(
				new Statement('Kdyby\Monolog\Handler\FallbackNetteHandler', array($config['name'], $builder->expand('%logDir%')))
			));
		}
	}



	public function afterCompile(Code\ClassType $class)
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$initialize = $class->methods['initialize'];

		if ($config['hookToTracy'] === TRUE) {
			if (method_exists('Tracy\Debugger', 'setLogger')) {
				$code = '\Tracy\Debugger::setLogger($this->getService(?));';

			} elseif (method_exists('Nette\Diagnostics\Debugger', 'setLogger')) {
				$code = '\Nette\Diagnostics\Debugger::setLogger($this->getService(?));';

			} else {
				$code = '\Nette\Diagnostics\Debugger::$logger = $this->getService(?);';
			}

			$initialize->addBody($code, array($this->prefix('adapter')));
		}

		if (empty(Debugger::$logDirectory)) {
			$initialize->addBody('Tracy\Debugger::$logDirectory = ?;', array($builder->expand('%logDir%')));
		}
	}



	public static function register(Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Compiler $compiler) {
			$compiler->addExtension('monolog', new MonologExtension());
		};
	}

}
