<?php

namespace phantomd\filedaemon\traits;

use yii\base\NotSupportedException;

trait DaemonTrait
{

    protected $config = [];

    protected $commands = [];

    protected $configName = '';

    protected $processing = null;

    protected $component = null;

    public function init()
    {
        parent::init();

        $this->configName = empty(static::$configAlias) ? '' : static::$configAlias;

        $this->getConfig();

        if (false === empty($this->config['commands'])) {
            foreach ($this->config['commands'] as $name => $value) {
                if (isset($value['id']) && false === isset($this->commands[(int)$value['id']])) {
                    $this->commands[(int)$value['id']] = $name;
                }
            }
        }

        $this->component = \phantomd\filedaemon\Component::init($this->config);
    }

    /**
     * Завершение работы демона по команде
     */
    protected function restart()
    {
        if (is_file($fileRestart = \Yii::getAlias('@app/config/daemons/restart-' . $this->configName))) {
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

    protected function getProcessName()
    {
        return $this->_shortName;
    }

}
