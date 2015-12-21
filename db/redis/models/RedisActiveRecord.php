<?php

namespace app\components\filedaemon\db\redis\models;

use Yii;

class RedisActiveRecord extends \yii\redis\ActiveRecord
{

    /**
     * @var integer Количество миллисекунд между повторами на запрос
     */
    protected static $sleepTry = 1000;

    public static function getDb()
    {
        $connection = \Yii::$app->get('redis' . strtolower(self::shortClassName()));
        if ($connection && $connection->isActive === false) {
            $connection->open();
        }

        return $connection;
    }

    /**
     * Get classname without namespace
     *
     * @return string
     */
    public function shortClassName()
    {
        $classname = self::className();

        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            $classname = $matches[1];
        }

        return $classname;
    }

}
