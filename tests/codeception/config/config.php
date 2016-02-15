<?php

/**
 * Application configuration shared by all test types
 */
return [
    'language'      => 'ru-RU',
    'controllerMap' => [
        'fixture' => [
            'class'           => 'yii\faker\FixtureController',
            'fixtureDataPath' => '@tests/codeception/fixtures',
            'templatePath'    => '@tests/codeception/templates',
            'namespace'       => 'tests\codeception\fixtures',
        ],
    ],
    'components'    => [
        'mailer'     => [
            'useFileTransport' => true,
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
    ],
];
