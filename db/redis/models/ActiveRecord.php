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

    public static function getAll($params = array())
    {
        throw new NotSupportedException();
    }

    public static function getCount($params = array())
    {
        throw new NotSupportedException();
    }

    public static function getData($params = array())
    {
        throw new NotSupportedException();
    }

    public static function getJobs()
    {
        throw new NotSupportedException();
    }

    public static function getOne($params = array())
    {
        throw new NotSupportedException();
    }

    public static function removeJobData()
    {
        throw new NotSupportedException();
    }

    public static function renameJob($params = array())
    {
        throw new NotSupportedException();
    }

    public static function setData($params = array())
    {
        throw new NotSupportedException();
    }

}
