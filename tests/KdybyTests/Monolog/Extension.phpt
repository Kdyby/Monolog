<?php

/**
 * Test: Kdyby\Monolog\Extension.
 *
 * @testCase KdybyTests\Monolog\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Monolog
 */

namespace KdybyTests\Monolog;

use Kdyby;
use KdybyTests;
use Monolog;
use Nette;
use Tester;
use Tester\Assert;
use Tracy\Debugger;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use Kdyby\Monolog\Processor\TracyUrlProcessor;
use Kdyby\Monolog\Processor\PriorityProcessor;
use Kdyby\Monolog\Processor\TracyExceptionProcessor;
use Monolog\Logger;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\ChromePHPHandler;
use Monolog\Handler\NewRelicHandler;



require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer($configName = NULL)
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Monolog\DI\MonologExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');

		if ($configName !== NULL) {
			$config->addConfig(__DIR__ . '/config/' . $configName . '.neon');
		}

		return $config->createContainer();
	}



	public function testServices()
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('monolog.logger') instanceof Monolog\Logger);
	}



	public function testFunctional()
	{
		foreach (array_merge(glob(TEMP_DIR . '/*.log'), glob(TEMP_DIR . '/*.html')) as $logFile) {
			unlink($logFile);
		}

		Debugger::$logDirectory = TEMP_DIR;

		$dic = $this->createContainer();
		$logger = $dic->getByType(Logger::class);
		assert($logger instanceof Monolog\Logger);

		Debugger::log('tracy message 1');
		Debugger::log('tracy message 2', 'error');

		Debugger::log(new \Exception('tracy exception message 1'), 'error');
		Debugger::log(new \Exception('tracy exception message 2'));

		$logger->addInfo('logger message 1');
		$logger->addInfo('logger message 2', ['channel' => 'custom']);

		$logger->addError('logger message 3');
		$logger->addError('logger message 4', ['channel' => 'custom']);

		$logger->addWarning('exception message 1', ['exception' => new \Exception('exception message 1')]);

		Assert::match(
			'[%a%] tracy message 1 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 2 in %a%:%d% {"at":"%a%","tracy":"exception-%a%.html"} []' . "\n" .
			'[%a%] logger message 1 [] []',
			file_get_contents(TEMP_DIR . '/info.log')
		);

		Assert::match(
			'[%a%] exception message 1 {"exception":"%a%","tracy":"exception-%a%.html"} []',
			file_get_contents(TEMP_DIR . '/warning.log')
		);

		Assert::match(
			'[%a%] tracy message 2 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 1 in %a%:%d% {"at":"%a%","tracy":"exception-%a%.html"} []' . "\n" .
			'[%a%] logger message 3 [] []',
			file_get_contents(TEMP_DIR . '/error.log')
		);

		Assert::match(
			'[%a%] INFO: logger message 2 [] []' . "\n" .
			'[%a%] ERROR: logger message 4 [] []',
			file_get_contents(TEMP_DIR . '/custom.log')
		);

		Assert::count(3, glob(TEMP_DIR . '/exception-*.html'));
	}



	public function testHandlersSorting()
	{
		$dic = $this->createContainer('handlers');
		$logger = $dic->getByType(Logger::class);
		$handlers = $logger->getHandlers();
		Assert::count(3, $handlers);
		Assert::type(NewRelicHandler::class, array_shift($handlers));
		Assert::type(ChromePHPHandler::class, array_shift($handlers));
		Assert::type(BrowserConsoleHandler::class, array_shift($handlers));
	}



	public function testProcessorsSorting()
	{
		$dic = $this->createContainer('processors');
		$logger = $dic->getByType(Logger::class);
		$processors = $logger->getProcessors();
		Assert::count(6, $processors);
		Assert::type(TracyExceptionProcessor::class, array_shift($processors));
		Assert::type(PriorityProcessor::class, array_shift($processors));
		Assert::type(TracyUrlProcessor::class, array_shift($processors));
		Assert::type(WebProcessor::class, array_shift($processors));
		Assert::type(ProcessIdProcessor::class, array_shift($processors));
		Assert::type(GitProcessor::class, array_shift($processors));
	}

}

run(new ExtensionTest());
