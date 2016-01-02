<?php

namespace phantomd\filedaemon\commands\controllers;

class WatcherDaemonController extends DaemonController
{

    use phantomd\filedaemon\traits\DaemonTrait;

    /**
     * Daemons for check
     * [
     *  ['className' => 'OneDaemonController', 'enabled' => true]
     *  ...
     *  ['className' => 'AnotherDaemonController', 'enabled' => false]
     * ]
     * @var $daemonsList Array
     */
    public $daemonsList = [];

    public $daemonFolder = '';

    private $currentDate = null;

    /**
     * @var string Короткое наименование для конфигурации
     */
    private $_shortName = '';

    public function init()
    {
        $this->setConfigName();

        $pidFile = \Yii::getAlias($this->pidDir) . DIRECTORY_SEPARATOR . $this->shortClassName();
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if ($this->isProcessRunning($pid, $this->configName)) {
                $this->halt(self::EXIT_CODE_ERROR, 'Another Watcher is already running.');
            }
        }

        $this->currentDate = strtotime(date('Y-m-d 00:00:00'));
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

    /**
     * Job processing body
     *
     * @param $job array
     * @return boolean
     */
    protected function doJob($job)
    {
        $pidfile = \Yii::getAlias($this->pidDir) . DIRECTORY_SEPARATOR . $job['className'];

        \Yii::trace('Check daemon ' . $job['className']);
        if (file_exists($pidfile)) {
            $pid = file_get_contents($pidfile);
            if ($this->isProcessRunning($pid)) {
                if ($job['enabled']) {
                    \Yii::trace('Daemon ' . $job['className'] . ' running and working fine');
                    return true;
                } else {
                    \Yii::warning('Daemon ' . $job['className'] . ' running, but disabled in config. Send SIGTERM signal.');
                    if (isset($job['hardKill']) && $job['hardKill']) {
                        posix_kill($pid, SIGKILL);
                    } else {
                        posix_kill($pid, SIGTERM);
                    }
                    return true;
                }
            }
        }
        \Yii::trace('Daemon pid not found.');
        if ($job['enabled']) {
            \Yii::trace('Try to run daemon ' . $job['className'] . '.');
            $command_name = $this->getCommandNameBy($job['className']);
            //flush log before fork
            \Yii::$app->getLog()->getLogger()->flush(true);
            //run daemon
            $pid          = pcntl_fork();
            if ($pid == -1) {
                $this->halt(self::EXIT_CODE_ERROR, 'pcntl_fork() returned error');
            } elseif (!$pid) {
                $this->initLogger();
                \Yii::trace('Daemon ' . $job['className'] . ' is running.');
            } else {
                $this->halt(
                    (0 === \Yii::$app->runAction("$command_name", ['demonize' => 1]) ? self::EXIT_CODE_NORMAL : self::EXIT_CODE_ERROR
                    )
                );
            }
        }
        \Yii::trace('Daemon ' . $job['className'] . ' is checked.');

        return true;
    }

}
