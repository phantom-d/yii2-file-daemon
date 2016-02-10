<?php

namespace phantomd\filedaemon\commands\controllers;

/**
 * Class WatcherDaemonController
 *
 * @author Vladimir Yants <vladimir.yants@gmail.com>
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class WatcherDaemonController extends StreakDaemonController
{

    /**
     *
     * ```php
     * [
     *      [
     *          'className'  => 'OneDaemonController',
     *          'enabled'    => true
     *      ],
     *      [
     *          'className'  => 'AnotherDaemonController',
     *          'enabled'    => false
     *      ],
     * ];
     * ```
     *
     * @var array Daemons for check
     */
    public $daemonsList = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        \Yii::info('Watcher daemon start.');
        $pidFile = \Yii::getAlias($this->pidDir) . DIRECTORY_SEPARATOR . $this->shortClassName();
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            if ($this->isProcessRunning($pid, $this->configName)) {
                $this->halt(self::EXIT_CODE_ERROR, 'Another Watcher is already running.');
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function beforeRestart()
    {
        foreach ($this->config['daemons'] as $key => $value) {
            if ($value['enabled']) {
                $this->config['daemons'][$key]['enabled'] = false;
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function defineJobs()
    {
        $this->getConfig();

        if (empty($this->config['daemons'])) {
            return [];
        }

        if (strtotime(date('Y-m-d 00:00:00')) > $this->currentDate) {
            $fileRestart = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $this->configPath . DIRECTORY_SEPARATOR . "restart-{$this->configName}"
                    )
            );
            file_put_contents($fileRestart, '');
        }

        $this->restart();

        return $this->config['daemons'];
    }

    /**
     * @inheritdoc
     */
    protected function doJob($job)
    {
        $pidfile = \Yii::getAlias($this->pidDir) . DIRECTORY_SEPARATOR . $job['className'];

        YII_DEBUG && \Yii::info('Check daemon ' . $job['className']);
        if (file_exists($pidfile)) {
            $pid = file_get_contents($pidfile);
            if ($this->isProcessRunning($pid, $this->getConfigName($job['className'], ['Daemon']))) {
                if ($job['enabled']) {
                    if (YII_DEBUG) {
                        \Yii::info("Daemon '{$job['className']}', PID: {$pid} running and working fine");
                    }
                    return true;
                } else {
                    \Yii::warning("Daemon {$job['className']} running, but disabled in config. Send SIGTERM signal.");
                    if (isset($job['hardKill']) && $job['hardKill']) {
                        posix_kill($pid, SIGKILL);
                    } else {
                        posix_kill($pid, SIGTERM);
                    }
                    return true;
                }
            }
        }

        YII_DEBUG && \Yii::info('Daemon ' . $job['className'] . ' pid not found.');

        if ($job['enabled']) {
            YII_DEBUG && \Yii::info('Try to run daemon ' . $job['className'] . '.');
            $command_name = $this->getCommandNameBy($job['className']);

            //flush log before fork
            \Yii::$app->getLog()->getLogger()->flush(true);

            //run daemon
            $pid = pcntl_fork();

            if ($pid == -1) {
                $this->halt(self::EXIT_CODE_ERROR, 'pcntl_fork() returned error');
            } elseif (!$pid) {
                $this->initLogger();
                YII_DEBUG && \Yii::info('Daemon ' . $job['className'] . ' is running.');
            } else {
                $result = \Yii::$app->runAction("$command_name", ['demonize' => 1]);
                $this->halt(
                    (0 === $result ? self::EXIT_CODE_NORMAL : self::EXIT_CODE_ERROR
                    )
                );
            }
        }
        YII_DEBUG && \Yii::info('Daemon ' . $job['className'] . ' is checked.');

        return true;
    }

}
