<?php

namespace Kdyby\Monolog\Diagnostics;

use Tracy\Logger;

class TracyLogger extends Logger
{

	/**
	 * @return string
	 */
	public function logException($exception, $file = NULL)
	{
		return parent::logException($exception, $file);
	}

}
