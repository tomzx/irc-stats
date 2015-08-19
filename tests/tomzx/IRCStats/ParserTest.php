<?php

namespace tomzx\IRCStats\Test;

use Mockery as m;
use tomzx\IRCStats\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var \Mockery\MockInterface
	 */
	protected $databaseProxy;
	/**
	 * @var \tomzx\IRCStats\Parser
	 */
	protected $parser;

	public function setUp()
	{
		$this->databaseProxy = m::mock('\tomzx\IRCStats\DatabaseProxy');
		$this->parser = new Parser($this->databaseProxy);
	}

	public function tearDown()
	{
		$this->parser = null;
		m::close();
	}

	public function testItCanParseAIRCLogLine()
	{
		$connection = m::mock('\Illuminate\Database\Connection');
		$query1 = m::mock('\Illuminate\Database\Query\Builder');
		$query2 = m::mock('\Illuminate\Database\Query\Builder');
		$query3 = m::mock('\Illuminate\Database\Query\Builder');
		$query4 = m::mock('\Illuminate\Database\Query\Builder');

		$line = [
			'server' => 'server',
			'channel' => 'channel',
			'nick' => 'nick',
			'timestamp' => 'timestamp',
			'message' => 'message',
		];

		$this->databaseProxy->shouldReceive('getConnection')->once()->andReturn($connection);

		// network
		$connection->shouldReceive('table')->once()->andReturn($query1);
		$query1->shouldReceive('select->where->first')->once()->andReturn(['id' => 1]);
		// channel
		$connection->shouldReceive('table')->once()->andReturn($query2);
		$query2->shouldReceive('select->where->where->first')->once()->andReturn(['id' => 2]);
		// nick
		$connection->shouldReceive('table')->once()->andReturn($query3);
		$query3->shouldReceive('select->where->where->first')->once()->andReturn(['id' => 3]);
		// log
		$connection->shouldReceive('table')->once()->andReturn($query4);
		$query4->shouldReceive('insert')->once();

		$this->parser->parseLine($line);
	}
}
