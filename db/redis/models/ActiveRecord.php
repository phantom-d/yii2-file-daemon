<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\NotSupportedException;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;
use yii\helpers\ArrayHelper;

/**
 * Class ActiveRecord
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class ActiveRecord extends \yii\redis\ActiveRecord implements \phantomd\filedaemon\db\ActiveInterface
{

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        return \Yii::$app->get('filedaemon_' . Inflector::camel2id(StringHelper::basename(get_called_class()), '_'));
    }

    /**
     * Returns the validation rules for attributes.
     */
    public function rules()
    {
        return [];
    }

    /**
     * Returns the list of attribute names.
     */
    public function attributes()
    {
        return [];
    }

    /**
     * Returns the attribute labels.
     */
    public function attributeLabels()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
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
    public static function count($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * Get ActiveQuery object
     *
     * @param array $params Condition array
     * @param int $limit Limit
     * @param int $page Page
     *
     * @return \yii\redis\ActiveQuery
     */
    public static function getQuery($params = [], $limit = 0, $page = 0)
    {
        $query = static::find();
        if ($params) {
            if (is_array($params)) {
                if (ArrayHelper::isAssociative($params)) {
                    $query = static::findByCondition($params);
                } else {
                    if (ArrayHelper::isAssociative($params, false)) {
                        foreach ($params as $key => $value) {
                            $data = [];
                            $type = 'and';
                            if (is_array($value)) {
                                if (isset($value[0]) && 2 === count($value)) {
                                    $value = [$value[0], $key, $value];
                                }
                                $data = $value;
                                $type = ('or' === $key) ? 'or' : $type;
                            } else {
                                $data[$key] = $value;
                            }

                            switch ($type) {
                                case 'and':
                                    $query->andWhere($data);
                                    break;
                                case 'or':
                                    $query->orWhere($data);
                                    break;
                            }
                        }
                    } else {
                        $query = static::findByCondition($params);
                    }
                }
            } else {
                $query = static::findByCondition($params);
            }
        }

        if ((int)$limit) {
            $query->limit((int)$limit)
                ->offset((int)$limit * (int)$page);
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public static function one($params = [], $remove = false)
    {
        return static::getQuery($params, 1)->one();
    }

    /**
     * @inheritdoc
     */
    public static function all($params = [], $limit = 0, $page = 0)
    {
        return static::getQuery($params, $limit, $page)->all();
    }

    /**
     * @inheritdoc
     */
    public static function names($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public static function groups($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function rename($params = [])
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    public function remove()
    {
        throw new NotSupportedException();
    }

}
