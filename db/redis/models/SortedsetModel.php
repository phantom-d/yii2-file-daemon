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

    protected $table = null;

    /**
     * @inheritdoc
     */
    public static function count($params = [])
    {
        if (is_array($params)) {
            $params = [
                'table' => isset($params['table']) ? (string)$params['table'] : '',
            ];
        } else {
            $params = [
                'table' => (string)$params,
            ];
        }

        $return = null;
        if (static::checkTable($params['table'])) {
            $model = new static;
            $db    = $model->getDb();

            $query  = [
                $params['table'], //
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
     * @param string $params[table] Наименование ключа в RedisDB
     * @param bool $params[remove] Удалять запись после получения.
     * @return mixed
     * @throws InvalidParamException
     */
    public static function one($params = [], $remove = false)
    {
        if (is_array($params)) {
            $params = [
                'table'  => isset($params['table']) ? (string)$params['table'] : '',
                'remove' => $remove,
            ];
        } else {
            $params = [
                'table'   => (string)$params,
                'remove'  => $remove,
                'asArray' => false,
            ];
        }

        $return = null;
        if (static::checkTable($params['table'])) {
            $model = new static;
            $db    = $model->getDb();

            $script = "local element = redis.pcall('ZRANGEBYSCORE', '{$params['table']}', '-inf', '+inf', 'WITHSCORES', 'LIMIT' , '0' , '1')"
                . ($params['remove'] ? " redis.pcall('ZREM', '{$params['table']}', element[1])" : '')
                . " return element";

            $result = $db->executeCommand('eval', [$script, 0]);
            if ($result) {
                $attributes = array_merge(json_decode($result[0], true), ['score' => $result[1]]);
                if (empty($params['asArray'])) {
                    $model->table = $params['table'];
                    $model->setAttributes($attributes);
                    $model->setIsNewRecord(false);
                    $model->afterFind(true);
                } else {
                    $model = $attributes;
                }
                $return = $model;
            }
        }

        return $return;
    }

    /**
     * Получение списка записей
     * 
     * @param string $params['table'] Наименование ключа в RedisDB
     * @param integer $params['limit'] Количество.
     * @param integer $params['page'] Номер страницы.
     * @throws InvalidParamException
     * @return mixed
     */
    public static function all($params = [], $limit = 10, $page = 0)
    {
        if (is_array($params)) {
            $params = [
                'table' => isset($params['table']) ? (string)$params['table'] : '',
            ];
        } else {
            $params = [
                'table' => (string)$params,
            ];
        }

        $params['offset'] = $params['limit'] * $params['offset'];

        $return = [];

        if (static::checkTable($params['table'])) {
            $model = new static;
            $db    = $model->getDb();

            $model->table = $params['table'];

            $query = [
                $params['table'], //
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
                    if (empty($params['asArray'])) {
                        $row = clone $model;
                        $row->setAttributes($attributes);
                        $row->setIsNewRecord(false);
                        $row->afterFind();
                    } else {
                        $row = $attributes;
                    }
                    $return[] = $row;
                }
            }
        }

        return $return;
    }

    /**
     * Получения списка наименований задач
     *
     * @param string $params[pattern] Regexp выборки наименований
     * @return array
     */
    public static function names($params = [])
    {
        $db      = static::getDb();
        $names   = [];
        $point   = 0;
        $pattern = '*';

        if (isset($params['pattern'])) {
            $pattern = (string)$params['pattern'];
        }

        while ($result = $db->executeCommand('scan', [$point, 'MATCH', $pattern, 'COUNT', 10000])) {
            if ($point !== (int)$result[0]) {
                $point = (int)$result[0];
            }

            if ($rows = $result[1]) {
                foreach ($rows as $value) {
                    $names[] = $value;
                }
            }

            if (0 === $point) {
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
        $attributes = $this->getOldAttributes();

        if ($return = $this->insert($runValidation, $attributeNames)) {
            $db = static::getDb();

            unset($attributes['score']);

            $query = [
                $this->table,
                \yii\helpers\Json::encode($attributes),
            ];

            $db->executeCommand('zrem', $query);
        }
        return $return;
    }

    /**
     * @inheritdoc
     */
    public function insert($runValidation = true, $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }
        if (!$this->beforeSave(true)) {
            return false;
        }

        $db     = static::getDb();
        $values = $this->getAttributes();
        $score  = $values['score'];
        unset($values['score']);

        $query = [
            $this->table,
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

}
