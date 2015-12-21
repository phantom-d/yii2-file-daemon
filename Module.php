<?php

namespace phantomd\filedaemon;

class Module extends \yii\base\Module
{

    public $db = null;

    public $daemons = null;

    public $secret  = null;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    public function test()
    {
        echo '<pre>',
        '$this(' . __LINE__ . '): ', print_r($this, true), "\n",
        '</pre>';
    }

}
