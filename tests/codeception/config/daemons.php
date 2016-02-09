<?php

ini_set('precision', 20);

/**
 * Конфигурация демонов
 * @todo Реализовать обработку callback из конфигурации
 * @todo Реализовать обработку method в настройках команд из конфигурации
 */
return [
    'watcher'      => [
        'multi-instance'  => true,
        'child-processes' => 100,
        'sleep'           => 60,
        'daemons'         => [
            [
                'className' => 'ImageServerDaemonController',
                'enabled'   => true
            ],
        ],
    ],
    'image-server' => [
        'component'       => 'image',
        'archive'         => true,
        'callback'        => [
            'default' => 'http://localhost/daemon/callback/',
        ],
        'max-threads'     => 5,
        'max-count'       => 100,
        'multi-instance'  => true,
        'child-processes' => 450,
        'sleep'           => 5,
        'extension'       => 'jpg',
        'directories'     => [
            'web'    => '/uploads/images',
            'source' => '@app/temp',
            'target' => '@app/..',
        ],
        'commands'        => [
            'normal'       => [
                'id' => 0,
            ],
            'is-main'      => [
                'id'      => 1,
                'targets' => [
                    'main' => [
                        'width'  => 50,
                        'height' => 50,
                        'suffix' => 'tbl',
                        'crop'   => true,
                    ],
                ],
            ],
            'complex-list' => [
                'id' => 2,
            ],
            'complex'      => [
                'id'      => 3,
                'method'  => [
                    'merge' => false,
                ],
                'targets' => [
                    'complex' => [
                        'width'  => 460,
                        'height' => 170,
                        'suffix' => 'complex',
                        'crop'   => true,
                    ],
                ],
            ],
            'logo'         => [
                'id'      => 4,
                'method'  => [
                    'merge' => false,
                ],
                'targets' => [
                    'logo' => [
                        'width'  => 100,
                        'height' => 25,
                        'suffix' => 'logo',
                        'crop'   => true,
                    ],
                ],
            ],
        ],
        'quality'         => 75,
        'targets'         => [
            'big'   => [
                'width'  => 600,
                'height' => 450,
                'suffix' => 'big',
            ],
            'promo' => [
                'width'  => 120,
                'height' => 100,
                'suffix' => 'promo',
                'crop'   => true,
            ],
            'm'     => [
                'width'  => 99,
                'height' => 74,
                'suffix' => 'm',
                'crop'   => true,
            ],
            'fav'   => [
                'width'  => 80,
                'height' => 80,
                'suffix' => 'fav',
                'crop'   => true,
            ],
        ],
        'db'              => [
            'default' => [
                'driver' => 'redis',
                'db'     => require(__DIR__ . '/redis.php')
            ],
            'config'  => [],
            'merge'   => [
                'source'    => [
                    'db' => [
                        'database' => 0,
                    ],
                ],
                'result'    => [
                    'db' => [
                        'database' => 1,
                    ],
                ],
                'arcresult' => [
                    'db' => [
                        'database' => 2,
                    ],
                ],
                'jobs'      => [
                    'db' => [
                        'database' => 2,
                    ],
                ],
            ],
        ],
    ],
];
