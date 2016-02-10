<?php

namespace phantomd\filedaemon\traits;

use yii\helpers\FileHelper;

/**
 * DaemonTrait provides a common implementation of the [[DaemonController]] interface.
 *
 * @property \phantomd\filedaemon\Component $component Component
 * @method void restart() Force kill current process if present file kind of `restart-{daemon name}`
 * @method void reloadComponent() Force reload component
 * @method void renewConnections() Force reconnect all connections to database for the component
 * @method array getConfig() Get configuration for current daemon
 * @method string getProcessName() Get name of current process
 */
trait DaemonTrait
{

    /**
     * Name of current process
     * @var string
     */
    protected $_shortName = '';

    /**
     * Array of configuration of daemon
     * @var array
     */
    protected $config = [];

    /**
     * Path to directory where placed configuration files
     * @var string
     */
    protected $configPath = '@app/config/daemons';

    /**
     * File name configuration
     * @var string
     */
    protected $configFile = 'daemons.php';

    /**
     * Array of extends methods for daemon
     * @var array
     */
    protected $commands = [];

    /**
     * Name of configuration
     * @var string
     */
    protected $configName = '';

    protected $currentDate = null;

    /**
     * FileProcessing
     * @var phantomd\filedaemon\FileProcessing
     */
    protected $component = null;

    /**
     * Alias of name for daemon controller
     * @var string
     */
    protected static $configAlias = '';

    public function init()
    {
        if (empty($this->configName)) {
            $this->configName = empty(static::$configAlias) ? '' : static::$configAlias;
        }

        if (empty($this->configName)) {
            $this->configName = $this->getConfigName($this->shortClassName(), ['Daemon']);
        }

        $this->getConfig();

        if (false === empty($this->config['commands'])) {
            foreach ($this->config['commands'] as $name => $value) {
                if (isset($value['id']) && false === isset($this->commands[(int)$value['id']])) {
                    $this->commands[(int)$value['id']] = $name;
                }
            }
        }

        $this->currentDate = strtotime(date('Y-m-d 00:00:00'));

        $this->reloadComponent();

        parent::init();
    }

    /**
     * Reload processing componet
     */
    protected function reloadComponent()
    {
        $this->component = \phantomd\filedaemon\Component::init($this->config);
    }

    protected function beforeRestart()
    {

    }

    /**
     * Завершение работы демона по команде
     */
    protected function restart()
    {
        $return = false;

        $fileRestart = FileHelper::normalizePath(
                \Yii::getAlias(
                    $this->configPath . DIRECTORY_SEPARATOR . "restart-{$this->configName}"
                )
        );

        if (is_file($fileRestart)) {
            $this->beforeRestart();
            unlink($fileRestart);
            static::stop();
            $return = true;
        }
        return $return;
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
     * Get classname without namespace
     *
     * @return string
     */
    public function shortClassName()
    {
        return \yii\helpers\StringHelper::basename(get_called_class());
    }

    /**
     * Get config name for daemon
     *
     * @param string $className Original class name
     * @param array $replace Array strings for remove
     * @return string
     */
    public function getConfigName($className = '', $replace = [])
    {
        $find = ['Controller'];

        $className = empty($className) ? $this->shortClassName() : (string)$className;

        if (false === empty($replace)) {
            $find = array_merge($find, (array)$replace);
        }

        return \yii\helpers\Inflector::camel2id(str_replace($find, '', $className));
    }

    /**
     * Получение настроек демона
     *
     * @return array
     */
    protected function getConfig()
    {
        $fileConfig = FileHelper::normalizePath(
                \Yii::getAlias(
                    $this->configPath . DIRECTORY_SEPARATOR . $this->configFile
                )
        );
        if (is_file($fileConfig)) {
            $params = include $fileConfig;
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

        return $this->config;
    }

    protected function getProcessName()
    {
        return $this->_shortName;
    }

}
