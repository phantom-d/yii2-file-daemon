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
            'command'   => 0,
            'object_id' => 'test_object',
            'url'       => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png',
            'file_id'   => 'test_file',
            'score'     => 0,
            'name'      => 'test_source::' . microtime(true),
        ];

        $model = $this->adapter->sourceModel($data);
        $class = $this->adapter->getModels('source');

        $this->assertTrue($model instanceof $class, 'It is not source model!');

        $this->assertTrue($model->save(), 'Save source errors: ' . print_r($model->getErrors(), true));
        $this->assertEquals(0, $model->insert(), 'Insert source errors: ' . print_r($model->getErrors(), true));

        $this->assertFalse($model->update(), 'Update source errors: ' . print_r($model->getErrors(), true));
        $model->file_id = 'test_file1';
        $this->assertTrue($model->update(), 'Update source errors: ' . print_r($model->getErrors(), true));

        $this->assertNotEmpty($model->all($data), 'Error get source all!');
        $this->assertNotEmpty($model->one($data['name']), 'Error get source one!');

        $this->assertEquals(1, $model->count($data['name']), 'Error count source!');

        $this->assertNotEmpty($model->names(), 'Error get names!');

        $this->assertNotEmpty($model->groups(), 'Error get groups!');

        $this->assertFalse($model->rename([$data['name'] . '_']), "Error check rename params!");
        $this->assertTrue($model->rename($data['name'] . '_'), "Could not rename source!");

        $this->assertTrue($model->delete(), "Could not delete source!");

        $data['command'] = 1;

        $model = $this->adapter->sourceModel($data);
        $model->save();

        $this->assertTrue($model->remove(), 'Remove source errors: ' . print_r($model->getErrors(), true));
    }

}
