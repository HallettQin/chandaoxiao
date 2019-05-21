<?php

/**
 * ECTouch E-Commerce Project
 *
 * @package  ECTouch
 * @author   carson <wanganlin@ecmoban.com>
 */

$db = require __DIR__ . '/config/database.php';
return [
  'paths' => [
      'migrations' => 'database/migrations',
      'seeds' => 'database/seeds'
  ],
  'environments' => [
      'default_migration_table' => $db['db_prefix'] . 'migration',
      'default_database' => 'production',
      'production' => [
        'adapter' => $db['db_type'],
        'host' => $db['db_host'],
        'name' => $db['db_name'],
        'user' => $db['db_user'],
        'pass' => $db['db_pwd'],
        'port' => $db['db_port'],
        'charset' => $db['db_charset'],
        'table_prefix' => $db['db_prefix'],
      ]
  ],
  'version_order' => 'creation'
];