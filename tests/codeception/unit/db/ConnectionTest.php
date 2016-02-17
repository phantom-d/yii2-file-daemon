<?php

namespace tests\codeception\unit\db;

use yii\codeception\TestCase;

class ConnectionTest extends TestCase
{

    /**
     * @var \phantomd\filedaemon\db\Connection
     */
    protected $adapter = '\phantomd\filedaemon\db\Connection';

    public function setUp()
    {
        parent::setUp();
        $config = \Yii::$app->params['daemons']['image-server']['db'];
        $params = [
            'class'  => $this->adapter,
            'params' => $config,
        ];

        $this->adapter = \Yii::createObject($params);
    }

    public function testModels()
    {
        $this->assertNotNull($this->adapter, 'Could not initialize database manager!');

        foreach ($this->adapter->getModels() as $name => $class) {
            $method = $name . 'Model';
            $model  = $this->adapter->{$method}();
            $this->assertTrue($model instanceof $class, "Could not initialize {$name} model!");
        }
    }

    public function testSourceModel()
    {
        $this->assertNotNull($this->adapter, 'Could not initialize database manager!');

        $data = [
            'command'   => '0',
            'object_id' => 'test_object',
            'url'       => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png',
            'file_id'   => 'test_file',
            'score'     => 0,
            'name'      => 'test_source::' . microtime(true),
        ];

        $model = $this->adapter->sourceModel($data);

        $this->assertTrue($model->save(), 'Save source errors: ' . print_r($model->getErrors(), true));
        $this->assertTrue((bool)$model->delete(), "Could not delete source!");
    }

}
