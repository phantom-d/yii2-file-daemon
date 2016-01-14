<?php

namespace phantomd\filedaemon\db\redis\models;

/**
 * Source
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Source extends SortedsetModel
{

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['object_id', 'url',], 'required'],
            [['command', 'object_id', 'url', 'image_id',], 'string'],
            ['url', 'url', 'enableIDN' => true, 'message' => \Yii::t('yii', "{attribute} is not a valid URL!")],
            [['command', 'object_id', 'image_id', 'score',], 'default', 'value' => 0],
            [['url',], 'default', 'value' => ''],
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
            'url',
            'image_id',
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
            'url'       => \Yii::t('app', 'URL'),
            'image_id'  => \Yii::t('app', 'Image ID'),
            'score'     => \Yii::t('app', 'Sort'),
        ];
    }

}
