<?php

namespace phantomd\filedaemon\db\redis\models;

use Yii;
use yii\base\NotSupportedException;

/**
 * Arc
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Arc extends DataModel
{

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'path',], 'required'],
            [['name', 'path',], 'string'],
            [['name', 'path',], 'default', 'value' => ''],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributes()
    {
        return [
            'name',
            'path',
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => \Yii::t('app', 'File name'),
            'path' => \Yii::t('app', 'File path'),
        ];
    }

}
