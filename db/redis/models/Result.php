<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;

/**
 * Result
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Result extends DataModel
{

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['object_id', 'file_name', 'time_dir',], 'required'],
            [['command', 'object_id', 'image_id', 'file_name', 'time_dir',], 'string'],
            [['command', 'object_id', 'image_id',], 'default', 'value' => 0],
            [['file_name', 'time_dir',], 'default', 'value' => ''],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'command',
            'object_id',
            'image_id',
            'file_name',
            'time_dir',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'command'   => \Yii::t('app', 'Command'),
            'object_id' => \Yii::t('app', 'Object ID'),
            'image_id'  => \Yii::t('app', 'Image ID'),
            'file_name' => \Yii::t('app', 'File name'),
            'time_dir'  => \Yii::t('app', 'Time directory'),
        ];
    }

}