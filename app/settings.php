<?php
return [
    'settings' => [
        // Slim Settings
        'determineRouteBeforeAppMiddleware' => false,
        'displayErrorDetails'               => true,

        // View settings
        'view'                              => [
            'template_path' => __DIR__ . '/templates',
            'twig'          => [
                'cache'       => __DIR__ . '/../cache/twig',
                'debug'       => true,
                'auto_reload' => true,
            ],
        ],

        // monolog settings
        'logger'                            => [
            'name' => 'app',
            'path' => __DIR__ . '/../log/app.log',
        ],

        'db'                                => [
            'name' => 'my_deploy',
            'host' => 'localhost',
            'user' => 'root',
            'pass' => 'root',
        ],
        'binpaths'                          => [
            'git_path'      => '/usr/bin/git',
            'composer_path' => '/usr/local/bin/composer.phar',
        ],


    ],
];
