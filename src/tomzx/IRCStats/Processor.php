<?php

namespace tomzx\IRCStats;

use Illuminate\Database\Connection;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Processor implements LoggerAwareInterface {
	/**
	 * @var \tomzx\IRCStats\DatabaseProxy
	 */
	protected $databaseProxy;
	/**
	 * @var \Psr\Log\LoggerInterface
	 */
	protected $logger;

	/**
	 * @param \tomzx\IRCStats\DatabaseProxy $databaseProxy
	 */
	public function __construct(DatabaseProxy $databaseProxy)
	{
		$this->databaseProxy = $databaseProxy;
		$this->logger = new NullLogger();
	}

	/**
	 * @return \Illuminate\Database\Connection
	 */
	protected function getDatabase()
	{
		return $this->databaseProxy->getConnection();
	}

	/**
	 * @param \Psr\Log\LoggerInterface $logger
	 * @return void
	 */
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
	}

	/**
	 * @return void
	 */
	public function run()
	{
		$db = $this->getDatabase();
		$this->initializeDictionary($db);
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

		$dictionary = null;
		$batchSize = 250;
		$currentId = $lastLogId;
		while (true) {
			$this->logger->debug('Processing id > '.$currentId.' (batch of '.$batchSize.')');
			$fetchStart = microtime(true);

			$logs = $this->getLogs($db, $currentId, $batchSize);

			$fetchDuration = microtime(true) - $fetchStart;

			if ( ! $logs) {
				// No more data available
				$this->logger->debug('End of data');
				break;
			}

			if ( ! $dictionary) {
				$dictionary = $this->loadDictionary($db);
			}

			$insertStart = microtime(true);
			$data = [];
			foreach ($logs as $log) {
				// TODO: Replace this with preg_split <tom@tomrochette.com>
				$words = explode(' ', $log->message);
				foreach ($words as $word) {
					// TODO: Support case insensitive <tom@tomrochette.com>
					if ( ! isset($dictionary[$word])) {
						//$this->logger->debug('Unknown word '.$word.PHP_EOL);
						continue;
					}

					$wordId = $dictionary[$word];

					$data[] = [
						'logs_id' => $log->id,
						// 'word'    => $word,
						'word_id' => $wordId,
					];
				}
			}

			$currentId = $log->id;

			$this->batchInsert($db->table('logs_words'), $data);
			$insertDuration = microtime(true) - $insertStart;
			$this->logger->debug('fetch: '.round($fetchDuration, 6).'s, insert: '.round($insertDuration, 6).'s');
		}
	}

	protected function initializeDictionary(Connection $db)
	{
		$dictionarySize = $db->table('words')->count();

		if ($dictionarySize > 0) {
			return;
		}

		$this->logger->info('Seeding words table...');
		$dictionarySeedStart = microtime(true);
		$dictionary = file(__DIR__ . '/../../../data/dictionary.txt');
		$data = [];
		foreach ($dictionary as $word) {
			$data[] = [
				'word' => trim($word),
			];
		}
		$this->batchInsert($db->table('words'), $data);

		$dictionarySeedDuration = microtime(true) - $dictionarySeedStart;
		$this->logger->info('Finished seeding words table in '.round($dictionarySeedDuration, 6).'s');
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @return array
	 */
	protected function getDictionary(Connection $db)
	{
		return $db->table('words')
			->select('id', 'word')
			->lists('id', 'word');
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @return array
	 */
	protected function loadDictionary(Connection $db)
	{
		$dictionaryStart = microtime(true);
		$dictionary = $this->getDictionary($db);
		$dictionaryDuration = microtime(true) - $dictionaryStart;
		$this->logger->info('Dictionary loaded in ' . round($dictionaryDuration, 6) . 's');
		return $dictionary;
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
	 * @param \Illuminate\Database\Query\Builder $builder
	 * @param  array                             $data
	 */
	protected function batchInsert(\Illuminate\Database\Query\Builder $builder, array $data)
	{
		$builder->getConnection()->transaction(function () use ($builder, $data) {
			// Batch in group of 250 entries to prevent "Too many SQL variables" SQL error
			$insertBatchSize = 250;
			$insertBatchCount = ceil(count($data) / $insertBatchSize);
			for ($i = 0; $i < $insertBatchCount; ++$i) {
				$insertedData = array_slice($data, $i * $insertBatchSize, $insertBatchSize);

				$builder->insert($insertedData);
			}
		});
	}
}
