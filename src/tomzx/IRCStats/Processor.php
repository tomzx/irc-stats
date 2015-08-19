<?php

namespace tomzx\IRCStats;

use Illuminate\Database\Connection;

class Processor {
	/**
	 * @var \tomzx\IRCStats\DatabaseProxy
	 */
	protected $databaseProxy;

	/**
	 * @param \tomzx\IRCStats\DatabaseProxy $databaseProxy
	 */
	public function __construct(DatabaseProxy $databaseProxy)
	{
		$this->databaseProxy = $databaseProxy;
	}

	/**
	 * @return \Illuminate\Database\Connection
	 */
	protected function getDatabase()
	{
		return $this->databaseProxy->getConnection();
	}

	public function run()
	{
		$db = $this->getDatabase();
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
			//echo 'Processing id > '.$currentId.' (batch of '.$batchSize.')';
			$fetchStart = microtime(true);

			$logs = $this->getLogs($db, $currentId, $batchSize);

			$fetchDuration = microtime(true) - $fetchStart;

			if ( ! $logs) {
				// No more data available
				//echo ' End of data'.PHP_EOL;
				break;
			}

			$insertStart = microtime(true);
			$data = [];
			foreach ($logs as $log) {
				$words = explode(' ', $log['message']);
				foreach ($words as $word) {
					$data[] = [
						'logs_id' => $log['id'],
						'word'    => $word,
					];
				}
			}

			$currentId = $log['id'];

			$this->batchInsert($db, $data);
			$insertDuration = microtime(true) - $insertStart;
			//echo ' fetch: '.round($fetchDuration, 6).'s, insert: '.round($insertDuration, 6).'s'.PHP_EOL;
		}
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @param int                             $currentId
	 * @param int                             $batchSize
	 * @return array
	 */
	protected function getLogs(Connection $db, $currentId, $batchSize)
	{
		return $db->table('logs')
			->select('id', 'message')
			->where('id', '>', $currentId)
			->orderBy('id', 'asc')
			->limit($batchSize)
			->get();
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @param  array                          $data
	 * @throws \Exception
	 */
	protected function batchInsert(Connection $db, array $data)
	{
		$db->transaction(function () use ($db, $data) {
			// Batch in group of 250 entries to prevent "Too many SQL variables" SQL error
			$insertBatchSize = 250;
			$insertBatchCount = ceil(count($data) / $insertBatchSize);
			for ($i = 0; $i < $insertBatchCount; ++$i) {
				$insertedData = array_slice($data, $i * $insertBatchSize, $insertBatchSize);

				$db->table('logs_words')
					->insert($insertedData);
			}
		});
	}
}
