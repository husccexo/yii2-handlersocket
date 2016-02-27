<?php

/**
 * This is the configuration file for the Yii2 unit tests.
 * You can override configuration values by creating a `config.local.php` file
 * and manipulate the `$config` variable.
 * For example to change MySQL username and password your `config.local.php` should
 * contain the following:
 *
<?php
$config['databases']['mysql']['username'] = 'yiitest';
$config['databases']['mysql']['password'] = 'changeme';

 */

$config = [
    'databases' => [
        'hs' => [
            'host' => 'localhost',
            'portRead' => 9998,
            'portWrite' => 9999,
            'secret' => null,
            'db' => 'tests',
        ],
    ],
];

if (is_file(__DIR__ . '/config-local.php')) {
    include(__DIR__ . '/config-local.php');
}

return $config;