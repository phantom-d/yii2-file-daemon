<?php

class FileProcessingTest extends \Codeception\TestCase\Test
{

    /**
     * @var \UnitTester
     */
    protected $tester;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var phantomd\filedaemon\FileProcessing
     */
    protected $component;

    /**
     * @var \Yii 
     */
    protected $app;

    protected function _before()
    {
        $this->app = $this->getModule('Yii2')->app;
        $this->config    = $this->app->params['daemons']['image-server'];
        $params          = [
            'class'  => '\phantomd\filedaemon\FileProcessing',
            'config' => $this->config,
        ];
        $this->component = \Yii::createObject($params);
    }

    public function testGetWebClient()
    {

        $webClient = $this->component->getWebClient();
        $this->tester->assertNotEmpty($webClient);

        return $webClient;
    }

}
