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



require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Monolog\DI\MonologExtension::register($config);
		$config->addConfig(__DIR__ . '/../nette-reset.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');

		return $config->createContainer();
	}



	public function testServices()
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('monolog.logger') instanceof Monolog\Logger);
	}



	public function testParameters()
	{
		$parameters = $this->createContainer()->getParameters();
		Assert::true(array_key_exists('monolog', $parameters));
		Assert::true(array_key_exists('name', $parameters['monolog']));
		Assert::true(array_key_exists('syslog', $parameters['monolog']));
	}



	public function testFunctional()
	{
		foreach (array_merge(glob(TEMP_DIR . '/*.log'), glob(TEMP_DIR . '/*.html')) as $logFile) {
			unlink($logFile);
		}

		Debugger::$logDirectory = TEMP_DIR;

		$dic = $this->createContainer();
		/** @var Monolog\Logger $logger */
		$logger = $dic->getByType('Monolog\Logger');

		Debugger::log('tracy message 1');
		Debugger::log('tracy message 2', 'error');

		Debugger::log(new \Exception('tracy exception message 1'), 'error');
		Debugger::log(new \Exception('tracy exception message 2'));

		$logger->addInfo('logger message 1');
		$logger->addInfo('logger message 2', array('channel' => 'custom'));

		$logger->addError('logger message 3');
		$logger->addError('logger message 4', array('channel' => 'custom'));

		Assert::match(
			'[%a%] tracy message 1 {"at":"%a%"} []' . "\n" .
			'[%a%] Exception: tracy exception message 2 in %a%:%d% {"at":"%a%","tracy":"exception-%a%.html"} []' . "\n" .
			'[%a%] logger message 1 [] []',
			file_get_contents(TEMP_DIR . '/info.log')
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

		Assert::count(2, glob(TEMP_DIR . '/exception-*.html'));
	}

}

run(new ExtensionTest());
