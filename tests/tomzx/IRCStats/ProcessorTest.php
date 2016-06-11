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

	public function testRunWithExistingDictionary()
	{
		$connection = m::mock('\Illuminate\Database\Connection');
		$builder = m::mock('\Illuminate\Database\Query\Builder');

		$this->databaseProxy->shouldReceive('getConnection')->once()->andReturn($connection);

		$connection->shouldReceive('table')->andReturn($builder);

		$builder->shouldReceive('count')->once()->andReturn(1);

		$builder->shouldReceive('max')->once();

		$builder->shouldReceive('select->where->orderBy->limit->get')->once()->andReturn([]);

		$this->processor->run();
	}

	public function testRunAndCreateDictionary()
	{
		$connection = m::mock('\Illuminate\Database\Connection');
		$builder = m::mock('\Illuminate\Database\Query\Builder');

		$this->databaseProxy->shouldReceive('getConnection')->once()->andReturn($connection);

		$connection->shouldReceive('table')->andReturn($builder);

		$builder->shouldReceive('count')->once()->andReturn(0);

		$builder->shouldReceive('getConnection')->once()->andReturn($connection);

		$connection->shouldReceive('transaction')->once();

		$builder->shouldReceive('max')->once();

		$builder->shouldReceive('select->where->orderBy->limit->get')->once()->andReturn([]);

		$this->processor->run();
	}
}
