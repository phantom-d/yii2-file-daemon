<?php

namespace phantomd\filedaemon\traits;

use yii\helpers\FileHelper;

trait DaemonTrait
{

    protected $_shortName = '';

    protected $config = [];

    protected $configPath = '@app/config/daemons';

    protected $commands = [];

    protected $configName = '';

    protected $component = null;

    protected static $configAlias = '';

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
        $fileRestart = FileHelper::normalizePath(
                \Yii::getAlias(
                    $this->configPath . DIRECTORY_SEPARATOR . "restart-{$this->configName}"
                )
        );

        if (is_file($fileRestart)) {
            unlink($fileRestart);
            posix_kill(getmypid(), SIGKILL);
        }
    }

    /**
     * Обновление соединений с БД для нового потока
     */
    public function renewConnections()
    {
        if ($this->component && $this->component instanceof \phantomd\filedaemon\FileProcessing) {
            $this->component->renewConnections();
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
            if (isset(\Yii::$app->params['daemons'])) {
                $params = \Yii::$app->params['daemons'];
            } else {
                $fileConfig = FileHelper::normalizePath(
                        \Yii::getAlias(
                            $this->configPath . DIRECTORY_SEPARATOR . 'daemons.php'
                        )
                );
                if (is_file($fileConfig)) {
                    $params = include $fileConfig;
                }
            }

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
