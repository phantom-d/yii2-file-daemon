<?php

namespace phantomd\filedaemon\db\redis\models;

/**
 * Class Arcresult
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Arcresult extends HashModel
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
