<?php

namespace phantomd\filedaemon\commands;

use yii\base\ErrorException;
use yii\helpers\FileHelper;
use app\models\Joblist;

class ImageDaemonController extends FileDaemonController
{

    use phantomd\filedaemon\traits\DaemonTrait;

    const TABLE_ARC = 'jobsArc';

    /**
     * @var array Массив задач с установленным количеством потоков
     */
    private $jobListData = [];

    /**
     * @var string Короткое наименование для конфигурации
     */
    private $_shortName = '';

    /**
     * @var array Массив с данными для обработки одной записи
     */
    protected $itemData = [];

    /**
     * @var array Массив с данными для записи результата в базу данных
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
    public $sleep = 60;

    /**
     * Очистка текущего списка задач
     */
    protected function checkJoblist()
    {
        foreach ($this->jobListData as $key => $jobId) {
            if (false === $this->checkJob($jobId)) {
                unset($this->jobListData[$key]);
            }
        }
    }

    /**
     * Проверка наличия процесса
     * 
     * @param string $id
     * @return boolean
     */
    protected function checkJob($id)
    {
        $exist = false;
        if (empty($id)) {
            return $exist;
        }
        $job = Joblist::chooseJob($id);
        if ($job && $job->pid) {
            $name = $this->getProcessName() . '_' . $job->name;
            if ($this->isProcessRunning($job->pid, $name)) {
                $exist = true;
            }
        }
        return $exist;
    }

    /**
     * Отправка результатов обработки не активных задач
     */
    protected function transferResults()
    {
        $tables = \Yii::$app->images->getTables('*', $this->config['db']['result']);

        if ($tables) {
            foreach ($tables as $table) {
                $jobId  = '';
                $params = explode('::', $table);
                $count = count($params);

                if (1 < $count) {
                    $jobId = array_pop($params);
                }

                if ($job = Joblist::chooseJob($jobId)) {
                    if ($job->status && false === $job->statusWork) {
                        $this->doTransfer($jobId);
                    }
                } else {
                    \Yii::$app->images->removeTable($table, $this->config['db']['result']);
                }
            }
        }
    }

    /**
     * Принудительное завершение всех потоков
     */
    protected function doRestart()
    {
        if (is_file(\Yii::getAlias("@app/config/restart-{$this->configName}"))) {
            \Yii::info('Do restart - start!', __METHOD__ . '(' . __LINE__ . ')');
            foreach ($this->jobListData as $jobId) {
                $keys = 0;
                $job  = Joblist::chooseJob($jobId);
                if ($job) {
                    $job->status = Joblist::STATUS_RESTART;
                    $job->save();
                }
            }
            \Yii::info('Do restart - end!', __METHOD__ . '(' . __LINE__ . ')');
        }
        $this->restart();
    }

    /**
     * Получение задачи для выполнения
     *
     * @param array $jobs Массив задач
     * @return string Одна задача
     */
    protected function defineJobExtractor(&$jobs)
    {
        return array_shift($jobs);
    }

    /**
     * Получение списка задач
     *
     * @return array Массив задач с установленным количеством потоков
     */
    protected function defineJobs()
    {
        $this->doRestart();

        \Yii::info('Define jobs - start!', __METHOD__ . '(' . __LINE__ . ')');

        $return = [];

        if (\Yii::$app->images->createJoblist($this->config['db']['source'])) {
            \Yii::info('Created new jobs.', __METHOD__ . '(' . __LINE__ . ')');
        }

        $this->transferResults();

        $jobsQuery = Joblist::find()
            ->where(['status' => Joblist::STATUS_WAIT])
            ->orWhere(['status' => Joblist::STATUS_WORK])
            ->orWhere(['status' => Joblist::STATUS_RESTART]);

        $this->checkJoblist();

        $threads = [];
        $jobs    = $jobsQuery->all();

        if ($jobs) {
            foreach ($jobs as $job) {
                $threads[explode('::', $job->name)[0]][] = $job->id;
            }
        }

        \Yii::trace($this->jobListData, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '--- $this->jobListData');

        if (empty($this->jobListData) || count($this->jobListData) < ((int)$this->config['max-threads'] * count($threads))) {
            if ($jobs) {
                foreach ($jobs as $job) {
                    if (count($this->jobListData) >= ((int)$this->config['max-threads'] * count($threads))) {
                        break;
                    }
                    $jobId   = $job->id;
                    $jobName = explode('::', $job->name)[0];

                    if ($job->statusWork && $job->complete === $job->total) {
                        $job->status = Joblist::STATUS_COMPLETE;
                        $job->save();

                        $job = Joblist::chooseJob($jobId);
                    }

                    // Очистка контейнера со списком потоков и удаление потоков
                    if (false === $job->statusWork) {
                        $this->doTransfer($jobId);
                        if (false !== ($delete = array_search($jobId, $this->jobListData))) {
                            unset($this->jobListData[$delete]);
                        }

                        if (Joblist::STATUS_RESTART === (int)$job->status) {
                            $job->status = Joblist::STATUS_WAIT;
                            $job->save();

                            $job = Joblist::chooseJob($jobId);
                        } else {
                            if (false !== ($delete = array_search($jobId, $threads[$jobName]))) {
                                unset($threads[$jobName][$delete]);
                            }
                            continue;
                        }
                    }

                    $countJobs = 0;

                    if (false === empty($threads[$jobName])) {
                        foreach ($threads[$jobName] as $value) {
                            if (in_array($value, $this->jobListData)) {
                                ++$countJobs;
                            }
                        }
                    }

                    // Добавление задач в контейнер потоков
                    if ($countJobs < (int)$this->config['max-threads']) {
                        if ($this->checkJob($jobId)) {
                            if (false === in_array($jobId, $this->jobListData)) {
                                $this->jobListData[] = $jobId;
                            }
                        } else {
                            $return[] = $jobId;
                            if (in_array($jobId, $this->jobListData)) {
                                $this->doTransfer($jobId);
                            } else {
                                $this->jobListData[] = $jobId;
                            }
                        }
                    }
                }
            }
        }

        \Yii::info('Define jobs - end!', __METHOD__ . '(' . __LINE__ . ')');

        return $return;
    }

    /**
     * Обработка поставленной задачи с ведением статуса выполнения в RedisDB
     *
     * @param string $jobId ID задачи в RedisDB
     */
    protected function doJob($jobId)
    {
        $job = Joblist::chooseJob($jobId);

        if ($job && $job->statusWork) {
            $job->pid = getmypid();

            $this->_shortName = $this->_shortName . '_' . $job->name;
            $this->initLogger();
            $this->renameProcess();

            \Yii::info('Do job - start("' . $jobId . '")! PID: ' . $job->pid, __METHOD__ . '(' . __LINE__ . ')');

            $job->status = Joblist::STATUS_WORK;
            $job->save();

            $job = Joblist::chooseJob($jobId);

            if (empty($job)) {
                return true;
            }

            $doJob = $job->complete < $job->total;

            while ($doJob) {
                $doJob = $this->doThread($job);

                $job = Joblist::chooseJob($jobId);

                \Yii::trace(($job ? $job->toArray() : $job), __METHOD__ . '(' . __LINE__ . ')');

                $doJob = $doJob && $job && $job->statusWork;

                if (false === $doJob || $job->complete === $job->total) {
                    $params = [
                        'time_end' => time(),
                    ];

                    if ($job->complete === $job->total) {
                        $params['status'] = Joblist::STATUS_COMPLETE;
                    }

                    $job = Joblist::chooseJob($jobId);

                    $job->setAttributes($params);
                    $job->save();

                    $doJob = false;
                } else {
                    $doJob = true;
                }
            }

            $this->doTransfer($jobId);
        }

        \Yii::info('Do job - end ("' . $jobId . '")! PID: ' . $job->pid, __METHOD__ . '(' . __LINE__ . ')');
        return true;
    }

    /**
     * Демон потока
     *
     * @param object $job Объект ActiveRecords - Joblist
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
        $sourceTotal = (int)\Yii::$app->images->getCount($job->name, $this->config['db']['source']);

        if ($sourceTotal > $jobTotal) {
            $jobTotal = $sourceTotal;
        }

        // * Start convert an image.
        $item = \Yii::$app->images->getSource(
            $job->name, //
            $this->config['db']['source']
        );

        \Yii::trace($item, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '$item');

        if (is_array($item)) {
            if (false === $this->doFile($item, explode('::', $job->name)[0] . '::' . $jobId)) {
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
            $job = Joblist::chooseJob($jobId);
        }
        // * End convert an image.

        if (true === $item) {
            if ($jobComplete > $jobTotal) {
                $job->complete = $jobTotal;
                $job->errors   = $jobErrors + ($jobTotal - $jobComplete);
            }
            $job->status = Joblist::STATUS_COMPLETE;

            $job->save();
            $job = Joblist::chooseJob($jobId);
        }

        $return = $jobComplete < $jobTotal // Количество обработанных записей задачи
            && Joblist::STATUS_WORK === (int)$job->status; // Статус задачи

        if (empty($job) || false === $job->statusWork) {
            $return = false;
        }

        return $return;
    }

    /**
     * Обработка одной строки данных
     *
     * @param mixed $item Данные для обработки
     * @param string $table Наименование ключа в RedisDB для записи положительного результата
     * @return bool
     */
    protected function doFile($item, $table)
    {
        \Yii::info('Do file - start!', __METHOD__ . '(' . __LINE__ . ')');
        \Yii::trace($item, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '$item');

        $return = false;

        if (empty($item) || empty($table)) {
            $args = func_get_args();
            \Yii::warning($args, __METHOD__ . '(' . __LINE__ . ')' . "\nIncorrect arguments");
            return $return;
        }

        if ($file = \Yii::$app->images->getFileName($item['item']['url'])) {

            \Yii::trace($file, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '$file');

            $type    = $this->config['type'];
            $command = $this->commands[(int)$item['item']['command']];

            $fileName = md5($item['item']['object_id'] . $file['file']);
            $tmpName  = tempnam($this->config['directories']['source'], $type);

            $path = \Yii::$app->images->getArcResult(self::TABLE_ARC, $fileName);

            \Yii::trace($path, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '$path');

            $this->itemData = [
                'table'         => $table,
                'table_arc'     => self::TABLE_ARC,
                'source'        => $tmpName,
                'source_delete' => true,
                'file'          => $fileName,
                'url'           => $file['url'],
                'type'          => $type,
                'command'       => (int)$item['item']['command'],
                'image_id'      => $item['item']['image_id'],
                'object_id'     => $item['item']['object_id'],
                'score'         => $item['score'],
                'directories'   => $this->config['directories'],
                'extension'     => $this->config['extension'],
                'quality'       => (int)$this->config['quality'],
                'targets'       => $this->config['targets'],
            ];

            if (empty($path)) {
                $getFile = \Yii::$app->images->getFile($file['url'], $tmpName, $type);
                \Yii::trace($getFile, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '$getFile');
            }

            $method = __FUNCTION__ . \yii\helpers\Inflector::id2camel($command);

            \Yii::trace($this->itemData, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '$this->itemData');

            if (method_exists($this, $method)) {
                \Yii::info("Do file `{$command}` - start!", __METHOD__ . '(' . __LINE__ . ')' . "\n");
                $this->{$method}($path);
                \Yii::info("Do file `{$command}` - end!", __METHOD__ . '(' . __LINE__ . ')' . "\n");
            }
            $this->doMerge();
            $return = $this->makeFile($path);
        }

        \Yii::info('Do file - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

    /**
     * Добавление/удаление параметров изображений в список
     */
    protected function doMerge()
    {
        \Yii::info('Do merge - start!', __METHOD__ . '(' . __LINE__ . ')');
        $command = false;
        if (isset($this->commands[$this->itemData['command']])) {
            $command = $this->commands[$this->itemData['command']];
        }

        $targets = [];
        $exclude = [];
        // Добавление и сортировка параметров обработки записи
        if (false === empty($this->config['commands'][$command]['targets'])) {
            $targets = $this->config['commands'][$command]['targets'];
            if (false === empty($targets['exclude'])) {
                $exclude = $targets['exclude'];
                unset($targets['exclude']);
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

            if (isset($this->config['commands'][$command]['method']['merge'])) {
                if (empty($this->config['commands'][$command]['method']['merge'])) {
                    $this->itemData['targets'] = $targets;
                }
            }
        }

        if (isset($this->config['commands'][$command]['type'])) {
            $this->itemData['type'] = $this->config['commands'][$command]['type'];
        }
        \Yii::info('Do merge - end!', __METHOD__ . '(' . __LINE__ . ')');
    }

    /**
     * Обработка изображений
     *
     * @param string $path Данные из архивной базы в RedisDB
     * @return boolean
     */
    protected function makeFile($path = null)
    {
        \Yii::info('Make file - start!', __METHOD__ . '(' . __LINE__ . ')');
        $return = false;
        $make   = true;

        // Контроль наличия файла в архивной базе
        if (false === empty($path)) {
            $filePath = FileHelper::normalizePath($this->itemData['directories']['target'] . $path);
            \Yii::trace('$filePath: ' . var_export($filePath, true), __METHOD__ . '(' . __LINE__ . ')');
            if (false === empty($this->itemData['targets'])) {
                $make = false;
                foreach ($this->itemData['targets'] as $target) {
                    $file = $filePath . $target['suffix'] . '.' . $this->itemData['extension'];
                    \Yii::trace('is_file(' . $file . '): ' . var_export(is_file($file), true), __METHOD__ . '(' . __LINE__ . ')');
                    if (false === is_file($file)) {
                        $make = true;
                    }
                }
                if ($make) {
                    foreach ($this->itemData['targets'] as $target) {
                        $file = $filePath . $target['suffix'] . '.' . $this->itemData['extension'];
                        is_file($file) && unlink($file);
                    }
                    \Yii::$app->images->removeTable($this->itemData['table_arc'], $this->config['db']['arc'], $this->itemData['file']);
                    $make = \Yii::$app->images->getFile($this->itemData['url'], $this->itemData['source'], $this->itemData['type']);
                    \Yii::trace('$make: ' . var_export($make, true), __METHOD__ . '(' . __LINE__ . ')');
                } else {
                    $return  = true;
                    $timeDir = dirname($path);

                    $this->itemData['file'] = basename($path);
                }
            }
        }

        // Обработка файла
        if ($make) {
            $timeDir = FileHelper::normalizePath($this->itemData['directories']['web'] . date('/Y/m/d/H/i'));

            $targetPath = FileHelper::normalizePath($this->itemData['directories']['target'] . $timeDir);
            $mkdir      = !(bool)`/usr/bin/env mkdir -m 775 -p '{$targetPath}' 2>&1`;
            if ($mkdir) {
                $this->itemData['directories']['target'] = $targetPath;
            } else {
                \Yii::error("Cant create dirrectory: '{$targetPath}'", __METHOD__ . '(' . __LINE__ . ')');
                return $return;
            }

            if (\Yii::$app->images->convertImage($this->itemData)) {
                $return = true;
            }
        }

        // Запись результатов в RedisDB
        if ($return) {
            $itemDst = [
                'item'  => [
                    'command'   => $this->itemData['command'],
                    'file_name' => $this->itemData['file'],
                    'image_id'  => $this->itemData['image_id'],
                    'object_id' => $this->itemData['object_id'],
                    'time_dir'  => $timeDir,
                ],
                'score' => $this->itemData['score'],
            ];

            \Yii::trace('$itemDst: ' . var_export($itemDst, true), __METHOD__ . '(' . __LINE__ . ')');

            $dbArc = $make ? $this->config['db']['arc'] : null;

            \Yii::$app->images->setResult(
                $this->itemData['table'], //
                $itemDst, //
                $this->config['db']['result'], //
                $dbArc, $this->itemData['table_arc']
            );
        }

        \Yii::info('Make file - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

    /**
     * Отправка результатов обработки задач
     *
     * @param string $jobId ID задачи
     * @return boolean
     */
    protected function doTransfer($jobId)
    {
        \Yii::info('Do transfer - start!', __METHOD__ . '(' . __LINE__ . ')');
        $return = false;
        if (false === empty($jobId)) {
            $job   = Joblist::chooseJob($jobId);
            $table = explode('::', $job->name)[0] . '::' . $jobId;
            if ($job) {
                $resultTotal = (int)\Yii::$app->images->getCount($table, $this->config['db']['result']);
                $page        = 0;

                while ($result = \Yii::$app->images->getResult($table, $this->config['db']['result'], 100, $page++)) {
                    if (true === $result) {
                        $return = true;
                        break;
                    }
                    $data = [];
                    foreach ($result as $value) {
                        $data[] = [
                            'command'   => $value['command'],
                            'object_id' => $value['object_id'],
                            'url'       => $value['time_dir'] . DIRECTORY_SEPARATOR . $value['file_name'],
                            'image_id'  => $value['image_id'],
                        ];
                    }

                    if ($data) {
                        $curl = new \phantomd\filedaemon\components\Curl();
                        $curl->setOption(CURLOPT_POSTFIELDS, http_build_query(['data' => $data]));
                        if ($curl->post($job->callback)) {
                            \Yii::info("Send data successful!\n\tTable: {$table}, total: {$resultTotal}, sended: " . count($data) . ", page: {$page}", __METHOD__ . '(' . __LINE__ . ')');
                            $return = true;
                        } else {
                            $message = "Send data to callback was error!"
                                . "\nresponse: " . var_export($curl->response, true)
                                . "\responseCode: " . var_export($curl->responseCode, true)
                                . "\nresponseError: " . var_export($curl->responseError, true)
                                . "\nresponseInfo: " . var_export($curl->responseInfo, true);
                            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                        }
                    }
                }
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args), __METHOD__ . '(' . __LINE__ . ')');
        }
        if ($return) {
            \Yii::info('Delete table: ' . $table, __METHOD__ . '(' . __LINE__ . ')');
            \Yii::$app->images->removeTable($table, $this->config['db']['result']);
        }
        \Yii::info('Do transfer - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

}
