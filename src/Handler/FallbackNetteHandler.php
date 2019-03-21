<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\Handler;

/**
 * If you have no custom handlers that will write and/or send your messages somewhere,
 * this one will just write them to the log/ directory, just like the default Tracy logger does.
 */
class FallbackNetteHandler extends \Monolog\Handler\ErrorLogHandler
{

	use \Kdyby\StrictObjects\Scream;

	/**
	 * @var string
	 */
	private $appName;

	/**
	 * @var string
	 */
	private $logDir;

	/**
	 * @var \Monolog\Formatter\LineFormatter
	 */
	private $defaultFormatter;

	/**
	 * @var \Monolog\Formatter\LineFormatter
	 */
	private $priorityFormatter;

	public function __construct($appName, $logDir, $expandNewlines = FALSE, $level = \Monolog\Logger::DEBUG)
	{
		parent::__construct(self::SAPI, $level, TRUE, $expandNewlines);
		$this->appName = $appName;
		$this->logDir = $logDir;

		$this->defaultFormatter = new \Monolog\Formatter\LineFormatter('[%datetime%] %message% %context% %extra%');
		$this->priorityFormatter = new \Monolog\Formatter\LineFormatter('[%datetime%] %level_name%: %message% %context% %extra%');
	}

	public function handle(array $record)
	{
		if ($record['channel'] === $this->appName) {
			$this->setFormatter($this->defaultFormatter);
			$record['filename'] = strtolower($record['level_name']);

		} else {
			$this->setFormatter($this->priorityFormatter);
			$record['filename'] = $record['channel'];
		}

		return parent::handle($record);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function write(array $record)
	{
		if ($this->expandNewlines) {
			$entry = '';
			/** @var string[] $lines */
			$lines = preg_split('{[\r\n]+}', (string) $record['message']);
			foreach ($lines as $line) {
				$entry .= trim($this->getFormatter()->format(['message' => $line] + $record)) . PHP_EOL;
			}

		} else {
			$entry = preg_replace('#\s*\r?\n\s*#', ' ', trim($record['formatted'])) . PHP_EOL;
		}

		$file = $this->logDir . '/' . strtolower($record['filename'] ?: 'info') . '.log';
		if (!@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX)) {
			throw new \RuntimeException(sprintf('Unable to write to log file %s. Is directory writable?', $file));
		}
	}

}
