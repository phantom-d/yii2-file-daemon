<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\Model;

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
            static::$db = new \yii\redis\Connection(static::$dbConfig);
        }

        return static::$db;
    }

    public function getAll()
    {
        
    }

    public function getCount()
    {
        
    }

    public function getData($remove = false)
    {
        
    }

    public function getJobs($filter)
    {
        
    }

    public function getOne($remove = false)
    {
        
    }

    public function removeJobData()
    {
        
    }

    public function renameJob($source, $target)
    {
        
    }

    public function setData($params, $arc = false)
    {
        
    }

}
