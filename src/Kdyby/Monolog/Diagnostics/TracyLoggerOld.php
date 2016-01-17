<?php

namespace Kdyby\Monolog\Diagnostics;

use Tracy\Logger;

class TracyLoggerOld extends Logger
{

	/**
	 * @return string
	 */
	public function getExceptionFile(\Exception $exception)
	{
		return parent::getExceptionFile($exception);
	}

	/**
	 * @return string
	 */
	public function logException(\Exception $exception, $file = NULL)
	{
		return parent::logException($exception, $file);
	}

}
