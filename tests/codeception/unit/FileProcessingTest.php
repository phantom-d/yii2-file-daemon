<?php

namespace tests\codeception\unit;

use yii\codeception\TestCase;

class FileProcessingTest extends TestCase
{

    /**
     * @var \phantomd\filedaemon\FileProcessing FileProcessing
     */
    protected $component = '\phantomd\filedaemon\FileProcessing';

    protected function setUp()
    {
        parent::setUp();
        $params          = [
            'class'  => $this->component,
            'config' => \Yii::$app->params['daemons']['image-server'],
        ];
        $this->component = \Yii::createObject($params);
    }

    public function testAddSource()
    {
        $this->assertNotNull($this->component, 'Could not initialize component!');

        $data = [
            'command'   => '0',
            'object_id' => 'test_object',
            'url'       => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png',
            'file_id'   => 'test_file',
        ];

        $model = $this->component->sourceModel();
        $db    = $model->getDb();

        $this->assertNotEmpty($db, 'Error initialize component connection!');

        $db->open();

        $this->assertTrue($db->isActive, 'Error connection to database!');

        $name = 'test_source::' . microtime(true);
        $this->assertTrue($this->component->addSource($name, $data), 'Error adding source data to database!');
        $db->flushdb();
    }

    public function testGetWebClient()
    {

        $webClient = $this->component->getWebClient();
        $this->assertNotEmpty($webClient);

        return $webClient;
    }

}
