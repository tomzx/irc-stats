<?php

namespace tomzx\IRCStats;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

class DatabaseSchema {
	/**
	 * @param \Illuminate\Database\Connection $db
	 */
	public function initialize(Connection $db)
	{
		$builder = $db->getSchemaBuilder();

		$tables = [
			'networks'   => function (Blueprint $table) {
				$table->increments('id');
				$table->string('server');
				$table->unique(['server']);
			},
			'channels'   => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('network_id')->unsigned();
				$table->string('channel');
				$table->unique(['network_id', 'channel']);

				$table->foreign('network_id')->references('id')->on('networks');
			},
			'nicks'      => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('network_id')->unsigned();
				$table->string('nick');
				$table->unique(['network_id', 'nick']);

				$table->foreign('network_id')->references('id')->on('networks');
			},
			'logs'       => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('channel_id')->unsigned();
				$table->integer('nick_id')->unsigned();
				$table->timestamp('timestamp');
				$table->text('message');

				$table->foreign('channel_id')->references('id')->on('channels');
				$table->foreign('nick_id')->references('id')->on('nicks');
			},
			// Generated data tables based on the content of the previous tables
//			'dictionary' => function (Blueprint $table) {
//				$table->increments('id');
//				$table->string('word', 40);
//			},
			'words'      => function (Blueprint $table) {
				$table->increments('id');
				$table->string('word', 40);
			},
			'logs_words' => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('logs_id')->unsigned();
				$table->integer('word_id')->unsigned();

				$table->foreign('logs_id')->references('id')->on('logs');
				$table->foreign('word_id')->references('id')->on('words');
			},
		];

		foreach ($tables as $table => $callback) {
			if ($builder->hasTable($table)) {
				continue;
			}
			$builder->create($table, $callback);
		}
	}
}
