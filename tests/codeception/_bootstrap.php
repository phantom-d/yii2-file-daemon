<?php

error_reporting(-1);

define('YII_ENABLE_ERROR_HANDLER', false);

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require_once __DIR__ . implode(DIRECTORY_SEPARATOR, ['', '..', '..', 'vendor', 'autoload.php']);
require_once __DIR__ . implode(DIRECTORY_SEPARATOR, ['', '..', '..', 'vendor', 'yiisoft', 'yii2', 'Yii.php']);

Yii::setAlias('@tests', dirname(__DIR__));
Yii::setAlias('@data', __DIR__ . DIRECTORY_SEPARATOR . '_data');
