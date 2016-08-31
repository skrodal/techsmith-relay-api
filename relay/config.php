<?php

	use Relay\Conf\Config;

	// For large mongo-requests...
	// ini_set('memory_limit','512M');
	//
	$config_root = '/var/www/etc/techsmith-relay/';

	Config::add(
		[
			'router'     => [
				// Remember to update .htacces as well:
				'api_base_path' => '/api/techsmith-relay'
			],
			'auth'       => [
				'dataporten'             => $config_root . 'dataporten_config.js',
				'relay_sql'              => $config_root . 'relay_config.js',
				'relay_mongo'            => $config_root . 'mongodb_config.js',
				'relay_mysql_presdelete' => $config_root . 'relay_mysql_presdelete_config.js'
			],
			'utils'      => [
				'debug' => false
			],
			'screencast' => [
				'base_url'      => 'https://screencast.uninett.no',
				'employee_url'  => 'https://screencast.uninett.no/relay/ansatt/',
				'student_url'   => 'https://screencast.uninett.no/relay/student/',
				'root_path'     => '/var/www/mnt/relaymedia_cache/',                         // Due to slow file access on MooseFS
				'employee_path' => '/var/www/mnt/relaymedia_cache/ansatt/',                  // we read a cached local directory tree
				'student_path'  => '/var/www/mnt/relaymedia_cache/student/',                 // (pushed with rsync daily) containing XML files only
				'delete_list'   => '/var/www/mnt/relaymedia_cache/relaymedia_deletelist'     // NOT IN USE
			]
		]);

