<?php

namespace tests\codeception\unit\db;

use yii\codeception\TestCase;
use Codeception\Util\Debug;

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
        $fixtures = require \Codeception\Configuration::config()['paths']['data'] . '/models.php';

        foreach ($this->adapter->getModels() as $name => $class) {
            $test  = in_array($name, $fixtures['test']);
            $field = $fixtures['fields'][$name];
            $data  = $fixtures[$name];

            $methodModel = $name . 'Model';
            $model       = $this->adapter->{$methodModel}($data);
            $this->assertTrue($model instanceof $class, "Could not initialize {$name} model!");

            $this->assertTrue($model->save(), "Save {$name} errors: " . print_r($model->getErrors(), true));
            $this->assertEquals(0, $model->insert(), "Insert {$name} errors: " . print_r($model->getErrors(), true));

            $this->assertEquals(0, $model->update(), "Update {$name} errors: " . print_r($model->getErrors(), true));

            $model->{$field} = $model->{$field} . '_';
            $this->assertTrue($model->update(), "Update {$name} errors: " . print_r($model->getErrors(), true));

            $this->assertNotEmpty($class::all($data['name']), "Error get {$name} all!");
            $this->assertNotEmpty($class::one($data['name']), "Error get {$name} one!");

            if ($test) {
                $this->assertEquals(1, $model->count($data['name']), "Error count {$name}!");

                $this->assertNotEmpty($model->names(), "Error get {$name} names!");

                $this->assertNotEmpty($model->groups(), "Error get {$name} groups!");

                $this->assertFalse($model->rename([$data['name'] . '_']), "Error check {$name} rename params!");
                $this->assertTrue($model->rename($data['name'] . '_'), "Could not rename {$name}!");
            }

            $this->assertTrue($model->delete(), "Could not delete {$name}!");

            if ($test) {
                $data['command'] = 1;

                $model = $this->adapter->{$methodModel}($data);
                $model->save();

                $this->assertTrue($model->remove(), "Remove {$name} errors: " . print_r($model->getErrors(), true));
            }
        }
    }

}
