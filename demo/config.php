<?php

return [
    'rabbitmq' => [
        'host' => getenv('DOCKER_INTERNAL_HOST'),
        'port' => '5672',
        'vhost' => '/',
        'login' => 'guest',
        'password' => 'guest',
    ]
];