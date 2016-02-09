<?php

return [
    'id'         => 'basic',
    'basePath'   => realpath(__DIR__ . '/../..'),
    'bootstrap'  => ['log'],
    'components' => [
        'request'      => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'FQ65MZmUFUQJvZzEmYbmJcYbygrxVTA9',
            'parsers'             => [
                'application/json' => 'yii\web\JsonParser',
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'default/error',
        ],
        'log'          => [
            'traceLevel'    => 3,
            'flushInterval' => 1,
            'targets'       => [
                [
                    'exportInterval' => 1,
                    'class'          => 'yii\log\FileTarget',
                    'levels'         => ['error', 'warning', 'info', 'profile'],
                    'logVars'        => ['_GET', '_POST', '_COOKIE', '_SESSION', '_SERVER'],
                ],
            ],
        ],
        'urlManager'   => [
            'class'               => '\yii\web\UrlManager',
            'enablePrettyUrl'     => true,
            'showScriptName'      => false,
            'enableStrictParsing' => true,
            'suffix'              => '/',
            'rules'               => [
                '/'                                                                                            => 'default/index',
                '<controller:[-_0-9a-zA-Z]+>/<action:[-_0-9a-zA-Z]+>/<id:[-_0-9a-zA-Z]+>/<key:[-_0-9a-zA-Z]+>' => '<controller>/<action>',
                '<controller:[-_0-9a-zA-Z]+>/<action:[-_0-9a-zA-Z]+>/<id:[-_0-9a-zA-Z]+>'                      => '<controller>/<action>',
                '<module:[-_0-9a-zA-Z]+>/<controller:[-_0-9a-zA-Z]+>/<action:[-_0-9a-zA-Z]+>'                  => '<module>/<controller>/<action>',
                '<controller:[-_0-9a-zA-Z]+>/<action:[-_0-9a-zA-Z]+>'                                          => '<controller>/<action>',
                '<controller:[-_0-9a-zA-Z]+>/'                                                                 => '<controller>/index',
                [
                    'class'      => 'yii\rest\UrlRule',
                    'controller' => ['daemon'],
                    'except'     => ['delete', 'update', 'view'],
                ],
            ],
        ],
    ],
    'params'     => require(__DIR__ . '/params.php'),
    'timeZone'   => 'Europe/Moscow',
];

