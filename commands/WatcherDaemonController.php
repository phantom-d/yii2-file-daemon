<?php

namespace phantomd\filedaemon\commands;

class WatcherDaemonController extends \vyants\daemon\controllers\WatcherDaemonController
{

    use phantomd\filedaemon\traits\DaemonTrait;

    private $currentDate = null;

    /**
     * @var string Короткое наименование для конфигурации
     */
    private $_shortName = '';

    public function init()
    {
        $this->currentDate = strtotime(date('Y-m-d 00:00:00'));
        $this->configName  = 'watcher';
        $this->getConfig();
        parent::init();
    }

    /**
     * @return array
     */
    protected function defineJobs()
    {
        $this->restart();
        sleep($this->sleep);
        $jobs = \Yii::getAlias('@app/config/daemons/watcher-jobs.php');

        $currentDate = strtotime(date('Y-m-d 00:00:00'));
        if ($currentDate > $this->currentDate) {
            $this->currentDate = $currentDate;
            foreach ($jobs as $key => $value) {
                if ($value['enabled']) {
                    $jobs[$key]['enabled'] = false;
                }
            }
        }

        return $jobs;
    }

    protected function getCommandNameBy($className)
    {
        $command = strtolower(
            preg_replace_callback('/(?<!^)(?<![A-Z])[A-Z]{1}/', function ($matches) {
                return '-' . $matches[0];
            }, str_replace('Controller', '', $className)
            )
        );

        if (!empty($this->daemonFolder)) {
            $command = $this->daemonFolder . DIRECTORY_SEPARATOR . $command;
        }

        return $command . DIRECTORY_SEPARATOR . 'index';
    }

    /**
     * Adjusting logger. You can override it.
     */
    protected function initLogger()
    {
        $targets = \Yii::$app->getLog()->targets;
        foreach ($targets as $name => $target) {
            $target->enabled = false;
        }
        $config = [
            'levels'  => ['error', 'warning', 'info'],
            'logFile' => \Yii::getAlias($this->logDir) . DIRECTORY_SEPARATOR . $this->shortClassName() . '.log',
            'logVars' => [],
            'except'  => [
                'yii\db\*', // Don't include messages from db
            ],
        ];

        if (YII_DEBUG) {
            $config['levels'][] = 'trace';
            $config['levels'][] = 'profile';
        }

        $targets['daemon'] = new \yii\log\FileTarget($config);

        \Yii::$app->getLog()->targets = $targets;
        \Yii::$app->getLog()->init();
    }

}
