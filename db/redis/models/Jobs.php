<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;

/**
 * Class Jobs. Model for jobs. RedisDB.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Jobs extends ActiveRecord
{

    /**
     * Статусы задач
     */
    const STATUS_PREPARE = 0;

    const STATUS_WAIT = 1;

    const STATUS_WORK = 2;

    const STATUS_COMPLETE = 3;

    const STATUS_ERROR = 4;

    const STATUS_ABORT = 5;

    const STATUS_RESTART = 6;

    /**
     * @var array Список разрешённых для работы статусов
     */
    public $allowWork = [
        self::STATUS_WAIT,
        self::STATUS_WORK,
    ];

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['id', 'name',], 'unique'],
            [['time_elapsed', 'time_per_item', 'time_to_left',], 'double'],
            [['pid', 'status', 'total', 'complete', 'errors', 'time_create', 'time_end',], 'integer'],
            [['name', 'id', 'callback', 'group'], 'string'],
            [
                'callback',
                'url',
                'enableIDN' => true,
                'message'   => \Yii::t('yii', "{attribute} is not a valid URL!")
            ],
            [[
                'pid', 'complete', 'errors', 'total',
                'time_create', 'time_per_item', 'time_to_left', 'time_elapsed', 'time_end',
                ], 'default', 'value' => 0
            ],
            [['status',], 'default', 'value' => self::STATUS_WAIT],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'id',
            'pid',
            'name',
            'group',
            'callback',
            'status',
            'total',
            'complete',
            'errors',
            'time_create',
            'time_elapsed',
            'time_per_item',
            'time_to_left',
            'time_end',
        ];
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     */
    public function beforeSave($insert)
    {
        if ('' === (string)$this->group) {
            $this->group = (string)explode('::', $this->name)[0];
        }

        if (empty($this->time_create)) {
            $this->time_create = time();
        }

        if (empty($this->id)) {
            $this->id = md5($this->name);
        }

        return parent::beforeSave($insert);
    }

    /**
     * This method is called when the AR object is created and populated with the query result.
     */
    public function afterFind()
    {
        if ('' === (string)$this->group) {
            $this->group = (string)explode('::', $this->name)[0];
        }
    }

    /**
     * Get status work
     *
     * @return bool
     */
    public function getStatusWork()
    {
        return in_array((int)$this->status, $this->allowWork);
    }

    /**
     * @inheritdoc
     */
    public static function groups($params = [])
    {
        $groups = [];
        $page   = 0;

        while ($result = static::all($params, 100, $page++)) {
            foreach ($result as $model) {
                $groups[$model->group] = '';
            }
        }
        return array_keys($groups);
    }

}
