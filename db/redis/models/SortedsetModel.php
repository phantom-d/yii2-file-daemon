<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\InvalidParamException;

/**
 * SortedsetModel
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class SortedsetModel extends ActiveModel
{

    protected static $type = 'zset';

    /**
     * @inheritdoc
     */
    public static function count($params = [])
    {
        if (is_array($params)) {
            $params = [
                'name' => isset($params['name']) ? (string)$params['name'] : '',
            ];
        } else {
            $params = [
                'name' => (string)$params,
            ];
        }

        $return = null;
        $model  = static::model($params);

        if ($model->checkTable()) {
            $db = static::getDb();

            $query  = [
                $model->tableName, //
                '-inf', '+inf', //
            ];
            $result = $db->executeCommand('zcount', $query);
            if (false === is_null($result)) {
                $return = (int)$result;
            }
        }

        return $return;
    }

    /**
     * Получение одной записи
     *
     * @param string $params[source_id] Наименование ключа в RedisDB
     * @param bool $params[remove] Удалять запись после получения.
     * @return mixed
     * @throws InvalidParamException
     */
    public static function one($params = [], $remove = false)
    {
        if (is_array($params)) {
            $params = [
                'name' => isset($params['name']) ? (string)$params['name'] : '',
            ];
        } else {
            $params = [
                'name' => (string)$params,
            ];
        }

        $params['remove'] = (bool)$remove;

        $return = null;
        $model  = static::model($params);

        if ($model->checkTable()) {
            $db    = static::getDb();
            $table = $model->tableName;

            $script = "local element = redis.pcall('ZRANGEBYSCORE', '{$table}', '-inf', '+inf', 'WITHSCORES', 'LIMIT' , '0' , '1')"
                . ($params['remove'] ? " redis.pcall('ZREM', '{$table}', element[1])" : '')
                . " return element";

            $result = $db->executeCommand('eval', [$script, 0]);
            if ($result) {
                $attributes = array_merge(json_decode($result[0], true), ['score' => $result[1]]);

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
     * @param string $params['name'] Наименование ключа в RedisDB
     * @param integer $params['limit'] Количество.
     * @param integer $params['page'] Номер страницы.
     * @throws InvalidParamException
     * @return mixed
     */
    public static function all($params = [], $limit = 10, $page = 0)
    {
        if (is_array($params)) {
            $params = [
                'name' => isset($params['name']) ? (string)$params['name'] : '',
            ];
        } else {
            $params = [
                'name' => (string)$params,
            ];
        }

        $return = [];
        $model  = static::model($params);

        if ($model->checkTable()) {
            $db = static::getDb();

            $query = [
                $model->tableName, //
                '-inf', '+inf', //
                'WITHSCORES',
            ];

            if ((int)$limit) {
                $query[] = 'LIMIT';
                $query[] = (int)$limit * (int)$page;
                $query[] = $limit;
            }

            $result = $db->executeCommand('zrangebyscore', $query);

            if ($result) {
                $key = 0;
                while (isset($result[$key])) {
                    $attributes = array_merge(json_decode($result[$key++], true), ['score' => $result[$key++]]);

                    $row      = clone $model;
                    $row->setAttributes($attributes);
                    $row->setIsNewRecord(false);
                    $row->afterFind();
                    $return[] = $row;
                }
            }
        }

        return $return;
    }

    /**
     * Получения списка наименований задач
     *
     * @param string $params Regexp выборки наименований
     * @return array
     */
    public static function names($params = [])
    {
        $db      = static::getDb();
        $names   = [];
        $cursor  = 0;
        $pattern = '*';
        $separator = '';

        if (is_array($params)) {
            $pattern = (isset($params['pattern']) && (string)$params['pattern']) ? (string)$params['pattern'] : $pattern;
            $separator = (isset($params['separator']) && (string)$params['separator']) ? (string)$params['separator'] : $separator;
        } else {
            $pattern = ('' === (string)$params) ? $pattern : (string)$params;
        }

        while ($result = $db->executeCommand('scan', [$cursor, 'MATCH', $pattern, 'COUNT', 10000])) {
            if ($cursor !== (int)$result[0]) {
                $cursor = (int)$result[0];
            }

            if ($rows = $result[1]) {
                foreach ($rows as $value) {
                    if ($separator) {
                        $value = explode($separator, $value);
                    }
                    $names[] = $value;
                }
            }

            if (0 === $cursor) {
                break;
            }
        }

        return $names;
    }

    /**
     * Получение списка наименований групп задач
     *
     * @param string $params[pattern] Regexp выборки наименований
     * @return array
     */
    public static function groups($params = [])
    {
        $groups = [];

        if ($result = static::names($params)) {
            foreach ($result as $value) {
                $groups[explode('::', $value)[0]] = '';
            }
        }

        return array_keys($groups);
    }

    /**
     * @inheritdoc
     */
    public function save($runValidation = true, $attributeNames = NULL)
    {
        if ($this->getIsNewRecord()) {
            return $this->insert($runValidation, $attributeNames);
        } else {
            return $this->update($runValidation, $attributeNames) !== false;
        }
    }

    /**
     * @inheritdoc
     */
    public function update($runValidation = true, $attributeNames = NULL)
    {
        if ($this->getDirtyAttributes($attributeNames)) {
            $model  = clone $this;
            if ($return = $this->insert($runValidation, $attributeNames)) {
                $model->delete();
            }
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributeNames = null)
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
        $score  = $values['score'];
        unset($values['score']);

        $query = [
            $this->tableName,
            $score,
            \yii\helpers\Json::encode($values),
        ];
        // save pk in a findall pool
        $db->executeCommand('zadd', $query);

        $values['score'] = $score;

        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
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
            unset($attributes['score']);

            $query = [
                $this->tableName,
                \yii\helpers\Json::encode($attributes),
            ];

            $result = $db->executeCommand('zrem', $query);

            $this->setOldAttributes(null);
            $this->afterDelete();
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function rename($params = [])
    {
        $result = false;

        if (empty($params) || is_array($params)) {
            return $result;
        }

        $this->tableRename = (string)$params;

        if ($this->beforeRename()) {
            $db = static::getDb();

            $query = [
                $this->tableName($this),
                $this->tableRename,
            ];

            if ($result = (bool)$db->executeCommand('renamenx', $query)) {
                $this->tableName = $this->tableRename;
            }

            $this->tableRename = null;
            $this->afterRename();
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function remove()
    {
        $result = false;
        if ($this->beforeRemove()) {
            $db = static::getDb();

            $attributes = $this->getOldAttributes();
            unset($attributes['score']);

            $query = [
                $this->tableName($this),
                \yii\helpers\Json::encode($attributes),
            ];

            $result = (bool)$db->executeCommand('del', $query);

            $this->setOldAttributes(null);
            $this->afterRemove();
        }

        return $result;
    }

}
