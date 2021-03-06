<?php

namespace phantomd\filedaemon\console\controllers;

use yii\helpers\FileHelper;
use phantomd\filedaemon\common\controllers\StreakDaemonController;

/**
 * Class FileDaemonController. Base file deamon controller for the extension.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class FileDaemonController extends StreakDaemonController
{

    /**
     * @var array Array threading jobs
     */
    protected static $jobListData = [];

    /**
     * @var array Array source item for processing
     */
    protected $itemData = [];

    /**
     * @var array Result for save
     */
    protected $itemResult = [];

    /**
     * @inheritdoc
     */
    public $isMultiInstance = true;

    /**
     * @inheritdoc
     */
    public $maxChildProcesses = 400;

    /**
     * @inheritdoc
     */
    public $maxThreads = 3;

    /**
     * @inheritdoc
     */
    public $sleep = 60;

    /**
     * Clear current jobs threads
     */
    protected function checkJoblist()
    {
        foreach (static::$jobListData as $key => $jobId) {
            if (false === $this->checkJob($jobId)) {
                unset(static::$jobListData[$key]);
            }
        }
    }

    /**
     * Check job running
     *
     * @param string|object $id Job id or Jobs::model()
     * @return boolean
     */
    protected function checkJob($id)
    {
        $exist = false;
        if (empty($id)) {
            return $exist;
        }

        if (is_object($id) && $id instanceof \phantomd\filedaemon\db\ActiveInterface) {
            $job = $id;
        } else {
            $job = static::$component->jobsOne($id);
        }

        if ($job && $job->pid) {
            $name = $this->getProcessName() . '_' . $job->name;
            if ($this->isProcessRunning($job->pid, $name)) {
                $exist = true;
            }
        }
        return $exist;
    }

    /**
     * Force stop daemon threads
     */
    protected static function beforeStop()
    {
        \Yii::info('Do restart - start!', __METHOD__ . '(' . __LINE__ . ')');
        foreach (static::$jobListData as $id) {
            $keys = 0;
            $job  = static::$component->jobsOne($id);
            if ($job) {
                $job->status = $job::STATUS_RESTART;
                $job->save();
            }
        }
        \Yii::info('Do restart - end!', __METHOD__ . '(' . __LINE__ . ')');
    }

    /**
     * @inheritdoc
     */
    protected function defineJobs()
    {

        \Yii::info('Define jobs - start!', __METHOD__ . '(' . __LINE__ . ')');

        $return = [];

        if ($this->restart()) {
            return $return;
        }

        if (static::$component->addJobs()) {
            \Yii::info('Created new jobs.', __METHOD__ . '(' . __LINE__ . ')');
        }

        static::$component->transferResults();
        YII_DEBUG && \Yii::info('Transfer results done.', __METHOD__ . '(' . __LINE__ . ')');

        $this->checkJoblist();
        YII_DEBUG && \Yii::info('Check joblist done.', __METHOD__ . '(' . __LINE__ . ')');

        $jobs         = [];
        $threads      = [];
        $maxProcesses = (int)static::$config['child-processes'];
        $limitCurrent = 0;

        if (count(static::$jobListData) < $maxProcesses && $groups = static::$component->sourceGroups()) {
            $limitProcesses = $maxProcesses - count(static::$jobListData);
            $limitTreads    = $this->maxThreads;
            $limit          = $limitTreads;

            YII_DEBUG && \Yii::info([$groups], __METHOD__ . '(' . __LINE__ . ') --- $groups');

            foreach ($groups as $group) {
                if ($limitCurrent < $limitProcesses) {
                    $diff = $limitProcesses - $limitCurrent;
                    if ($diff < $limitTreads) {
                        $limit = $diff;
                    }
                } else {
                    break;
                }

                $jobsModel = static::$component->jobsModel();
                $where     = [
                    'group'  => $group,
                    'status' => [
                        $jobsModel::STATUS_WAIT,
                        $jobsModel::STATUS_WORK,
                        $jobsModel::STATUS_RESTART,
                    ]
                ];

                $result = $jobsModel->all($where, $limit);

                YII_DEBUG && \Yii::info([$result], __METHOD__ . '(' . __LINE__ . ') --- $jobs');
                if ($result) {
                    foreach ($result as $job) {
                        $threads[$job->group][] = $job->id;
                    }

                    $jobs = array_merge($jobs, $result);
                    $limitCurrent += count($result);
                }
            }
        }

        $jobsModel = static::$component->jobsModel();

        $page  = 0;
        $where = [
            'status' => [
                $jobsModel::STATUS_WAIT,
                $jobsModel::STATUS_WORK,
                $jobsModel::STATUS_RESTART,
                $jobsModel::STATUS_COMPLETE,
                $jobsModel::STATUS_ERROR,
            ]
        ];

        while ($result = $jobsModel->all($where, 100, $page++)) {
            foreach ($result as $model) {
                $jobId    = $model->id;
                $jobGroup = $model->group;

                if (false === isset($threads[$jobGroup])) {
                    $threads[$jobGroup] = [];
                }

                if ($model->statusWork) {
                    if ($model->complete === $model->total) {
                        $model->status = $model::STATUS_COMPLETE;
                    } else {
                        $source = static::$component->sourceOne($model->name);
                        if (empty($source)) {
                            $model->status = $model::STATUS_ERROR;
                        }
                    }
                    $model->save();
                }

                // Очистка контейнера со списком потоков и удаление потоков
                if (false === $this->checkJob($model) && false === $model->statusWork) {
                    static::$component->transfer($jobId);
                    if (false !== ($delete = array_search($jobId, static::$jobListData))) {
                        unset(static::$jobListData[$delete]);
                    }

                    if ($model::STATUS_RESTART === (int)$model->status) {
                        $model->status = $model::STATUS_WAIT;
                        $model->save();

                        if (false !== ($search = array_search($jobId, $threads[$jobGroup]))) {
                            $threads[$jobGroup][$search] = $model;
                        }
                    } else {
                        if (false !== ($delete = array_search($jobId, $threads[$jobGroup]))) {
                            unset($threads[$jobGroup][$delete]);
                        }
                    }
                }
            }
        }

        YII_DEBUG && \Yii::info([static::$jobListData], __METHOD__ . '(' . __LINE__ . ') --- static::$jobListData');

        if ($jobs) {
            foreach ($jobs as $job) {
                if (count(static::$jobListData) >= $maxProcesses) {
                    break;
                }
                $jobId    = $job->id;
                $jobGroup = $job->group;

                $countJobs = 0;

                if (false === empty($threads[$jobGroup])) {
                    foreach ($threads[$jobGroup] as $value) {
                        if (in_array($value, static::$jobListData)) {
                            ++$countJobs;
                        }
                    }
                }

                // Добавление задач в контейнер потоков
                if ($countJobs < (int)static::$config['max-threads']) {
                    if ($this->checkJob($jobId)) {
                        if (false === in_array($jobId, static::$jobListData)) {
                            static::$jobListData[] = $jobId;
                        }
                    } else {
                        $return[] = $jobId;
                        if (in_array($jobId, static::$jobListData)) {
                            static::$component->transfer($jobId);
                        } else {
                            static::$jobListData[] = $jobId;
                        }
                    }
                }
            }
        }

        if (empty(static::$jobListData)) {
            $path  = \Yii::getAlias(static::$config['directories']['source']);
            $files = FileHelper::findFiles($path, ['except' => ['\.gitignore']]);
            if ($files) {
                foreach ($files as $file) {
                    if (false === is_dir($file) && 0 === (int)filesize($file)) {
                        unlink($file);
                    }
                }
            }
        }

        \Yii::info('Define jobs - end!', __METHOD__ . '(' . __LINE__ . ')');

        return $return;
    }

    /**
     * @inheritdoc
     */
    protected function doJob($job)
    {
        $jobModel = static::$component->jobsOne($job);

        if ($jobModel && $jobModel->statusWork) {
            $jobModel->pid = getmypid();

            $this->shortName = $this->shortName . '_' . $jobModel->name;
            $this->initLogger();
            $this->renameProcess();

            \Yii::info('Do job - start("' . $job . '")! PID: ' . $jobModel->pid, __METHOD__ . '(' . __LINE__ . ')');

            $jobModel->status = $jobModel::STATUS_WORK;
            $jobModel->save();

            $doJob = $jobModel->complete < $jobModel->total;

            while ($doJob) {
                $doJob = $this->doThread($jobModel);

                $doJob = $doJob && $jobModel && $jobModel->statusWork;

                if (YII_DEBUG) {
                    \Yii::info([$doJob], __METHOD__ . '(' . __LINE__ . ') --- $doJob');
                    $message = ($jobModel ? $jobModel->toArray() : [$jobModel]);
                    \Yii::info($message, __METHOD__ . '(' . __LINE__ . ') --- $job');
                }

                if (false === $doJob || $jobModel->complete === $jobModel->total) {
                    $params = [
                        'time_end' => microtime(true),
                    ];

                    if ($jobModel->complete === $jobModel->total) {
                        $params['status'] = $jobModel::STATUS_COMPLETE;
                    }

                    $jobModel->setAttributes($params);
                    $jobModel->save();

                    $doJob = false;
                } else {
                    $doJob = true;
                }
            }

            static::$component->transfer($job);
        }

        \Yii::info('Do job - end ("' . $job . '")! PID: ' . $jobModel->pid, __METHOD__ . '(' . __LINE__ . ')');
        return true;
    }

    /**
     * Job thread
     *
     * @param object $job Jobs::model()
     * @return true
     */
    protected function doThread($job)
    {
        $return = false;
        if (empty($job)) {
            return $return;
        }

        $jobId       = $job->id;
        $jobTotal    = $job->total;
        $jobErrors   = $job->errors;
        $jobComplete = $job->complete;

        $startItem   = microtime(true);
        $sourceTotal = static::$component->sourceCount($job->name);

        if ($sourceTotal > $jobTotal) {
            $jobTotal = $sourceTotal;
        }

        // * Start file processing
        if ($item = static::$component->sourceOne($job->name, true)) {
            YII_DEBUG && \Yii::info(print_r($item->toArray(), true), __METHOD__ . '(' . __LINE__ . ') --- $item');

            if (false === $this->doFile($item, $job->group . '::' . $jobId)) {
                ++$jobErrors;
            }
            ++$jobComplete;

            $timePerItem = microtime(true) - $startItem;

            $jobTimeElapsed = $job->time_elapsed + $timePerItem;
            $jobTimePerItem = $jobTimeElapsed / $jobComplete;
            $job->setAttributes([
                'complete'      => $jobComplete,
                'errors'        => $jobErrors,
                'time_elapsed'  => $jobTimeElapsed,
                'time_per_item' => $jobTimePerItem,
                'time_to_left'  => ($jobTotal - $jobComplete) * $jobTimePerItem,
            ]);
            $job->save();
        }
        // * End file processing

        if (empty($item)) {
            if ($jobComplete > $jobTotal) {
                $job->complete = $jobTotal;
                $job->errors   = $jobErrors + ($jobTotal - $jobComplete);
            }
            $job->status = $job::STATUS_COMPLETE;
            $job->save();
        }

        $return = $jobComplete < $jobTotal // Количество обработанных записей задачи
            && $job::STATUS_WORK === (int)$job->status; // Статус задачи

        if (empty($job) || false === $job->statusWork) {
            $return = false;
        }

        return $return;
    }

    /**
     * Source processing
     *
     * @param mixed $item Source data
     * @param string $table Result name
     * @return bool
     */
    protected function doFile($item, $table)
    {
        \Yii::info('Do file - start!', __METHOD__ . '(' . __LINE__ . ')');

        $return = false;
        $path   = null;

        if (empty($item) || empty($table)) {
            $args = func_get_args();
            \Yii::warning($args, __METHOD__ . '(' . __LINE__ . ') --- Incorrect arguments');
            return $return;
        }

        YII_DEBUG && \Yii::info(print_r($item->toArray(), true), __METHOD__ . '(' . __LINE__ . ') --- $item');

        if ($file = static::$component->getFileName($item->url)) {

            YII_DEBUG && \Yii::info([$file], __METHOD__ . '(' . __LINE__ . ') --- $file');

            $command = null;
            if (isset($this->commands[(int)$item->command])) {
                $command = $this->commands[(int)$item->command];
            }

            $fileName = md5($item->object_id . $file['file']);
            $tmpName  = tempnam(\Yii::getAlias(static::$config['directories']['source']), $this->configName);

            if ($arcresult = static::$component->arcresultOne($fileName)) {
                $path = $arcresult->path;
            }

            if (YII_DEBUG) {
                $message = $arcresult ? $arcresult->toArray() : [$path];
                \Yii::info($message, __METHOD__ . '(' . __LINE__ . ') --- $arcresult');
            }

            $this->itemData = [
                'table'         => $table,
                'source'        => $tmpName,
                'source_delete' => true,
                'file'          => $fileName,
                'url'           => $file['url'],
                'command'       => (int)$item->command,
                'file_id'       => $item->file_id,
                'object_id'     => $item->object_id,
                'score'         => $item->score,
                'directories'   => static::$config['directories'],
                'extension'     => isset(static::$config['extension']) ? static::$config['extension'] : '',
                'quality'       => (int)static::$config['quality'],
                'targets'       => static::$config['targets'],
            ];

            YII_DEBUG && \Yii::info([$this->itemData], __METHOD__ . '(' . __LINE__ . ') $this->itemData');

            if ($command) {
                $method = __FUNCTION__ . \yii\helpers\Inflector::id2camel($command);
                if (method_exists($this, $method)) {
                    \Yii::info("Do file `{$command}` - start!", __METHOD__ . '(' . __LINE__ . ')' . "\n");
                    $this->{$method}($path);
                    \Yii::info("Do file `{$command}` - end!", __METHOD__ . '(' . __LINE__ . ')' . "\n");
                }
            }
            $this->doMerge();
            $return = $this->makeFile($path);
        }

        \Yii::info('Do file - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

    /**
     * Add/remove data for processing
     */
    public function doMerge()
    {
        \Yii::info('Do merge - start!', __METHOD__ . '(' . __LINE__ . ')');
        $command = false;
        if (isset($this->commands[$this->itemData['command']])) {
            $command = $this->commands[$this->itemData['command']];
        }

        $targets = [];
        $exclude = [];
        // Добавление и сортировка параметров обработки записи
        if ($command &&
            isset(static::$config['commands']) &&
            false === empty(static::$config['commands'][$command])) {

            if (isset(static::$config['commands'][$command]['targets'])) {
                $targets = static::$config['commands'][$command]['targets'];
                if (false === empty($targets['exclude'])) {
                    $exclude = $targets['exclude'];
                    unset($targets['exclude']);
                }
            }
        }

        if ($exclude) {
            foreach ($exclude as $key) {
                if (isset($this->itemData['targets'][$key])) {
                    unset($this->itemData['targets'][$key]);
                }
            }
        }

        if ($targets) {
            $this->itemData['targets'] += $targets;
            if (isset(static::$config['commands'][$command]['method']['merge'])) {
                if (empty(static::$config['commands'][$command]['method']['merge'])) {
                    $this->itemData['targets'] = $targets;
                }
            }
        }

        \Yii::info('Do merge - end!', __METHOD__ . '(' . __LINE__ . ')');
    }

    /**
     * File processing
     *
     * @param string $path Path from archive database
     * @return boolean
     */
    protected function makeFile($path = null)
    {
        \Yii::info('Make file - start!', __METHOD__ . '(' . __LINE__ . ')');
        $return = false;
        $make   = true;

        // Контроль наличия файла в архивной базе
        if (false === empty($path) &&
            (false === isset(static::$config['archive']) || (bool)static::$config['archive'])) {

            $filePath = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $this->itemData['directories']['target'] . $path
                    )
            );

            YII_DEBUG && \Yii::info([$filePath], __METHOD__ . '(' . __LINE__ . ')');

            $make = false;
            $file = $filePath . '.' . $this->itemData['extension'];

            if (YII_DEBUG) {
                $message = 'is_file(' . $file . '): ' . var_export(is_file($file), true);
                \Yii::info($message, __METHOD__ . '(' . __LINE__ . ')');
            }

            if (false === is_file($file)) {
                $make = true;
            }

            if (false === empty($this->itemData['targets'])) {
                foreach ($this->itemData['targets'] as $target) {
                    $file = $filePath . $target['suffix'] . '.' . $this->itemData['extension'];

                    if (YII_DEBUG) {
                        $message = 'is_file(' . $file . '): ' . var_export(is_file($file), true);
                        \Yii::info($message, __METHOD__ . '(' . __LINE__ . ')');
                    }

                    if (false === is_file($file)) {
                        $make = true;
                        break;
                    }
                }

                if ($make) {
                    foreach ($this->itemData['targets'] as $target) {
                        $file = $filePath . $target['suffix'] . '.' . $this->itemData['extension'];
                        is_file($file) && unlink($file);
                    }

                    if ($arcResult = static::$component->arcresultOne($this->itemData['file'])) {
                        $arcResult->delete();
                    }
                } else {
                    $return  = true;
                    $timeDir = dirname($path);

                    $this->itemData['file'] = basename($path);
                }
            }
        }

        YII_DEBUG && \Yii::info([$make], __METHOD__ . '(' . __LINE__ . ') --- $make');

        // Обработка файла
        if ($make) {
            if (static::$component->getFile($this->itemData['url'], $this->itemData['source'])) {
                $timeDir = FileHelper::normalizePath(
                        \Yii::getAlias(
                            $this->itemData['directories']['web'] . date('/Y/m/d/H/i')
                        )
                );

                $targetPath = FileHelper::normalizePath(
                        \Yii::getAlias(
                            $this->itemData['directories']['target'] . $timeDir
                        )
                );

                try {
                    $mkdir = FileHelper::createDirectory($targetPath);
                    if ($mkdir) {
                        $this->itemData['directories']['target'] = $targetPath;
                    } else {
                        \Yii::error("Can't create dirrectory: '{$targetPath}'", __METHOD__ . '(' . __LINE__ . ')');
                        return $return;
                    }
                } catch (\Exception $e) {
                    \Yii::error($e->getMessage(), __METHOD__ . '(' . __LINE__ . ')');
                    return $return;
                }

                if (static::$component->makeFile($this->itemData)) {
                    $return = true;
                }
            }
        }

        // Запись результатов
        if ($return) {
            $itemDst = [
                'name'      => $this->itemData['table'],
                'command'   => $this->itemData['command'],
                'file_name' => $this->itemData['file'],
                'file_id'   => $this->itemData['file_id'],
                'object_id' => $this->itemData['object_id'],
                'time_dir'  => $timeDir,
                'score'     => $this->itemData['score'],
            ];

            YII_DEBUG && \Yii::info([$itemDst], __METHOD__ . '(' . __LINE__ . ') --- $itemDst');

            $resultModel = static::$component->resultModel($itemDst);
            if ($resultModel->save()) {
                if ($make) {
                    YII_DEBUG && \Yii::info([static::$config['archive']], __METHOD__ . '(' . __LINE__ . ')--- archive');
                    if (false === isset(static::$config['archive']) || (bool)static::$config['archive']) {
                        $data = [
                            'name' => $itemDst['file_name'],
                            'path' => $itemDst['time_dir'] . DIRECTORY_SEPARATOR . $itemDst['file_name'],
                        ];

                        $arcresultModel = static::$component->arcresultModel($data);
                        $arcresultModel->save();
                    }
                }
            } else {
                if (YII_DEBUG) {
                    \Yii::info($resultModel->toArray(), __METHOD__ . '(' . __LINE__ . ') --- $resultModel');
                    \Yii::info($resultModel->getErrors(), __METHOD__ . '(' . __LINE__ . ') --- $resultModel');
                }
            }
        }

        \Yii::info('Make file - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

}
