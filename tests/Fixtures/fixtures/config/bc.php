<?php

use Jackalope\Factory;

$container->loadFromExtension('doctrine_phpcr', [
    'session' => [
        'backend' => [
            'type' => 'doctrinedbal',
            'parameters' => [
                'jackalope.factory' => Factory::class,
            ],
            'check_login_on_server' => true,
            'disable_stream_wrapper' => true,
            'disable_transactions' => true,
        ],
        'workspace' => 'default',
        'username' => 'admin',
        'password' => 'admin',
    ],
]);
