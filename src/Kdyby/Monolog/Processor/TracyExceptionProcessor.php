<?php

namespace Kdyby\Monolog\Processor;

use Kdyby\Monolog\Diagnostics\MonologAdapter;
use Kdyby\Monolog\Diagnostics\TracyLogger;
use Kdyby\Monolog\Diagnostics\TracyLoggerOld;
use Tracy\Debugger;

class TracyExceptionProcessor
{

	private $processedExceptionFileNames = [];

	/**
	 * @var \Kdyby\Monolog\Diagnostics\TracyLogger
	 */
	private $tracyLogger;

	/**
	 * @var string
	 */
	private $baseUrl;

	public function __construct($tracyDir, $baseUrl)
	{
		$this->baseUrl = rtrim($baseUrl, '/');

		if (version_compare(Debugger::VERSION, '2.3.3', '>=')) {
			$this->tracyLogger = new TracyLogger($tracyDir);
		} else {
			$this->tracyLogger = new TracyLoggerOld($tracyDir);
		}
	}

	public function __invoke(array $record)
	{
		if (!isset($record['context']['tracy']) && isset($record['context']['exception']) && $record['context']['exception'] instanceof \Exception) {
			$fileName = $this->tracyLogger->getExceptionFile($record['context']['exception']);

			if (!isset($this->processedExceptionFileNames[$fileName])) {
				$this->tracyLogger->logException($record['context']['exception'], $fileName);
				$this->processedExceptionFileNames[$fileName] = TRUE;
			}

			$record['context']['tracy'] = ltrim(strrchr($fileName, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
		}

		if (isset($record['context']['tracy'])) {
			$record['context']['tracyUrl'] = sprintf('%s/%s', $this->baseUrl, $record['context']['tracy']);
		}

		return $record;
	}

}
