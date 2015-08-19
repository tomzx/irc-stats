<?php

namespace tomzx\IRCStats;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

class DatabaseProxy {
	/**
	 * @var array
	 */
	protected $configuration = [];
	/**
	 * @var \Illuminate\Database\Capsule\Manager
	 */
	protected $capsule;
	/**
	 * @var \tomzx\IRCStats\DatabaseSchema
	 */
	protected $databaseSchema;
	/**
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * @param array $configuration
	 */
	public function __construct(array $configuration)
	{
		$this->configuration = $configuration;
	}

	public function __destruct()
	{
		if ($this->capsule) {
			$this->getConnection()->disconnect();
		}
	}

	/**
	 * @param \Illuminate\Database\Capsule\Manager $capsule
	 * @return $this
	 */
	public function setCapsule(Capsule $capsule)
	{
		$this->capsule = $capsule;

		return $this;
	}

	/**
	 * @return \Illuminate\Database\Capsule\Manager
	 */
	public function getCapsule()
	{
		if ( ! $this->capsule) {
			$this->capsule = new Capsule;
			$this->capsule->addConnection($this->configuration);
			$this->capsule->setAsGlobal();
		}

		return $this->capsule;
	}

	/**
	 * @return \tomzx\IRCStats\DatabaseSchema
	 */
	public function getDatabaseSchema()
	{
		if ( ! $this->databaseSchema) {
			$this->databaseSchema = new DatabaseSchema();
		}

		return $this->databaseSchema;
	}

	/**
	 * @param \tomzx\IRCStats\DatabaseSchema $databaseSchema
	 * @return $this
	 */
	public function setDatabaseSchema(DatabaseSchema $databaseSchema)
	{
		$this->databaseSchema = $databaseSchema;

		return $this;
	}

	/**
	 * @return \Illuminate\Database\Connection
	 */
	public function getConnection()
	{
		$this->bootDatabase();
		return $this->getCapsule()->connection();
	}

	protected function bootDatabase()
	{
		if ( ! $this->booted) {
			$this->booted = true;
			$db = $this->getCapsule()->connection();
			$this->configurePragma($db);
			$this->setupDatabase($db);
		}
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @return void
	 */
	protected function setupDatabase(Connection $db)
	{
		$this->getDatabaseSchema()->initialize($db);
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 */
	protected function configurePragma(Connection $db)
	{
		// Enable foreign keys for the current connection/file
		$db->statement('PRAGMA foreign_keys = ON;');
		// Create sqlite-journal in memory only (instead of creating disk files)
		$db->statement('PRAGMA journal_mode = MEMORY;');
		// Do not wait for OS after sending write commands
		$db->statement('PRAGMA synchronous = OFF;');
	}
}
