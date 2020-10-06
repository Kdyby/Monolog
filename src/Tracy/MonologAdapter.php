<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\Tracy;

use Monolog\Logger as MonologLogger;
use Tracy\Helpers;

/**
 * Replaces the default Tracy logger,
 * which allows to preprocess all messages and pass then to Monolog for processing.
 */
class MonologAdapter extends \Tracy\Logger
{

	use \Kdyby\StrictObjects\Scream;

	const ACCESS = 'access';

	/**
	 * @var int[]
	 */
	private $priorityMap = [
		self::DEBUG => MonologLogger::DEBUG,
		self::INFO => MonologLogger::INFO,
		self::WARNING => MonologLogger::WARNING,
		self::ERROR => MonologLogger::ERROR,
		self::EXCEPTION => MonologLogger::CRITICAL,
		self::CRITICAL => MonologLogger::CRITICAL,
	];

	/**
	 * @var \Monolog\Logger
	 */
	private $monolog;

	/**
	 * @var \Kdyby\Monolog\Tracy\BlueScreenRenderer
	 */
	private $blueScreenRenderer;

	/**
	 * @var string
	 */
	private $accessPriority;

	public function __construct(
		MonologLogger $monolog,
		BlueScreenRenderer $blueScreenRenderer,
		$email = NULL,
		$accessPriority = self::INFO
	)
	{
		parent::__construct($blueScreenRenderer->directory, $email);
		$this->monolog = $monolog;
		$this->blueScreenRenderer = $blueScreenRenderer;
		$this->accessPriority = $accessPriority;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getExceptionFile(\Throwable $exception, string $level = self::EXCEPTION): string
	{
		return $this->blueScreenRenderer->getExceptionFile($exception);
	}

	public function log($originalMessage, $priority = self::INFO): ?string
	{
		$message = $this->formatMessage($originalMessage);
		$context = [
			'priority' => $priority,
			'at' => Helpers::getSource(),
		];

		if ($originalMessage instanceof \Throwable || $originalMessage instanceof \Exception) {
			$context['exception'] = $originalMessage;
		}

		$exceptionFile = ($originalMessage instanceof \Throwable || $originalMessage instanceof \Exception)
			? $this->getExceptionFile($originalMessage)
			: NULL;

		if ($this->email !== NULL && $this->mailer !== NULL && in_array($priority, [self::ERROR, self::EXCEPTION, self::CRITICAL], TRUE)) {
			$this->sendEmail(implode(' ', [
				@date('[Y-m-d H-i-s]'),
				$message,
				' @ ' . Helpers::getSource(),
				($exceptionFile !== NULL) ? ' @@ ' . basename($exceptionFile) : NULL,
			]));
		}

		if ($priority === self::ACCESS) {
			$priority = $this->accessPriority;
		}

		$this->monolog->addRecord(
			$this->getLevel($priority),
			$message,
			$context
		);

		return $exceptionFile;
	}

	/**
	 * @param string $priority
	 *
	 * @return int
	 */
	protected function getLevel($priority): int
	{
		if (isset($this->priorityMap[$priority])) {
			return $this->priorityMap[$priority];
		}

		$levels = MonologLogger::getLevels();
		return isset($levels[$uPriority = strtoupper($priority)]) ? $levels[$uPriority] : MonologLogger::INFO;
	}

}
