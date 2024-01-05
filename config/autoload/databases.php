<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
$databases = [];
$databaseName = [
    'jkc_edu'
];

foreach($databaseName as $name){
    $_writeDbEnv = strtoupper($name).'_W_DB';
    $_readDbEnv = strtoupper($name).'_R_DB';
    $_writeDb = json_decode(env($_writeDbEnv),true);
    $_readDb = json_decode(env($_readDbEnv),true);

    $databases[$name] = [
        'driver' => 'mysql',
        'write' => [
            'host' => [$_writeDb['host']],
            'username' => $_writeDb['username'],
            'password' => $_writeDb['password'],
        ],
        'read' => [
            'host' => [$_readDb['host']],
            'username' => $_readDb['username'],
            'password' => $_readDb['password'],
        ],
        'sticky'    => false,
        'database' => $name,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'pool' => [
            'min_connections' => 10,
            'max_connections' => 100,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
            'max_idle_time' => (float) env('DB_MAX_IDLE_TIME', 60),
        ],
    ];
}

return $databases;
