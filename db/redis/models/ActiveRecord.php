<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\NotSupportedException;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * ActiveRecord
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class ActiveRecord extends \yii\redis\ActiveRecord implements \phantomd\filedaemon\db\ActiveInterface
{

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return \Yii::$app->get('filedaemon_' . Inflector::camel2id(StringHelper::basename(get_called_class()), '_'));
    }

    public static function model($params = [])
    {
        $model = new static;
        if ($params) {
            $model->setAttributes($params);
        }
        return $model;
    }

    public static function count($params = [])
    {
        throw new NotSupportedException();
    }

    public static function one($params = [])
    {
        if (is_string($params) || isset($params[0])) {
            $params = [
                'id' => is_array($params) ? array_values($params) : $params,
            ];
        }

        $query = static::find();
        if ($params) {
            $query->andWhere($params);
        }
        return $query->one();
    }

    public static function all($params = [], $limit = 10, $page = 0)
    {
        if (is_string($params) || isset($params[0])) {
            $params = [
                'id' => is_array($params) ? array_values($params) : $params,
            ];
        }

        $query = static::find();
        if ($params) {
            $query->andWhere($params);
        }
        return $query->all();
    }

    public static function names($params = [])
    {
        throw new NotSupportedException();
    }

    public static function groups($params = [])
    {
        throw new NotSupportedException();
    }

    public function remove()
    {
        throw new NotSupportedException();
    }

    public function rename($params = [])
    {
        throw new NotSupportedException();
    }

}
