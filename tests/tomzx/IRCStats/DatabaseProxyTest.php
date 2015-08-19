<?php

namespace tomzx\IRCStats\Test;

use Mockery as m;
use tomzx\IRCStats\DatabaseProxy;

class DatabaseProxyTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \tomzx\IRCStats\DatabaseProxy
	 */
	protected $databaseProxy;
	/**
	 * @var \Mockery\MockInterface
	 */
	protected $capsule;
	/**
	 * @var \Mockery\MockInterface
	 */
	protected $databaseSchema;

	public function setUp()
	{
		$this->capsule = m::mock('\Illuminate\Database\Capsule\Manager');
		$this->databaseSchema = m::mock('\tomzx\IRCStats\DatabaseSchema');
		$this->databaseProxy = new DatabaseProxy([]);
		$this->databaseProxy->setCapsule($this->capsule);
		$this->databaseProxy->setDatabaseSchema($this->databaseSchema);
	}

	public function tearDown()
	{
		$this->databaseProxy = null;
		m::close();
	}

	public function testItBootsTheDatabaseOnGetDatabase()
	{
		$connection = m::mock('\Illuminate\Database\Connection');

		$this->capsule->shouldReceive('connection')->times(3)->andReturn($connection);

		$this->databaseSchema->shouldReceive('initialize')->once();

		$connection->shouldReceive('statement')->times(3);

		$connection->shouldReceive('disconnect')->once();

		$actual = $this->databaseProxy->getConnection();
		$this->assertSame($connection, $actual);
	}
}
