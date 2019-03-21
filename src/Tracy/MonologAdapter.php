<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Monolog\Tracy;

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
		self::DEBUG => \Monolog\Logger::DEBUG,
		self::INFO => \Monolog\Logger::INFO,
		self::WARNING => \Monolog\Logger::WARNING,
		self::ERROR => \Monolog\Logger::ERROR,
		self::EXCEPTION => \Monolog\Logger::CRITICAL,
		self::CRITICAL => \Monolog\Logger::CRITICAL,
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
		\Monolog\Logger $monolog,
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
	public function getExceptionFile(\Throwable $exception): string
	{
		return $this->blueScreenRenderer->getExceptionFile($exception);
	}

	public function log($originalMessage, $priority = self::INFO)
	{
		$message = $this->formatMessage($originalMessage);
		$context = [
			'priority' => $priority,
			'at' => \Tracy\Helpers::getSource(),
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
				' @ ' . \Tracy\Helpers::getSource(),
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
	protected function getLevel($priority)
	{
		if (isset($this->priorityMap[$priority])) {
			return $this->priorityMap[$priority];
		}

		$levels = \Monolog\Logger::getLevels();
		return isset($levels[$uPriority = strtoupper($priority)]) ? $levels[$uPriority] : \Monolog\Logger::INFO;
	}

}
