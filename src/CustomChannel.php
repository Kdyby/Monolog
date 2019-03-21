<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog;

class CustomChannel extends \Monolog\Logger
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Kdyby\Monolog\Logger
	 */
	private $parentLogger;

	public function __construct($name, \Kdyby\Monolog\Logger $parentLogger)
	{
		parent::__construct($name, [], []);
		$this->parentLogger = $parentLogger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function pushHandler(\Monolog\Handler\HandlerInterface $handler)
	{
		$this->parentLogger->pushHandler($handler);
	}

	/**
	 * {@inheritdoc}
	 */
	public function popHandler()
	{
		return $this->parentLogger->popHandler();
	}

	/**
	 * {@inheritdoc}
	 */
	public function getHandlers()
	{
		return $this->parentLogger->getHandlers();
	}

	/**
	 * {@inheritdoc}
	 */
	public function pushProcessor($callback)
	{
		$this->parentLogger->pushProcessor($callback);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return callable
	 */
	public function popProcessor()
	{
		return $this->parentLogger->popProcessor();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return callable[]
	 */
	public function getProcessors()
	{
		return $this->parentLogger->getProcessors();
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addRecord($level, $message, array $context = [])
	{
		return $this->parentLogger->addRecord($level, $message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addDebug($message, array $context = [])
	{
		return $this->parentLogger->addDebug($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addInfo($message, array $context = [])
	{
		return $this->parentLogger->addInfo($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addNotice($message, array $context = [])
	{
		return $this->parentLogger->addNotice($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addWarning($message, array $context = [])
	{
		return $this->parentLogger->addWarning($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addError($message, array $context = [])
	{
		return $this->parentLogger->addError($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addCritical($message, array $context = [])
	{
		return $this->parentLogger->addCritical($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addAlert($message, array $context = [])
	{
		return $this->parentLogger->addAlert($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function addEmergency($message, array $context = [])
	{
		return $this->parentLogger->addEmergency($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function isHandling($level)
	{
		return $this->parentLogger->isHandling($level);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function log($level, $message, array $context = [])
	{
		return $this->parentLogger->log($level, $message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function debug($message, array $context = [])
	{
		return $this->parentLogger->debug($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function info($message, array $context = [])
	{
		return $this->parentLogger->info($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function notice($message, array $context = [])
	{
		return $this->parentLogger->notice($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function warn($message, array $context = [])
	{
		return $this->parentLogger->warn($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function warning($message, array $context = [])
	{
		return $this->parentLogger->warning($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function err($message, array $context = [])
	{
		return $this->parentLogger->err($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function error($message, array $context = [])
	{
		return $this->parentLogger->error($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function crit($message, array $context = [])
	{
		return $this->parentLogger->crit($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function critical($message, array $context = [])
	{
		return $this->parentLogger->critical($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function alert($message, array $context = [])
	{
		return $this->parentLogger->alert($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function emerg($message, array $context = [])
	{
		return $this->parentLogger->emerg($message, ['channel' => $this->name] + $context);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @return bool Whether the record has been processed
	 */
	public function emergency($message, array $context = [])
	{
		return $this->parentLogger->emergency($message, ['channel' => $this->name] + $context);
	}

}
