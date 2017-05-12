<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\Diagnostics;

use Monolog;
use Tracy\Debugger;
use Tracy\Logger;



/**
 * Replaces the default Tracy logger,
 * which allows to preprocess all messages and pass then to Monolog for processing.
 *
 * @author Martin Bažík <martin@bazo.sk>
 * @author Filip Procházka <filip@prochazka.su>
 */
class MonologAdapter extends Logger
{

	/**
	 * @var array
	 */
	protected $priorityMap = [
		self::DEBUG => Monolog\Logger::DEBUG,
		self::INFO => Monolog\Logger::INFO,
		self::WARNING => Monolog\Logger::WARNING,
		self::ERROR => Monolog\Logger::ERROR,
		self::EXCEPTION => Monolog\Logger::CRITICAL,
		self::CRITICAL => Monolog\Logger::CRITICAL
	];

	/**
	 * @var Monolog\Logger
	 */
	protected $monolog;



	public function __construct(Monolog\Logger $monolog)
	{
		$this->monolog = $monolog;

		// BC with Nette
		if (interface_exists('Tracy\ILogger') && method_exists($this, 'logException')) {
			parent::__construct(Debugger::$logDirectory, Debugger::$email, Debugger::getBlueScreen());
		}

		$this->directory = &Debugger::$logDirectory;
		$this->email = &Debugger::$email;
	}



	public function log($message, $priority = self::INFO)
	{
		$exception = ($message instanceof \Exception || $message instanceof \Throwable) ? $message : NULL;

		if (!is_array($message) && method_exists($this, 'logException')) { // forward BC with Nette in 2.3-dev
			$exceptionFile = ($message instanceof \Exception || $message instanceof \Throwable) ? $this->logException($message) : NULL;

			$message = [
				@date('[Y-m-d H-i-s]'),
				$this->formatMessage($message),
				' @ ' . self::getSource(),
				$exceptionFile ? ' @@ ' . basename($exceptionFile) : NULL
			];

			if (in_array($priority, [self::ERROR, self::EXCEPTION, self::CRITICAL], TRUE)) {
				$this->sendEmail(implode('', $message));
			}
		}

		$normalised = $message;
		$context = [
			'at' => self::getSource(),
		];

		if ($exception !== NULL) {
			$context['exception'] = $exception;
		}

		if (is_array($message)) { // bc with Nette until 2.3
			if (count($message) >= 2 && preg_match('~\\[[\\d+ -]+\\]~i', $message[0])) {
				array_shift($message); // first entry is probably time
			}

			if (isset($message[1]) && (preg_match('~\\@\\s+https?:\\/\\/.+~', $message[1]) || preg_match('~CLI:.+~i', $message[1]))) {
				$context['at'] = ltrim($message[1], '@ ');
				unset($message[1]);
			}

			if (isset($message[2]) && preg_match('~\\@\\@\\s+exception\\-[^\\s]+\\.html~', $message[2])) {
				$context['tracy'] = ltrim($message[2], '@ ');
				unset($message[2]);
			}

			$normalised = implode($message);
		}

		switch ($priority) {
			case 'access':
				$this->monolog->addInfo($normalised, ['priority' => $priority] + $context);
				break;

			default:
				$this->monolog->addRecord(
					$this->getLevel($priority),
					$normalised,
					['priority' => $priority] + $context
				);
		}
		return isset($context['tracy']) ? $context['tracy'] : '';
	}


	/**
	 * @param string $priority
	 * @return int
	 */
	protected function getLevel($priority)
	{
		if (isset($this->priorityMap[$priority])) {
			return $this->priorityMap[$priority];
		}

		$levels = $this->monolog->getLevels();
		return isset($levels[$uPriority = strtoupper($priority)]) ? $levels[$uPriority] : Monolog\Logger::INFO;
	}


	/**
	 * @internal
	 * @author David Grudl
	 * @see https://github.com/nette/tracy/blob/922630e689578f6daae185dba251cded831d9162/src/Tracy/Helpers.php#L146
	 */
	protected static function getSource()
	{
		if (isset($_SERVER['REQUEST_URI'])) {
			return (!empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https://' : 'http://')
			. (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')
			. $_SERVER['REQUEST_URI'];

		} else {
			return empty($_SERVER['argv']) ? 'CLI' : 'CLI: ' . implode(' ', $_SERVER['argv']);
		}
	}

}
