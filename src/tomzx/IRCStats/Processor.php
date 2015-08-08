<?php

namespace tomzx\IRCStats;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

class Processor
{
	/**
	 * @var array
	 */
	protected $configuration = [];
	/**
	 * @var \Illuminate\Database\Capsule\Manager
	 */
	protected $capsule;

	/**
	 * @param array $configuration
	 */
	public function __construct(array $configuration)
	{
		$this->configuration = $configuration;
	}

	/**
	 * @param \Illuminate\Database\Capsule\Manager $capsule
	 */
	public function setCapsule(Capsule $capsule)
	{
		$this->capsule = $capsule;
	}

	/**
	 * @return \Illuminate\Database\Connection
	 */
	protected function getDatabase()
	{
		if ( ! $this->capsule) {
			$this->capsule = new Capsule;
			$this->capsule->addConnection($this->configuration);
			$this->capsule->setAsGlobal();
		}

		return $this->capsule->connection();
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

	/**
	 * @param \Illuminate\Database\Connection $db
	 */
	protected function setupDatabase(Connection $db)
	{
		$databaseSchema = new DatabaseSchema();
		$databaseSchema->initialize($db);
	}

	public function run()
	{
		$db = $this->getDatabase();
		$this->configurePragma($db);
		$this->setupDatabase($db);
		$this->generateLogsWords($db);
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @throws \Exception
	 */
	protected function generateLogsWords(Connection $db)
	{
		// Find last processed logs id
		$lastLogId = (int)$db->table('logs_words')->max('logs_id');

		$batchSize = 250;
		$currentId = $lastLogId;
		while (true) {
			echo 'Processing id > '.$currentId.' (batch of '.$batchSize.')';
			$fetchStart = microtime(true);
			$logs = $db->table('logs')
				->select('id', 'message')
				->where('id', '>', $currentId)
				->orderBy('id', 'asc')
				->limit($batchSize)
				->get();

			$fetchDuration = microtime(true) - $fetchStart;

			if ( ! $logs) {
				// No more data available
				echo ' End of data'.PHP_EOL;
				break;
			}

			$insertStart = microtime(true);
			$data = [];
			foreach ($logs as $log) {
				$words = explode(' ', $log['message']);
				foreach ($words as $word) {
					$data[] = [
						'logs_id' => $log['id'],
						'word' => $word,
					];
				}
			}

			$currentId = $log['id'];

			$db->transaction(function() use ($db, $data) {
				// Batch in group of 250 entries to prevent "Too many SQL variables" SQL error
				$insertBatchSize = 250;
				$insertBatchCount = ceil(count($data) / $insertBatchSize);
				for ($i = 0; $i < $insertBatchCount; ++$i) {
					$insertedData = array_slice($data, $i*$insertBatchSize, $insertBatchSize);

					$db->table('logs_words')
						->insert($insertedData);
				}
			});
			$insertDuration = microtime(true) - $insertStart;
			echo ' fetch: '.round($fetchDuration, 6).'s, insert: '.round($insertDuration, 6).'s'.PHP_EOL;
		}
	}
}
