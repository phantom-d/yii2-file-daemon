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

    private $type = 'zset';

    /**
     * Получение одной записи
     *
     * @param string $params[source_id] Наименование ключа в RedisDB
     * @param bool $params[remove] Удалять запись после получения.
     * @return mixed
     * @throws InvalidParamException
     */
    public static function getOne($params = [])
    {
        $params = [
            'source_id' => isset($params['source_id']) ? (string)$params['source_id'] : '',
            'remove'    => isset($params['remove']) ? (bool)$params['remove'] : false,
        ];

        if ('' === $params['source_id']) {
            throw new InvalidParamException(Yii::t('app', "Parameter 'source_id' cannot be blank."));
        }

        $model = new static;
        $db    = $model->getDb();

        if (false === $db->exists($params['source_id'])) {
            Yii::error(Yii::t('app', "source_id not exists: {source_id}!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        if ($model->type !== $db->type($params['source_id'])) {
            Yii::error(Yii::t('app', "Incorrect type of source_id '{source_id}'! Must be sorted sets!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $script = "local element = redis.pcall('ZRANGEBYSCORE', '{$params['source_id']}', '-inf', '+inf', 'WITHSCORES', 'LIMIT' , '0' , '1')"
            . ($params['remove'] ? " redis.pcall('ZREM', '{$params['source_id']}', element[1])" : '')
            . " return element";

        $result = $db->eval($script, 0);
        if ($result) {
            $model->setAttributes(array_merge(json_decode($result[0], true), ['score' => $result[1]]));
            $model->setIsNewRecord(false);
        } else {
            $model = null;
        }

        if (false === $model->afterFind(true)) {
            return false;
        }

        return $model;
    }

    /**
     * Получение списка записей
     * 
     * @param string $params[source_id] Наименование ключа в RedisDB
     * @param integer $params[limit] Количество.
     * @param integer $params[page] Номер страницы.
     * @throws InvalidParamException
     * @return mixed
     */
    public static function getAll($params = [])
    {
        $params = [
            'source_id' => isset($params['source_id']) ? (string)$params['source_id'] : '',
            'limit'     => isset($params['limit']) ? (int)$params['limit'] : 0,
            'page'      => isset($params['page']) ? (int)$params['page'] : 0,
        ];

        $return = [];

        if ('' === $params['source_id']) {
            throw new InvalidParamException(Yii::t('app', "Parameter 'source_id' cannot be blank."));
        }

        $model = new static;
        $db    = $model->getDb();

        if (false === $db->exists($params['table'])) {
            Yii::error(Yii::t('app', "source_id not exists: {source_id}!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        if ($model->type !== $db->type($params['source_id'])) {
            Yii::error(Yii::t('app', "Incorrect type of source_id '{source_id}'! Must be sorted sets!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $query = [
            $params['source_id'], //
            '-inf', '+inf', //
            'WITHSCORES',
        ];

        if ($params['limit']) {
            $query[] = 'LIMIT';
            $query[] = $params['limit'] * $params['page'];
            $query[] = $params['limit'];
        }

        $result = $db->executeCommand('zrangebyscore', $query);

        if ($result) {
            $key = 0;
            while (isset($result[$key])) {
                $row = clone $model;
                $row->setAttributes(array_merge(json_decode($result[$key++], true), ['score' => $result[$key++]]));
                $row->setIsNewRecord(false);

                $return[] = $row;
            }
        }

        if (false === $model->afterFind(true)) {
            return false;
        }

        return $return;
    }

}
