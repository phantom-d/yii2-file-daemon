<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\InvalidParamException;

/**
 * Class SortedsetModel. Base model for **RedisDB** Sorted sets.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class SortedsetModel extends ActiveModel
{

    /**
     * @inheritdoc
     */
    protected static $type = 'zset';

    /**
     * @inheritdoc
     */
    public static function count($params = [])
    {
        $return = null;
        $model  = static::model($params);

        if ($model->checkTable()) {
            $db = static::getDb();

            $query  = [
                $model->tableName,
                '-inf', '+inf',
            ];
            $result = $db->executeCommand('zcount', $query);
            if (false === is_null($result)) {
                $return = (int)$result;
            }
        }

        return $return;
    }

    /**
     * Get one row
     *
     * @param string $params Condition array
     * @return mixed
     * @throws InvalidParamException
     */
    public static function one($params = [], $remove = false)
    {
        $return = null;
        $model  = static::model($params);

        if ($model->checkTable()) {
            $db    = static::getDb();
            $table = $model->tableName;

            $script = "local element = redis.pcall('ZRANGEBYSCORE', "
                . "'{$table}', '-inf', '+inf', 'WITHSCORES', 'LIMIT' , '0' , '1') "
                . ((bool)$remove ? "redis.pcall('ZREM', '{$table}', element[1]) " : '')
                . "return element";

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
     * Get rows
     *
     * @param string $params Condition array
     * @throws InvalidParamException
     * @return mixed
     */
    public static function all($params = [], $limit = 10, $page = 0)
    {
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

            YII_DEBUG && \Yii::info([$query], __METHOD__ . '(' . __LINE__ . ') --- $query');

            $result = $db->executeCommand('zrangebyscore', $query);

            YII_DEBUG && \Yii::info([$result], __METHOD__ . '(' . __LINE__ . ') --- $result');

            if ($result) {
                $key = 0;
                while (isset($result[$key])) {
                    $attributes = array_merge(json_decode($result[$key++], true), ['score' => $result[$key++]]);

                    $row = clone $model;
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
     * Get names from database
     *
     * @param string $params Regexp condition
     * @return array
     */
    public static function names($params = [])
    {
        $db        = static::getDb();
        $names     = [];
        $cursor    = 0;
        $pattern   = '*';
        $separator = '';

        if (is_array($params)) {
            if (isset($params['pattern']) && (string)$params['pattern']) {
                $pattern   = (string)$params['pattern'];
            }
            if (isset($params['separator']) && (string)$params['separator']) {
                $separator = (string)$params['separator'];
            }
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
     * Get groups
     *
     * @param string $params Regexp condition
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
    public function save($runValidation = true, $attributeNames = null)
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
    public function update($runValidation = true, $attributeNames = null)
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
        YII_DEBUG && \Yii::info([$changedAttributes], __METHOD__ . '(' . __LINE__ . ') --- $changedAttributes');

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

        YII_DEBUG && \Yii::info([$query], __METHOD__ . '(' . __LINE__ . ') --- $query');

        // save pk in a findall pool
        $result = $db->executeCommand('zadd', $query);

        YII_DEBUG && \Yii::info([$result], __METHOD__ . '(' . __LINE__ . ') --- $result');

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
                $this->tableName,
                $this->tableRename,
            ];

            YII_DEBUG && \Yii::info([$query], __METHOD__ . '(' . __LINE__ . ') --- $query');

            if ($result = (bool)$db->executeCommand('renamenx', $query)) {
                $this->tableName = $this->tableRename;
            }

            YII_DEBUG && \Yii::info([$result], __METHOD__ . '(' . __LINE__ . ') --- $result');

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
                $this->tableName,
            ];

            YII_DEBUG && \Yii::info([$query], __METHOD__ . '(' . __LINE__ . ') --- $query');

            $result = (bool)$db->executeCommand('del', $query);

            YII_DEBUG && \Yii::info([$result], __METHOD__ . '(' . __LINE__ . ') --- $result');

            $this->setOldAttributes(null);
            $this->afterRemove();
        }

        return $result;
    }

}
