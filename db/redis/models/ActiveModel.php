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

    protected static $errors = [];

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

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return Inflector::camel2id(StringHelper::basename(get_called_class()), '_');
    }

    public static function count($params = [])
    {
        throw new NotSupportedException();
    }

    public static function one($params = [])
    {
        throw new NotSupportedException();
    }

    public static function all($params = [], $limit = 10, $page = 0)
    {
        throw new NotSupportedException();
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

    public function save($runValidation = true, $attributeNames = NULL)
    {
        return parent::save($runValidation, $attributeNames);
    }

    public function insert($runValidation = true, $attributes = null)
    {
        return parent::insert($runValidation, $attributes);
    }

    public static function find()
    {
        return Yii::createObject(static::className());
    }

    public static function primaryKey()
    {
        return parent::primaryKey();
    }

    /**
     * Проверка наличия и соответствие типа ключа в RedisDB
     * 
     * @param string $table
     * @return boolean
     * @throws InvalidParamException
     */
    protected static function checkTable($table = '')
    {
        $table = (string)$table;

        if ('' === $table) {
            $message                 = \Yii::t('app', "Parameter 'table' cannot be blank.");
            static::$errors['table'] = $message;
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $db = static::getDb();
        if (false === $db->exists($table)) {
            $message                 = \Yii::t('app', "Table not exists: {table}!", ['table' => $table]);
            static::$errors['table'] = $message;
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        if (static::$type !== $db->type($table)) {
            $message                 = \Yii::t('app', "Incorrect type of table '{table}'! Must be sorted sets!", ['table' => $table]);
            static::$errors['table'] = $message;
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        return true;
    }

    public static function getModelErrors()
    {
        $return = null;
        if (static::$errors) {
            $return = static::$errors;
        }

        return $return;
    }

}
