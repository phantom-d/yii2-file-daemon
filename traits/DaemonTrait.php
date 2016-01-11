<?php

namespace phantomd\filedaemon\traits;

use yii\base\NotSupportedException;

trait DaemonTrait
{

    protected $config = [];

    protected $commands = [];

    protected $configName = '';

    public function init()
    {
        $this->setConfigName();

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

    public function setConfigName()
    {
        if (empty($this->configName)) {
            $this->configName = $this->getCommandNameBy($this->shortClassName());
        }
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

    /**
     * Get classname without namespace
     *
     * @return string
     */
    public static function shortClassName()
    {
        $classname = static::className();

        if (preg_match('@\\\\([\w]+)$@', $classname, $matches)) {
            $classname = $matches[1];
        }

        return $classname;
    }

    /**
     * Get command for console running
     * 
     * @param string $className Original class name
     * @param array $replace Array strings for remove
     * @return string
     */
    protected function getCommandNameBy($className = '', $replace = [])
    {
        $find = ['Controller'];

        if (false === empty($replace)) {
            $find = array_merge($find, (array)$replace);
        }

        $command = strtolower(
            preg_replace_callback(
                '/(?<!^)(?<![A-Z])[A-Z]{1}/', function ($matches) {
                return '-' . $matches[0];
            }, str_replace($find, '', (empty($className) ? $this->shortClassName() : $className))
            )
        );

        if (!empty($this->daemonFolder)) {
            $command = $this->daemonFolder . DIRECTORY_SEPARATOR . $command;
        }

        return $command . DIRECTORY_SEPARATOR . 'index';
    }

}
