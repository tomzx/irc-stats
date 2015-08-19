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
				//				$table->unique['server'];
			},
			'channels'   => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('network_id')->unsigned();
				$table->string('channel');
				//				$table->unique(['network_id', 'channel']);
			},
			'nicks'      => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('channel_id')->unsigned();
				$table->string('nick');
				//				$table->unique(['channel_id', 'nick']);
			},
			'logs'       => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('channel_id')->unsigned();
				$table->integer('nick_id')->unsigned();
				$table->timestamp('timestamp');
				$table->text('message');
			},
			'logs_words' => function (Blueprint $table) {
				$table->increments('id');
				$table->integer('logs_id')->unsigned();
				$table->string('word');
				//				 $table->index(['word']);
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
