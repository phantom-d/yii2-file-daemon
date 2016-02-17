<?php

return [
    'id'                  => 'basic-console',
    'basePath'            => realpath(__DIR__ . '/../..'),
    'bootstrap'           => ['log'],
    'controllerNamespace' => 'app\console',
    'components'          => [
        'log' => [
            'traceLevel'    => 3,
            'flushInterval' => 1,
            'targets'       => [
                [
                    'class'          => 'yii\log\FileTarget',
                    'levels'         => ['error', 'warning', 'info',],
                    'exportInterval' => 1,
                ],
            ],
        ],
    ],
    'params'              => require(__DIR__ . '/params.php'),
    'timeZone'            => 'Europe/Moscow',
];
