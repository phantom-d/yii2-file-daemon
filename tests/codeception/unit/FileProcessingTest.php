<?php

namespace tests\codeception\unit;

use yii\codeception\TestCase;

class FileProcessingTest extends TestCase
{

    /**
     * @var \phantomd\filedaemon\FileProcessing FileProcessing
     */
    protected $component = null;

    protected function setUp()
    {
        parent::setUp();
        $config          = \Yii::$app->params['daemons']['image-server'];
        $params          = [
            'class'  => '\phantomd\filedaemon\FileProcessing',
            'config' => $config,
        ];
        $this->component = \Yii::createObject($params);
    }

    public function testAddSource()
    {
        $data = [
            [
                'command'   => 0,
                'object_id' => 'test_object',
                'url'       => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png',
                'file_id'   => 'test_file',
            ]
        ];
        $name = 'test_source::' . microtime(true);
        $this->assertNotNull($this->component, 'Could not initialize component!');
//        $this->assertTrue($this->component->addSource($name, $data), 'Error adding source data to database!');
    }

    public function testGetWebClient()
    {

        $webClient = $this->component->getWebClient();
        $this->assertNotEmpty($webClient);

        return $webClient;
    }

}
