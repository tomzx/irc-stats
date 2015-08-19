<?php

namespace tomzx\IRCStats\Test;

use Mockery as m;
use tomzx\IRCStats\DatabaseSchema;

class DatabaseSchemaTest extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \tomzx\IRCStats\DatabaseSchema
	 */
	protected $databaseSchema;

	public function setUp()
	{
		$this->databaseSchema = new DatabaseSchema();
	}

	public function tearDown()
	{
		m::close();
	}

	public function testInitialize()
	{
		$connection = m::mock('\Illuminate\Database\Connection');
		$schemaBuilder = m::mock('\Illuminate\Database\Schema\Builder');

		$connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schemaBuilder);

		$schemaBuilder->shouldReceive('hasTable')->times(5)
			->shouldReceive('create')->times(5);

		$this->databaseSchema->initialize($connection);
	}
}
