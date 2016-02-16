<?php

namespace phantomd\filedaemon\commands\controllers;

use yii\base\NotSupportedException;
use yii\console\Controller;
use yii\helpers\Console;
use yii\helpers\Inflector;
use yii\helpers\StringHelper;

/**
 * Class DaemonController. Base daemon controller.
 *
 * @author Vladimir Yants <vladimir.yants@gmail.com>
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
abstract class DaemonController extends Controller
{

    const EVENT_BEFORE_JOB = "EVENT_BEFORE_JOB";

    const EVENT_AFTER_JOB = "EVENT_AFTER_JOB";

    const EVENT_BEFORE_ITERATION = "event_before_iteration";

    const EVENT_AFTER_ITERATION = "event_after_iteration";

    /**
     * @var boolean Run controller as Daemon
     */
    public $demonize = false;

    /**
     * @var boolean Allow daemon create a few instances
     * @see $maxChildProcesses
     */
    public $isMultiInstance = false;

    /**
     * @var integer Max daemon instances
     */
    public $maxChildProcesses = 10;

    /**
     * @var integer Max jobs group instances
     */
    public $maxThreads = 1;

    /**
     * @var string Daemon folder for console command
     */
    public $daemonFolder = '';

    /**
     * @var integer Main procces pid
     */
    protected $parentPID;

    /**
     * @var array Array of running instances
     */
    protected static $currentJobs = [];

    /**
     * @var integer Memory limit for daemon, must bee less than php memory_limit
     */
    private $memoryLimit = 268435456;

    /**
     * @var integer Used for soft daemon stop, set 1 to stop
     */
    private static $stopFlag = 0;

    /**
     * @var integer Delay between task list checking in seconds
     */
    protected $sleep = 5;

    /**
     * @var string Directory for save pid file
     */
    protected $pidDir = "@runtime/daemons/pids";

    /**
     * @var string Directory for save pid file
     */
    protected $logDir = "@runtime/daemons/logs";

    /**
     * @var string Short class name
     */
    private $shortName = '';

    /**
     * Init function
     */
    public function init()
    {
        parent::init();

        //set PCNTL signal handlers
        pcntl_signal(SIGSYS, [__CLASS__, 'signalHandler']);
        pcntl_signal(SIGTERM, [__CLASS__, 'signalHandler']);
        pcntl_signal(SIGHUP, [__CLASS__, 'signalHandler']);
        pcntl_signal(SIGUSR1, [__CLASS__, 'signalHandler']);
        pcntl_signal(SIGUSR2, [__CLASS__, 'signalHandler']);
        pcntl_signal(SIGCHLD, [__CLASS__, 'signalHandler']);

        $this->shortName = $this->shortClassName();
        $this->setConfigName();
    }

    /**
     * Adjusting logger. You can override it.
     */
    protected function initLogger()
    {
        $targets = \Yii::$app->getLog()->targets;
        foreach ($targets as $target) {
            $target->enabled = false;
        }
        $date        = date('Y-m-d');
        $logFileName = \Yii::getAlias($this->logDir)
            . DIRECTORY_SEPARATOR . $this->getConfigName($this->shortClassName(), 'Daemon')
            . DIRECTORY_SEPARATOR . $date
            . DIRECTORY_SEPARATOR . $this->_shortName . '_' . $date . '.log';

        $config          = [
            'class'   => '\phantomd\filedaemon\components\FileTarget',
            'levels'  => ['error', 'warning', 'info'],
            'logFile' => $logFileName,
            'logVars' => [],
            'prefix'  => function () {
            return '';
        },
            'exportInterval' => 1,
            'enableRotation' => false,
            'except'         => [
                'yii\db\*', // Don't include messages from db
            ],
        ];

        if (YII_DEBUG) {
//            $config['levels'][] = 'trace';
            $config['levels'][] = 'profile';
        }

        $targets['daemon'] = \Yii::createObject($config);

        \Yii::$app->getLog()->targets = $targets;
        \Yii::$app->getLog()->init();
    }

    /**
     * Daemon worker body
     *
     * @param $job Job data
     * @return boolean
     */
    abstract protected function doJob($job);

    /**
     * Base action, you can\t override or create another actions
     *
     * @return boolean
     */
    final public function actionIndex()
    {
        if ($this->demonize) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                $this->halt(self::EXIT_CODE_ERROR, 'pcntl_fork() rise error');
            } elseif ($pid) {
                $this->halt(self::EXIT_CODE_NORMAL);
            } else {
                posix_setsid();
                //close std streams (unlink console)
                if (is_resource(STDIN)) {
                    fclose(STDIN);
                    $stdIn = fopen('/dev/null', 'r');
                }
                if (is_resource(STDOUT)) {
                    fclose(STDOUT);
                    $stdOut = fopen('/dev/null', 'ab');
                }
                if (is_resource(STDERR)) {
                    fclose(STDERR);
                    $stdErr = fopen('/dev/null', 'ab');
                }
            }
        }
        //rename process
        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            cli_set_process_title($this->getProcessName());
        } else {
            if (function_exists('setproctitle')) {
                setproctitle($this->getProcessName());
            } else {
                throw new NotSupportedException(
                "Can't find cli_set_process_title or setproctitle function"
                );
            }
        }
        //run iterator
        return $this->loop();
    }

    /**
     * Process name
     *
     * @return string
     */
    protected function getProcessName()
    {
        return $this->shortName;
    }

    /**
     * Prevent non index action running
     *
     * @param \yii\base\Action $action
     * @return bool
     * @throws NotSupportedException
     */
    public function beforeAction($action)
    {
        if (parent::beforeAction($action)) {
            $this->initLogger();
            if ($action->id != "index") {
                throw new NotSupportedException(
                "Only index action allowed in daemons. So, don't create and call another"
                );
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get available options
     *
     * @param string $actionID Called action
     * @return array
     */
    public function options($actionID)
    {
        return [
            'demonize',
            'taskLimit',
            'isMultiInstance',
            'maxChildProcesses'
        ];
    }

    /**
     * Extract current unprocessed jobs
     * You can extract jobs from DB (DataProvider will be great), queue managers (ZMQ, RabbiMQ etc), redis and so on
     *
     * @return array with jobs
     */
    abstract protected function defineJobs();

    /**
     * Fetch one task from array of tasks
     *
     * @param array $jobs Jobs array
     * @return mixed one task
     */
    protected function defineJobExtractor(&$jobs)
    {
        return array_shift($jobs);
    }

    /**
     * Main iterator
     *
     * @return boolean 0|1
     */
    private function loop()
    {
        if (file_put_contents($this->getPidPath(), getmypid())) {
            $this->parentPID = getmypid();
            YII_DEBUG && \Yii::info('Daemon ' . $this->shortName . ' pid ' . getmypid() . ' started.');
            while (!self::$stopFlag && (memory_get_usage() < $this->memoryLimit)) {
                $this->trigger(self::EVENT_BEFORE_ITERATION);
                $this->renewConnections();
                $jobs = $this->defineJobs();
                if ($jobs && count($jobs)) {
                    while (($job = $this->defineJobExtractor($jobs)) !== null) {
                        //if no free workers, wait
                        if (count(static::$currentJobs) >= $this->maxChildProcesses) {
                            YII_DEBUG && \Yii::info('Reached maximum number of child processes. Waiting...');
                            while (count(static::$currentJobs) >= $this->maxChildProcesses) {
                                sleep(1);
                                pcntl_signal_dispatch();
                            }
                            YII_DEBUG && \Yii::info(
                                    'Free workers found: ' .
                                    ($this->maxChildProcesses - count(static::$currentJobs)) .
                                    ' worker(s). Delegate tasks.'
                            );
                        }
                        pcntl_signal_dispatch();
                        $this->runDaemon($job);
                        sleep(1);
                    }
                }
                sleep($this->sleep);
                pcntl_signal_dispatch();
                $this->trigger(self::EVENT_AFTER_ITERATION);
            }
            if (memory_get_usage() > $this->memoryLimit) {
                YII_DEBUG && \Yii::info('Daemon ' . $this->shortName . ' pid ' .
                        getmypid() . ' used ' . memory_get_usage() . ' bytes on ' . $this->memoryLimit .
                        ' bytes allowed by memory limit');
            }

            \Yii::info('Daemon ' . $this->shortClassName() . ' pid ' . getmypid() . ' is stopped.');

            if (file_exists($this->getPidPath())) {
                @unlink($this->getPidPath());
            } else {
                \Yii::error("Can't unlink pid file " . $this->getPidPath());
            }

            return self::EXIT_CODE_NORMAL;
        }
        $this->halt(self::EXIT_CODE_ERROR, 'Can\'t create pid file ' . $this->getPidPath());
    }

    /**
     * Completes the process (soft)
     */
    public static function stop()
    {
        self::$stopFlag = 1;
    }

    /**
     * PCNTL signals handler
     *
     * @param $signo Signal number
     * @param integer $pid Process ID
     * @param integer $status Status
     */
    final public function signalHandler($signo, $pid = null, $status = null)
    {
        switch ($signo) {
            case SIGSYS:
            case SIGTERM:
                //shutdown
                static::stop();
                break;
            case SIGHUP:
                //restart, not implemented
                break;
            case SIGUSR1:
                //user signal, not implemented
                break;
            case SIGUSR2:
                //user signal, not implemented
                break;
            case SIGCHLD:
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                while ($pid > 0) {
                    if ($pid && isset(static::$currentJobs[$pid])) {
                        unset(static::$currentJobs[$pid]);
                    }
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break;
        }
    }

    /**
     * Tasks runner
     *
     * @param string $job Job data
     * @return boolean
     */
    final public function runDaemon($job)
    {

        if ($this->isMultiInstance) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                return false;
            } elseif ($pid) {
                static::$currentJobs[$pid] = true;
            } else {
                $this->renewConnections();
                //child process must die
                $this->trigger(self::EVENT_BEFORE_JOB);
                if ($this->doJob($job)) {
                    $this->trigger(self::EVENT_AFTER_JOB);
                    $this->halt(self::EXIT_CODE_NORMAL);
                } else {
                    $this->trigger(self::EVENT_AFTER_JOB);
                    $this->halt(self::EXIT_CODE_ERROR, 'Child process #' . $pid . ' return error.');
                }
            }

            return true;
        } else {
            $this->trigger(self::EVENT_BEFORE_JOB);
            $status = $this->doJob($job);
            $this->trigger(self::EVENT_AFTER_JOB);

            return $status;
        }
    }

    /**
     * Stop process and show or write message
     *
     * @param integer $code Code completion -1|0|1
     * @param string $message Message
     */
    protected function halt($code, $message = null)
    {
        if ($message !== null) {
            if ($code == static::EXIT_CODE_ERROR) {
                \Yii::error($message);
                if (!$this->demonize) {
                    $message = Console::ansiFormat($message, [Console::FG_RED]);
                }
            } else {
                YII_DEBUG && \Yii::info($message);
            }
            if (!$this->demonize) {
                $this->writeConsole($message);
            }
        }
        if ($code !== -1) {
            exit($code);
        }
    }

    /**
     * Renew database connections
     *
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     */
    protected function renewConnections()
    {
        if (isset(\Yii::$app->db)) {
            \Yii::$app->db->close();
            \Yii::$app->db->open();
        }
    }

    /**
     * Show message in console
     *
     * @param $message Message
     */
    private function writeConsole($message)
    {
        $out = Console::ansiFormat('[' . date('d.m.Y H:i:s') . '] ', [Console::BOLD]);
        $this->stdout($out . $message . "\n");
    }

    /**
     * Get classname without namespace
     *
     * @return string
     */
    public function shortClassName()
    {
        return StringHelper::basename(get_called_class());
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
        $command = $this->getConfigName($className, $replace);

        if (false === empty($this->daemonFolder)) {
            $command = $this->daemonFolder . DIRECTORY_SEPARATOR . $command;
        }

        return $command . DIRECTORY_SEPARATOR . 'index';
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

        return Inflector::camel2id(str_replace($find, '', $className));
    }

    /**
     * Set name of configuration
     */
    public function setConfigName()
    {
        if (empty($this->configName)) {
            $this->configName = $this->getCommandNameBy($this->getProcessName());
        }
    }

    /**
     * Get full path to pid file
     *
     * @return string
     */
    public function getPidPath()
    {
        $dir = \Yii::getAlias($this->pidDir);
        if (!file_exists($dir)) {
            mkdir($dir, 0744, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $this->shortName;
    }

    /**
     * Check is process running by ID and name
     *
     * @param $pid Process ID
     * @param $name Process name
     * @return bool
     */
    public function isProcessRunning($pid, $name = '')
    {
        if ('' !== (string)$name) {
            $name = "| grep -i '{$name}'";
        }
        $command = "/usr/bin/env ps -p {$pid} -o args= {$name}";
        $result  = `{$command}`;
        return (bool)$result;
    }

    /**
     * Rename process name
     *
     * @param string $prefix Prefix for the process name
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

}
