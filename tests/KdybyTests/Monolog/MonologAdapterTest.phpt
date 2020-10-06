<?php

/**
 * Test: Kdyby\Monolog\MonologAdapter.
 *
 * @testCase
 */

namespace KdybyTests\Monolog;

use DateTimeInterface;
use Kdyby\Monolog\Tracy\BlueScreenRenderer;
use Kdyby\Monolog\Tracy\MonologAdapter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use Tester\Assert;
use Tracy\BlueScreen;

require_once __DIR__ . '/../bootstrap.php';

class MonologAdapterTest extends \Tester\TestCase
{

	/**
	 * @var \Kdyby\Monolog\Tracy\MonologAdapter
	 */
	protected $adapter;

	/**
	 * @var \Monolog\Logger
	 */
	protected $monolog;

	/**
	 * @var \Monolog\Handler\TestHandler
	 */
	protected $testHandler;

	protected function setUp()
	{
		$this->testHandler = new TestHandler();
		$this->monolog = new MonologLogger('kdyby', [$this->testHandler]);
		$blueScreenRenderer = new BlueScreenRenderer(TEMP_DIR, new BlueScreen());
		$this->adapter = new MonologAdapter($this->monolog, $blueScreenRenderer);
	}

	/**
	 * @return array
	 */
	public function dataLogStandard()
	{
		return [
			['test message 1', 'debug'],
			['test message 2', 'info'],
			['test message 3', 'notice'],
			['test message 4', 'warning'],
			['test message 5', 'error'],
			['test message 6', 'critical'],
			['test message 7', 'alert'],
			['test message 8', 'emergency'],
		];
	}

	/**
	 * @dataProvider dataLogStandard
	 */
	public function testLogStandard($message, $priority)
	{
		Assert::count(0, $this->testHandler->getRecords());
		$this->adapter->log($message, $priority);
		Assert::count(1, $this->testHandler->getRecords());

		list($record) = $this->testHandler->getRecords();
		Assert::same('kdyby', $record['channel']);
		Assert::same($message, $record['message']);
		Assert::same(strtoupper($priority), $record['level_name']);
		Assert::same($priority, $record['context']['priority']);
		Assert::type(DateTimeInterface::class, $record['datetime']);
		Assert::match('CLI%a%: %a%/MonologAdapterTest.phpt%a%', $record['context']['at']);
	}

	public function testLogWithCustomPriority()
	{
		$this->adapter->log('test message', 'nemam');
		Assert::count(1, $this->testHandler->getRecords());

		list($record) = $this->testHandler->getRecords();
		Assert::same('kdyby', $record['channel']);
		Assert::same('test message', $record['message']);
		Assert::same('INFO', $record['level_name']);
		Assert::same('nemam', $record['context']['priority']);
		Assert::match('CLI%a%: %a%/MonologAdapterTest.phpt%a%', $record['context']['at']);
	}

	public function testLogWithAccessPriority()
    {
        $this->adapter->log('test access message', MonologAdapter::ACCESS);
        Assert::count(1, $this->testHandler->getRecords());

        list($record) = $this->testHandler->getRecords();
        Assert::same('kdyby', $record['channel']);
        Assert::same('test access message', $record['message']);
        Assert::same('INFO', $record['level_name']);
        Assert::same(MonologAdapter::ACCESS, $record['context']['priority']);
        Assert::match('CLI%a%: %a%/MonologAdapterTest.phpt%a%', $record['context']['at']);
    }

}

(new MonologAdapterTest())->run();
