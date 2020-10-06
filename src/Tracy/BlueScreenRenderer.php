<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Monolog\Tracy;

use Tracy\BlueScreen;

class BlueScreenRenderer extends \Tracy\Logger
{

	use \Kdyby\StrictObjects\Scream;

	public function __construct($directory, BlueScreen $blueScreen)
	{
		parent::__construct($directory, NULL, $blueScreen);
	}

	/**
	 * @param \Exception|\Throwable $exception
	 * @param string $file
	 * @return string logged error filename
	 */
	public function renderToFile($exception, $file): string
	{
		return $this->logException($exception, $file);
	}

	/**
	 * @internal
	 * @deprecated
	 */
	public function log($message, $priority = self::INFO): ?string
	{
		throw new \Kdyby\Monolog\Exception\NotSupportedException('This class is only for rendering exceptions');
	}

	/**
	 * @internal
	 * @deprecated
	 */
	public function defaultMailer($message, string $email): void
	{
		// pass
	}

}
