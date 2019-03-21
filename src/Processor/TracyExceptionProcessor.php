<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog\Processor;

class TracyExceptionProcessor
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var \Kdyby\Monolog\Tracy\BlueScreenRenderer
	 */
	private $blueScreenRenderer;

	public function __construct(\Kdyby\Monolog\Tracy\BlueScreenRenderer $blueScreenRenderer)
	{
		$this->blueScreenRenderer = $blueScreenRenderer;
	}

	public function __invoke(array $record)
	{
		if (!$this->isHandling($record)) {
			return $record;
		}

		$exception = $record['context']['exception'];
		$filename = $this->blueScreenRenderer->getExceptionFile($exception);
		$record['context']['tracy_filename'] = basename($filename);

		if (!file_exists($filename)) {
			$this->blueScreenRenderer->renderToFile($exception, $filename);
			$record['context']['tracy_created'] = TRUE;
		}

		return $record;
	}

	public function isHandling(array $record)
	{
		return !isset($record['context']['tracy'])
			&& !isset($record['context']['tracy_filename'])
			&& isset($record['context']['exception'])
			&& ($record['context']['exception'] instanceof \Throwable || $record['context']['exception'] instanceof \Exception);
	}

}
