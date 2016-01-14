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
     * @param string $params[table] Наименование ключа в RedisDB
     * @param string $params[field] Наименование поля
     * @throws InvalidParamException
     * @return mixed
     */
    public static function getOne($params = [])
    {
        $params = [
            'table'  => isset($params['table']) ? (string)$params['table'] : static::tableName(),
            'field' => isset($params['field']) ? (string)$params['field'] : '',
        ];

        $errors = [];
        if ('' === $params['table']) {
            $errors[] = Yii::t('app', "Parameter 'table' cannot be blank.");
        }

        if ('' === $params['field']) {
            $errors[] = Yii::t('app', "Parameter 'field' cannot be blank.");
        }

        if ($errors) {
            throw new InvalidParamException(print_r($errors, true));
        }

        $model = new static;
        $db    = $model->getDb();

        if (false === $db->exists($params['table'])) {
            Yii::error(Yii::t('app', "Table not exists: {table}!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        if ($model->type !== $db->type($params['table'])) {
            Yii::error(Yii::t('app', "Incorrect type of table '{table}'! Must be hash!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $result = $db->hget($params['table'], $params['field']);
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
     * @param string $params[table] Наименование ключа в RedisDB
     * @param string $params[fields] Массив наименований полей
     * @throws InvalidParamException
     * @return mixed
     */
    public static function getAll($params = [])
    {
        $params = [
            'table'  => isset($params['table']) ? (string)$params['table'] : static::tableName(),
            'fields' => isset($params['fields']) ? array_filter((array)$params['fields']) : [],
        ];

        $return = [];

        if ('' === $params['table']) {
            throw new InvalidParamException(Yii::t('app', "Parameter 'table' cannot be blank."));
        }

        $model = new static;
        $db    = $model->getDb();

        if (false === $db->exists($params['table'])) {
            Yii::error(Yii::t('app', "Table not exists: {table}!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        if ($model->type !== $db->type($params['table'])) {
            Yii::error(Yii::t('app', "Incorrect type of table '{table}'! Must be hash!", $params), __METHOD__ . '(' . __LINE__ . ')');
            return false;
        }

        $query = [
            $params['table'],
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
