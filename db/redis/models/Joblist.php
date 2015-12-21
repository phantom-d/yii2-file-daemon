<?php

namespace phantomd\filedaemon\models;

use Yii;

class Joblist extends RedisActiveRecord
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
            [['name', 'id', 'callback'], 'string'],
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
     * Получение статуса текущей задачи из списка разрешённых для работы статусов
     *
     * @return bool
     */
    public function getStatusWork()
    {
        return in_array((int)$this->status, $this->allowWork);
    }

    /**
     * Получение задачи со списком потоков
     *
     * @param string $id ID задачи
     * @param mixed|true $threads Массив условия выборки потоков | TRUE
     * @return object|null Объект с данными задачи | NULL
     */
    public static function chooseJob($id, $threads = null)
    {
        $find = self::find()
            ->where(['id' => $id]);

        if ($threads) {
            if (true === $threads) {
                $find->with('threads');
            } else {
                $find->with(['threads' => function ($query) use ($threads) {
                    if (is_array($threads)) {
                        foreach ($threads as $key => $value) {
                            if (false === is_numeric($key)) {
                                $query->andWhere([$key => $value]);
                            }
                        }
                    } else {
                        $query->andWhere($threads);
                    }
                },]);
            }
        }

        $return  = $find->one();
        $attempt = 0;
        while (empty($return) && 10 > $attempt) {
            $return = $find->one();
            ++$attempt;
            usleep(self::$sleepTry);
        }
        return $return;
    }

    /**
     * Проверка наличия задачи по полю name
     *
     * @param string $name Значение поля name в задаче
     * @return bool
     */
    public static function checkByName($name)
    {
        $find = self::find()
            ->where(['name' => $name])
            ->exists();
        return $find;
    }

}
