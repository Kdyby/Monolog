<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog\Processor;

/**
 * Helps you change the channel name of the record,
 * when you wanna have multiple log files coming out of your application.
 */
class PriorityProcessor
{

	use \Kdyby\StrictObjects\Scream;

	public function __invoke($record)
	{
		if (isset($record['context']['channel'])) {
			$record['channel'] = $record['context']['channel'];
			unset($record['context']['channel']);

		} elseif (isset($record['context']['priority'])) {
			$rename = strtoupper($record['context']['priority']);
			if (!array_key_exists($rename, \Monolog\Logger::getLevels())) {
				$record['channel'] = strtolower($rename);
			}
			unset($record['context']['priority']);
		}

		return $record;
	}

}
