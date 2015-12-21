<?php

namespace phantomd\filedaemon\db;

use Yii;
use yii\base\NotSupportedException;

class ActiveRecord extends \yii\redis\ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        $connection = \Yii::$app->get('redis' . strtolower(static::shortClassName()));
        if ($connection && $connection->isActive === false) {
            $connection->open();
        }

        return $connection;
    }

}
