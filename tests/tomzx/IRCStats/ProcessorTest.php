<?php

namespace tomzx\IRCStats\Test;

use Mockery as m;
use tomzx\IRCStats\Processor;

class ProcessorTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \Mockery\MockInterface
	 */
	protected $databaseProxy;
	/**
	 * @var \tomzx\IRCStats\Processor
	 */
	protected $processor;

	public function setUp()
	{
		$this->databaseProxy = m::mock('\tomzx\IRCStats\DatabaseProxy');
		$this->processor = new Processor($this->databaseProxy);
	}

	public function tearDown()
	{
		m::close();
	}

	public function testRun()
	{
		$connection = m::mock('\Illuminate\Database\Connection');

		$this->databaseProxy->shouldReceive('getConnection')->once()->andReturn($connection);

		$connection->shouldReceive('table->max')->once();

		$connection->shouldReceive('table->select->where->orderBy->limit->get')->once()->andReturn([]);

		$this->processor->run();
	}
}
