<?php

namespace tests\codeception\unit\db;

use yii\codeception\TestCase;

class ConnectionTest extends TestCase
{

    public function setUp()
    {
        parent::setUp();
        $config = \Yii::$app->params['daemons']['image-server']['db'];
        $params = [
            'class'  => '\phantomd\filedaemon\db\Connection',
            'params' => $config,
        ];

        $this->adapter = \Yii::createObject($params);
    }

    public function testSourceModel()
    {
        $this->assertNotNull($this->adapter, 'Could not initialize database manager!');
    }

}
