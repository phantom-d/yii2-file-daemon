<?php

/**
 * Daemons configuration
 *
 * Watcher daemon configuration:
 *
 * ```php
 *  'watcher' => [
 *      'multi-instance'  => true,
 *      'child-processes' => 100,
 *      'sleep'           => 60,
 *      'daemons'         => [
 *          [
 *              [
 *                  'className'  => 'OneDaemonController',
 *                  'enabled'    => true
 *              ],
 *              [
 *                  'className'  => 'AnotherDaemonController',
 *                  'enabled'    => false
 *              ],
 *          ]
 *      ],
 *  ],
 * ```
 */
return [
    'watcher'     => [
        'multi-instance'  => true,
        'child-processes' => 100,
        'sleep'           => 60,
        'daemons'         => [
            [
                'className' => 'FileServerDaemonController',
                'enabled'   => true
            ],
        ],
    ],
    'file-server' => [
        'component'       => 'file',
        'archive'         => true,
        'callback'        => [
            'default' => 'http://localhost/daemon/callback/',
        ],
        'max-threads'     => 5,
        'max-count'       => 100,
        'multi-instance'  => true,
        'child-processes' => 450,
        'sleep'           => 5,
        'extension'       => '',
        'directories'     => [
            'web'    => '/uploads/files',
            'source' => '@app/temp',
            'target' => '@app',
        ],
        'db'              => [
            'default' => [
                'driver' => 'redis',
                'db'     => [
                    'class'    => 'yii\redis\Connection',
                    'hostname' => 'localhost',
                    'port'     => 6379,
                    'database' => 0,
                ]
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
