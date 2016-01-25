<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\NotSupportedException;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * DataModel
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class ActiveModel extends \yii\db\BaseActiveRecord implements \phantomd\filedaemon\db\ActiveInterface
{

    protected static $dbConfig = [
        'hostname' => 'localhost',
        'port'     => 6379,
        'database' => 0,
    ];

    protected static $db = null;

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return \Yii::$app->get('filedaemon_' . Inflector::camel2id(StringHelper::basename(get_called_class()), '_'));
    }

    /**
     * Declares the name of the database table associated with this AR class.
     * By default this method returns the class name as the table name by calling [[Inflector::camel2id()]]
     * if the table is not named after this convention.
     * @return string the table name
     */
    public static function tableName()
    {
        return Inflector::camel2id(StringHelper::basename(get_called_class()), '_');
    }

    public static function getAll($params = [])
    {
        throw new NotSupportedException();
    }

    public static function getCount($params = [])
    {
        throw new NotSupportedException();
    }

    public static function getData($params = [])
    {
        throw new NotSupportedException();
    }

    public static function getJobs()
    {
        throw new NotSupportedException();
    }

    public static function getOne($params = [])
    {
        throw new NotSupportedException();
    }

    public static function removeJobData()
    {
        throw new NotSupportedException();
    }

    public static function renameJob($params = [])
    {
        throw new NotSupportedException();
    }

    public static function setData($params = [])
    {
        throw new NotSupportedException();
    }

    public function insert($runValidation = true, $attributes = null)
    {
        throw new NotSupportedException();
    }

    public static function find()
    {
        return Yii::createObject(static::className());
    }

    public static function primaryKey()
    {
        throw new NotSupportedException();
    }

    public static function getGroups($params = [])
    {
        throw new NotSupportedException();
    }

    public static function getTables($params = [])
    {
        throw new NotSupportedException();
    }

}
