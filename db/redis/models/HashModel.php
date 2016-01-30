<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\InvalidParamException;

/**
 * HashModel
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class HashModel extends ActiveModel
{

    protected static $type = 'hash';

    /**
     * @inheritdoc
     */
    public static function count($params = [])
    {
        $return = null;
        $model  = static::model();

        if (is_array($params)) {
            $params = [
                'name' => isset($params['name']) ? (string)$params['name'] : $model->tableName,
            ];
        } else {
            $params = [
                'name' => $params ? (string)$params : $model->tableName,
            ];
        }

        if ($model->checkTable()) {
            $db     = static::getDb();
            $query  = [
                $params['name'], //
            ];
            $result = $db->executeCommand('hlen', $query);
            if (false === is_null($result)) {
                $return = (int)$result;
            }
        }

        return $return;
    }

    /**
     * Получение одной записи
     *
     * @param string $params Наименование поля
     * @throws InvalidParamException
     * @return mixed
     */
    public static function one($params = '')
    {
        $return = null;
        $model  = static::model();

        if ($model->checkTable()) {
            $db    = static::getDb();
            $table = $model->tableName;

            $attributes = null;

            if ($params) {
                $params = (string)$params;
                $query  = [
                    $table,
                    $params,
                ];

                $result = $db->executeCommand('hget', $query);
                if ($result) {
                    $attributes = ['name' => $params, 'path' => $result];
                }
            } else {
                $query = [
                    $table, 0,
                    'COUNT', 1
                ];

                if ($result = $db->executeCommand('hscan', $query)) {
                    $attributes = ['name' => (string)$result[1][0], 'path' => (string)$result[1][1]];
                }
            }

            if ($attributes) {
                $model->setAttributes($attributes);
                $model->setIsNewRecord(false);
                $model->afterFind();

                $return = $model;
            }
        }

        return $return;
    }

    /**
     * Получение списка записей
     * 
     * @param string $params[source_id] Наименование ключа в RedisDB
     * @param string $params[fields] Массив наименований полей
     * @throws InvalidParamException
     * @return mixed
     */
    public static function all($params = [], $limit = 10, $page = 0)
    {
        if ($params) {
            $params = array_filter((array)$params);
        }

        $return = [];
        $model  = static::model();

        if ($model->checkTable()) {
            $db    = static::getDb();
            $table = $this->tableName;
            $query = [$table];

            if (empty($params)) {
                $offset = 0;
                if ((int)$limit) {
                    $limit = (int)$limit;
                    $page  = (int)$page;

                    $script = "local page = 0 local limit = {$limit} local cursor = 0 local elements = {} "
                        . "while true do "
                        . "local result = redis.pcall('HSCAN', '{$table}', cursor, 'COUNT', limit) "
                        . "local count  = table.getn(result[2])/2 cursor = result[1] "
                        . "if (page == {$page}) then elements = result[2] break end "
                        . "if (cursor == 0) then break end "
                        . "page = page + 1 "
                        . "end "
                        . "return elements";

                    $result = $db->executeCommand('eval', [$script, 0]);
                } else {
                    $result = $db->executeCommand('hgetall', $query);
                }

                if ($result) {
                    $key = 0;
                    while (isset($result[$key])) {
                        $row = clone $model;
                        $row->setAttributes(['name' => $result[$key++], 'path' => $result[$key++]]);
                        $row->setIsNewRecord(false);
                        $row->afterFind();

                        $return[] = $row;
                    }
                }
            } else {
                $query  = array_merge($query, $params);
                $result = $db->executeCommand('hmget', $query);

                if ($result) {
                    $result = array_combine($params, $result);
                    foreach ($result as $name => $path) {
                        if ($path) {
                            $row = clone $model;
                            $row->setAttributes(['name' => $name, 'path' => $path]);
                            $row->setIsNewRecord(false);
                            $row->afterFind();

                            $return[] = $row;
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @inheritdoc
     */
    public function save($runValidation = true, $attributeNames = NULL)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }
        if (!$this->beforeSave(true)) {
            return false;
        }

        $changedAttributes = $this->getDirtyAttributes($attributeNames);
        if (empty($changedAttributes)) {
            $this->afterSave(false, $changedAttributes);
            return 0;
        }

        $db     = static::getDb();
        $values = $this->getAttributes();

        $query  = [$this->tableName];
        $result = $this->attributes();

        $key = 0;
        while (isset($result[$key])) {
            $query[] = $values[$result[$key++]];
        }

        $db->executeCommand('hmset', $query);

        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function update($runValidation = true, $attributeNames = NULL)
    {
        return $this->save($runValidation, $attributeNames);
    }

    /**
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributeNames = null)
    {
        return $this->save($runValidation, $attributeNames);
    }

    /**
     * @inheritdoc
     */
    public function delete()
    {
        $result = false;
        if ($this->beforeDelete()) {
            $db = static::getDb();

            $attributes = $this->getOldAttributes();

            $query = [
                $this->tableName,
                $attributes['name'],
            ];

            $result = $db->executeCommand('hdel', $query);

            $this->_oldAttributes = null;
            $this->afterDelete();
        }

        return $result;
    }

}
