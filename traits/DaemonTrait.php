<?php

namespace phantomd\filedaemon\traits;

use yii\helpers\FileHelper;

/**
 * Trait DaemonTrait provides a common implementation of the DaemonController interface.
 *
 * @property \phantomd\filedaemon\FileProcessing $component FileProcessing component
 * @method void restart() Force kill current process if present file kind of `restart-{daemon name}`
 * @method void reloadComponent() Force reload component
 * @method void renewConnections() Force reconnect all connections to database for the component
 * @method array getConfig() Get configuration for current daemon
 * @method string getProcessName() Get name of current process
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
trait DaemonTrait
{

    /**
     * @var string Name of current process
     */
    protected $_shortName = '';

    /**
     * @var array Daemon configuration
     */
    protected $config = [];

    /**
     * @var string Directory for substitution in the URL of the file
     */
    protected $dirWeb = '/uploads/files';

    /**
     * @var string Temp directory
     */
    protected $dirSource = '@app/temp';

    /**
     * @var string Directory for results
     */
    protected $dirTarget = '@app';

    /**
     * @var mixed Path to directory where placed configuration files. Can be _string_ or _array_
     */
    protected $configPath = [
        '@app/common/config/daemons',
        '@app/config/daemons',
    ];

    /**
     * @var string File name configuration
     */
    protected $configFile = 'daemons.php';

    /**
     * @var array Additional methods for daemon from configuration
     */
    protected $commands = [];

    /**
     * @var string Name of configuration
     */
    protected $configName = '';

    /**
     * @var integer Current date in seconds
     */
    protected $currentDate = null;

    /**
     * @var integer Max post results
     */
    protected $maxPostCount = 100;

    /**
     * @var \phantomd\filedaemon\FileProcessing FileProcessing component
     */
    protected $component = null;

    /**
     * @var string Alias of name for daemon controller
     */
    protected static $configAlias = '';

    /**
     * Init method
     */
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

    /**
     * Event before restart daemon
     */
    protected function beforeRestart()
    {
        return true;
    }

    /**
     * Restart current daemon
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
     * Renew database connections
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
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
     * Get daemon configuration
     *
     * @return array
     */
    protected function getConfig()
    {
        $params = [];

        if (is_array($this->configPath)) {
            foreach ($this->configPath as $configPath) {
                $fileConfig = FileHelper::normalizePath(
                        \Yii::getAlias(
                            $configPath . DIRECTORY_SEPARATOR . $this->configFile
                        )
                );
                if (is_file($fileConfig)) {
                    $config = include $fileConfig;
                    if (false === empty($config) && is_array($config)) {
                        $params = array_merge($params, $config);

                        $this->configPath = FileHelper::normalizePath(\Yii::getAlias($configPath));
                        break;
                    }
                }
            }
        } else {
            $fileConfig = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $this->configPath . DIRECTORY_SEPARATOR . $this->configFile
                    )
            );

            if (is_file($fileConfig)) {
                $config = include $fileConfig;
                if (false === empty($config) && is_array($config)) {
                    $params = array_merge($params, $config);
                    $this->configPath = FileHelper::normalizePath(\Yii::getAlias($this->configPath));
                }
            }
        }

        if (isset($params[$this->configName])) {
            if (isset($params[$this->configName]['multi-instance']) && isset($this->isMultiInstance)) {
                $this->isMultiInstance = (bool)$params[$this->configName]['multi-instance'];
            }
            if (isset($params[$this->configName]['child-processes']) && isset($this->maxChildProcesses)) {
                $this->maxChildProcesses = (int)$params[$this->configName]['child-processes'];
            }
            if (isset($params[$this->configName]['max-threads']) && isset($this->maxThreads)) {
                $this->maxThreads = (int)$params[$this->configName]['max-threads'];
            }
            if (isset($params[$this->configName]['max-count']) && (int)$params[$this->configName]['max-count']) {
                $this->maxPostCount = (int)$params[$this->configName]['max-count'];
            }
            if (isset($params[$this->configName]['sleep']) && isset($this->sleep)) {
                $this->sleep = (int)$params[$this->configName]['sleep'];
            }

            if (false === isset($params[$this->configName]['directories'])) {
                $params[$this->configName]['directories'] = [];
            }
            if (false === isset($params[$this->configName]['directories']['web'])) {
                $params[$this->configName]['directories']['web'] = $this->dirWeb;
            }
            if (false === isset($params[$this->configName]['directories']['source'])) {
                $params[$this->configName]['directories']['source'] = $this->dirSource;
            }
            if (false === isset($params[$this->configName]['directories']['target'])) {
                $params[$this->configName]['directories']['target'] = $this->dirTarget;
            }


            $this->config     = $params[$this->configName];
            $this->_shortName = $this->configName;
        }

        return $this->config;
    }

}
