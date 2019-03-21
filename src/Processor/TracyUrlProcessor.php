<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog\Processor;

class TracyUrlProcessor
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var string
	 */
	private $baseUrl;

	/**
	 * @var \Kdyby\Monolog\Tracy\BlueScreenRenderer
	 */
	private $blueScreenRenderer;

	public function __construct($baseUrl, \Kdyby\Monolog\Tracy\BlueScreenRenderer $blueScreenRenderer)
	{
		$this->baseUrl = rtrim($baseUrl, '/');
		$this->blueScreenRenderer = $blueScreenRenderer;
	}

	public function __invoke(array $record)
	{
		if ($this->isHandling($record)) {
			$exceptionFile = $this->blueScreenRenderer->getExceptionFile($record['context']['exception']);
			$record['context']['tracyUrl'] = sprintf('%s/%s', $this->baseUrl, basename($exceptionFile));
		}

		return $record;
	}

	public function isHandling(array $record)
	{
		return isset($record['context']['exception'])
			&& ($record['context']['exception'] instanceof \Throwable || $record['context']['exception'] instanceof \Exception);
	}

}
