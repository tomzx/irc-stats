<?php

namespace tomzx\IRCStats;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Inserter implements LoggerAwareInterface
{
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


	public function insert($server, $channel, $nick, $message, $timestamp)
	{
		$targetNetwork = $this->insertServer($server);
		$targetChannel = $this->insertChannel($targetNetwork, $channel);
		$targetNick = $this->insertNick($targetNetwork, $nick);
		$this->insertLog($targetChannel, $targetNick, $message, $timestamp);
	}

	protected function insertServer($server)
	{
		$db = $this->getDatabase();
		$targetNetwork = $db->table('networks')->select('id')->where('server', '=', $server)->first();
		if ($targetNetwork) {
			return $targetNetwork->id;
		}

		return $db->table('networks')->insertGetId([
			'server' => $server,
		]);
	}

	protected function insertChannel($targetNetwork, $channel)
	{
		$db = $this->getDatabase();
		$targetChannel = $db->table('channels')->select('id')->where('network_id', '=', $targetNetwork)->where('channel', '=', $channel)->first();
		if ($targetChannel) {
			return $targetChannel->id;
		}

		return $db->table('channels')->insertGetId([
			'network_id' => $targetNetwork,
			'channel' => $channel,
		]);
	}

	protected function insertNick($targetNetwork, $nick)
	{
		$db = $this->getDatabase();
		$targetNick = $db->table('nicks')->select('id')->where('network_id', '=', $targetNetwork)->where('nick', '=', $nick)->first();
		if ($targetNick) {
			return $targetNick->id;
		}

		return $db->table('nicks')->insertGetId([
			'network_id' => $targetNetwork,
			'nick' => $nick,
		]);
	}

	public function insertLog($targetChannel, $targetNick, $timestamp, $message)
	{
		$db = $this->getDatabase();
		$db->table('logs')->insert([
			'channel_id' => $targetChannel,
			'nick_id' => $targetNick,
			'timestamp' => date('Y-m-d H:i:s', $timestamp),
			'message' => $message,
		]);
	}
}
