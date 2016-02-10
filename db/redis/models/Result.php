<?php

namespace phantomd\filedaemon\db\redis\models;

/**
 * Result
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Result extends SortedsetModel
{

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['object_id', 'file_name', 'time_dir',], 'required'],
            [['command', 'object_id', 'file_id', 'file_name', 'time_dir',], 'string'],
            [['command', 'object_id', 'file_id', 'score',], 'default', 'value' => 0],
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
            'file_id',
            'file_name',
            'time_dir',
            'score',
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
            'file_id'  => \Yii::t('app', 'Image ID'),
            'file_name' => \Yii::t('app', 'File name'),
            'time_dir'  => \Yii::t('app', 'Time directory'),
            'score'     => \Yii::t('app', 'Sort'),
        ];
    }

}
