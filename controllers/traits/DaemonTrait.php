<?php

namespace app\components\filedaemon\controllers\traits;

use yii\base\NotSupportedException;

trait DaemonTrait
{

    protected $config = [];

    protected $commands = [];

    protected $configName = '';

    public function init()
    {
        if (empty($this->configName)) {
            $this->configName = $this->getCommandNameBy($this->shortClassName());
        }

        parent::init();

        $this->getConfig();
        $this->renewConnections();
        if (false === empty($this->config['commands'])) {
            foreach ($this->config['commands'] as $name => $value) {
                if (isset($value['id']) && false === isset($this->commands[(int)$value['id']])) {
                    $this->commands[(int)$value['id']] = $name;
                }
            }
        }
    }

    /**
     * Завершение работы демона по команде
     */
    protected function restart()
    {
        if (is_file($fileRestart = \Yii::getAlias('@app') . '/config/restart-' . $this->configName)) {
            unlink($fileRestart);
            posix_kill(getmypid(), SIGKILL);
        }
    }

    /**
     * Обновление соединений с БД для нового потока
     */
    public function renewConnections()
    {
        if (false === empty($this->config['db'])) {
            foreach ($this->config['db'] as $db) {
                if (false === empty($this->config['db-config'][$db]['class'])) {
                    \Yii::$app->set($db, $this->config['db-config'][$db]);
                }
            }
        }
    }

    /**
     * Проверка наличия процесса по ID и имени
     *
     * @param $pid ID процесса
     * @param $name Имя процесса
     * @return bool Статус процесса
     */
    public function isProcessRunning($pid, $name = '')
    {
        if ('' !== (string)$name) {
            $name = "| grep -i '{$name}'";
        }
        $command = "/usr/bin/env ps -p {$pid} -o args= {$name}";
        $result  = `{$command}`;
        return (bool)`/usr/bin/env ps -p {$pid} -o args= {$name}`;
    }

    /**
     * Получение настроек демона
     * 
     * @return array
     */
    protected function getConfig()
    {
        if (empty($this->config)) {
            $params = \Yii::$app->params['daemons'];

            if (isset($params[$this->configName])) {
                if (isset($params[$this->configName]['multi-instance']) && isset($this->isMultiInstance)) {
                    $this->isMultiInstance = (bool)$params[$this->configName]['multi-instance'];
                }
                if (isset($params[$this->configName]['child-processes']) && isset($this->maxChildProcesses)) {
                    $this->maxChildProcesses = (int)$params[$this->configName]['child-processes'];
                }
                if (isset($params[$this->configName]['sleep']) && isset($this->sleep)) {
                    $this->sleep = (int)$params[$this->configName]['sleep'];
                }

                $this->config     = $params[$this->configName];
                $this->_shortName = $this->configName;
            }
        }

        return $this->config;
    }

    protected function getCommandNameBy($className = '')
    {
        $command = strtolower(
            preg_replace_callback(
                '/(?<!^)(?<![A-Z])[A-Z]{1}/', function ($matches) {
                return '-' . $matches[0];
            }, str_replace(['Daemon', 'Controller'], '', (empty($className) ? $this->shortClassName() : $className))
            )
        );

        return $command;
    }

    /**
     * @inheritdoc
     */
    protected function initLogger()
    {
        $targets = \Yii::$app->getLog()->targets;
        foreach ($targets as $target) {
            $target->enabled = false;
        }
        $date        = date('Y-m-d');
        $logFileName = \Yii::getAlias($this->logDir)
            . DIRECTORY_SEPARATOR . $this->getCommandNameBy($this->shortClassName())
            . DIRECTORY_SEPARATOR . $date
            . DIRECTORY_SEPARATOR . $this->_shortName . '_' . $date . '.log';

        $config          = [
            'levels'  => ['error', 'warning', 'info'],
            'logFile' => $logFileName,
            'logVars' => [],
            'prefix'  => function() {
            return '';
        },
            'exportInterval' => 1,
            'enableRotation' => false,
            'except'         => [
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

    protected function getProcessName()
    {
        return $this->_shortName;
    }

    /**
     * Переименование процесса
     * 
     * @param string $prefix Префикс к имени процесса
     * @throws NotSupportedException
     */
    protected function renameProcess($prefix = '')
    {
        $name = $this->getProcessName();
        if (false === empty($prefix)) {
            $name = $prefix . '-' . $name;
        }

        //rename process
        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            cli_set_process_title($name);
        } else {
            if (function_exists('setproctitle')) {
                setproctitle($name);
            } else {
                throw new NotSupportedException("Can't find cli_set_process_title or setproctitle function");
            }
        }
    }

    /**
     * Get classname without namespace
     *
     * @return string
     */
    public function shortClassName()
    {
        $classname = $this->className();

        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            $classname = $matches[1];
        }

        return $classname;
    }

}
