<?php

return [
    'db.options' => [
        'driver' => 'pdo_mysql',
        'charset' => 'utf8',
        'host' => 'chillter_mariadb',
        'port' => '3306',
        'dbname' => 'chillter',
        'user' => 'chillter',
        'password' => 'chillter',
    ],
    'onesignal.options' => [
        'application_id' => 'ThisIdIsNoSecret',
        'key' => 'ThisKeyIsNoSecret'
    ],
    'upload.directory' => "/images/",
    'entry_point_url' => 'http://127.0.0.1:30080/api',
    'debug' => true,
];
