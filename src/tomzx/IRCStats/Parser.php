<?php

namespace tomzx\IRCStats;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

class Parser
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
	 * @return void
	 */
	public function __construct(array $configuration)
	{
		$this->configuration = $configuration;
	}

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
	 * @param array $line
	 * @return void
	 */
	public function parseLine(array $line)
	{
		$db = $this->getDatabase();

		$this->setupDatabase($db);

		$server = $line['server'];
		$channel = $line['channel'];
		$nick = $line['nick'];
		$timestamp = $line['timestamp'];
		$message = $line['message'];

		$networkId = $this->getNetworkId($db, $server);
		$channelId = $this->getChannelId($db, $networkId, $channel);
		$nickId = $this->getNickId($db, $channelId, $nick);

		$db->table('logs')->insert([
			'channel_id' => $channelId,
			'nick_id' => $nickId,
			'timestamp' => $timestamp,
			'message' => $message,
		]);

		$db->disconnect();
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @return void
	 */
	protected function setupDatabase(Connection $db)
	{
		// TODO: server should be unique
		$db->statement('CREATE TABLE IF NOT EXISTS networks (
			id INTEGER primary key autoincrement,
			server VARCHAR(255)
		);');

		// TODO: network_id+channel should be unique
		$db->statement('CREATE TABLE IF NOT EXISTS channels (
			id INTEGER primary key autoincrement,
			network_id INTEGER,
			channel VARCHAR(255)
		);');

		// TODO: channel_id+nick should be unique
		$db->statement('CREATE TABLE IF NOT EXISTS nicks (
			id INTEGER primary key autoincrement,
			channel_id INTEGER,
			nick VARCHAR(255)
		);');

		$db->statement('CREATE TABLE IF NOT EXISTS logs (
			id INTEGER primary key autoincrement,
			channel_id INTEGER,
			nick_id INTEGER,
			timestamp INTEGER,
			message TEXT
		);');
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @param string $server
	 * @return int
	 */
	protected function getNetworkId(Connection $db, $server)
	{
		$targetNetwork = $db->table('networks')
			->select('id')
			->where('server', '=', $server)
			->first();
		if ($targetNetwork) {
			return $targetNetwork['id'];
		} else {
			return $db->table('networks')->insertGetId([
				'server' => $server,
			]);
		}
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @param int $networkId
	 * @param string $channel
	 * @return int
	 */
	protected function getChannelId(Connection $db, $networkId, $channel)
	{
		$targetChannel = $db->table('channels')
			->select('id')
			->where('network_id', '=', $networkId)
			->where('channel', '=', $channel)
			->first();
		if ($targetChannel) {
			return $targetChannel['id'];
		} else {
			return $db->table('channels')->insertGetId([
				'network_id' => $networkId,
				'channel' => $channel,
			]);
		}
	}

	/**
	 * @param \Illuminate\Database\Connection $db
	 * @param int $channelId
	 * @param string $nick
	 * @return int
	 */
	protected function getNickId(Connection $db, $channelId, $nick)
	{
		$targetNick = $db->table('nicks')
			->select('id')
			->where('channel_id', '=', $channelId)
			->where('nick', '=', $nick)
			->first();
		if ($targetNick) {
			return $targetNick['id'];
		} else {
			return $db->table('nicks')->insertGetId([
				'channel_id' => $channelId,
				'nick' => $nick,
			]);
		}
	}
}