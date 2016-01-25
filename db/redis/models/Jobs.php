<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;

/**
 * Joblist
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Jobs extends ActiveRecord
{

    /**
     * Статусы задач
     */
    const STATUS_PREPARE = 0,
        STATUS_WAIT = 1,
        STATUS_WORK = 2,
        STATUS_COMPLETE = 3,
        STATUS_ERROR = 4,
        STATUS_ABORT = 5,
        STATUS_RESTART = 6;

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
            [['id'], 'unique'],
            [['time_elapsed', 'time_per_item', 'time_to_left',], 'double'],
            [['pid', 'status', 'total', 'complete', 'errors', 'time_create', 'time_end',], 'integer'],
            [['name', 'id', 'callback', 'group'], 'string'],
            [
                'callback',
                'url',
                'enableIDN' => true,
                'message'   => \Yii::t('yii', "{attribute} is not a valid URL!")
            ],
            [['pid', 'complete', 'errors', 'time_elapsed', 'time_end', 'time_per_item', 'time_create', 'time_to_left', 'total',], 'default', 'value' => 0],
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

    public function beforeSave($insert)
    {
        if ('' === (string)$this->group) {
            $this->group = (string)explode('::', $this->name)[0];
        }
        
        if (empty($this->time_create)) {
            $this->time_create = time();
        }

        return parent::beforeSave($insert);
    }
    
    public function afterFind()
    {
        if ('' === (string)$this->group) {
            $this->group = (string)explode('::', $this->name)[0];
        }
    }

    /**
     * Получение статуса текущей задачи из списка разрешённых для работы статусов
     *
     * @return bool
     */
    public function getStatusWork()
    {
        return in_array((int)$this->status, $this->allowWork);
    }

}
