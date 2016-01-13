<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;

/**
 * Joblist
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Jobs extends \yii\redis\ActiveRecord implements \phantomd\filedaemon\db\DataInterface
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
     * @var integer Количество миллисекунд между повторами на запрос
     */
    protected static $sleepTry = 1000;

    protected static $dbConfig = null;

    protected static $db = null;

    /**
     * @inheritdoc
     */
    public static function getDb()
    {
        if (empty(static::$db)) {
            static::$db = \Yii::$app->get('filedaemon-jobs');
        }

        return static::$db;
    }

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
     * Получение задачи
     *
     * @param string $id ID задачи
     * @return mixed Объект с данными задачи | NULL
     */
    public static function chooseJob($id)
    {
        $find = static::find()
            ->where(['id' => $id]);

        $return  = $find->one();
        $attempt = 0;
        while (empty($return) && 10 > $attempt) {
            $return = $find->one();
            ++$attempt;
            usleep(static::$sleepTry);
        }
        return $return;
    }

    public static function getAll()
    {
        
    }

    public static function getCount()
    {
        
    }

    public static function getData($remove = false)
    {
        
    }

    public static function getJobs()
    {
        
    }

    public static function getOne($remove = false)
    {
        
    }

    public static function removeJobData()
    {
        
    }

    public static function renameJob($source, $target)
    {
        
    }

    public static function setData($params, $arc = false)
    {
        
    }

}
