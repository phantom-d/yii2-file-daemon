<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\Model;
use yii\base\NotSupportedException;

/**
 * DataModel
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class DataModel extends Model implements \phantomd\filedaemon\db\DataInterface
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
        if (empty(static::$db)) {
            static::$db = \Yii::$app->get('filedaemon-' . strtolower(static::shortClassName()));
        }

        return static::$db;
    }

    /**
     * Get classname without namespace
     *
     * @return string
     */
    public static function shortClassName()
    {
        $classname = static::className();

        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            $classname = $matches[1];
        }

        return $classname;
    }

    public static function getAll()
    {
        throw new NotSupportedException();
    }

    public static function getCount()
    {
        throw new NotSupportedException();
    }

    public static function getData($remove = false)
    {
        throw new NotSupportedException();
    }

    public static function getJobs()
    {
        throw new NotSupportedException();
    }

    public static function getOne($remove = false)
    {
        throw new NotSupportedException();
    }

    public static function removeJobData()
    {
        throw new NotSupportedException();
    }

    public static function renameJob($source, $target)
    {
        throw new NotSupportedException();
    }

    public static function setData($params, $arc = false)
    {
        throw new NotSupportedException();
    }

}
