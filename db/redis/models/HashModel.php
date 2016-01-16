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

    private $type = 'hash';

    /**
     * Получение одной записи
     *
     * @param string $params[source_id] Наименование ключа в RedisDB
     * @param string $params[field] Наименование поля
     * @throws InvalidParamException
     * @return mixed
     */
    public static function getOne($params = [])
    {
        $params = [
            'source_id' => isset($params['source_id']) ? (string)$params['source_id'] : static::tableName(),
            'field'     => isset($params['field']) ? (string)$params['field'] : '',
        ];

        $errors = [];
        if ('' === $params['source_id']) {
            $errors[] = Yii::t('app', "Parameter 'source_id' cannot be blank.");
        }

        if ('' === $params['field']) {
            $errors[] = Yii::t('app', "Parameter 'field' cannot be blank.");
        }

        if ($errors) {
            throw new InvalidParamException(print_r($errors, true));
        }

        $model = new static;
        $db    = $model->getDb();

        if (false === $db->exists($params['source_id'])) {
            Yii::error(Yii::t('app', "source_id not exists: {source_id}!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        if ($model->type !== $db->type($params['source_id'])) {
            Yii::error(Yii::t('app', "Incorrect type of source_id '{source_id}'! Must be hash!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $result = $db->hget($params['source_id'], $params['field']);
        if ($result) {
            $model->setAttributes(['name' => $params['field'], 'path' => $result]);
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
     * @param string $params[fields] Массив наименований полей
     * @throws InvalidParamException
     * @return mixed
     */
    public static function getAll($params = [])
    {
        $params = [
            'source_id' => isset($params['source_id']) ? (string)$params['source_id'] : static::tableName(),
            'fields'    => isset($params['fields']) ? array_filter((array)$params['fields']) : [],
        ];

        $return = [];

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
            Yii::error(Yii::t('app', "Incorrect type of source_id '{source_id}'! Must be hash!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $query = [
            $params['source_id'],
        ];

        if (empty($params['fields'])) {
            $result = $db->executeCommand('hgetall', $query);

            if ($result) {
                $key = 0;
                while (isset($result[$key])) {
                    $row = clone $model;
                    $row->setAttributes(['name' => $result[$key++], 'path' => $result[$key++]]);
                    $row->setIsNewRecord(false);

                    $return[] = $row;
                }
            }
        } else {
            foreach ($params['fields'] as $field) {
                $query[] = (string)$field;
            }

            $result = $db->executeCommand('hmget', $query);

            if ($result) {
                $result = array_combine($params['fields'], $result);
                foreach ($result as $name => $path) {
                    if ($path) {
                        $row = clone $model;
                        $row->setAttributes(['name' => $name, 'path' => $path]);
                        $row->setIsNewRecord(false);

                        $return[] = $row;
                    }
                }
            }
        }

        if (false === $model->afterFind(true)) {
            return false;
        }

        return $return;
    }

}
