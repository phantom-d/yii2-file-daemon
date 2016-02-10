<?php

namespace phantomd\filedaemon;

use yii\base\ErrorException;
use yii\helpers\FileHelper;
use yii\httpclient\Client;

/**
 * FileProcessing
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class FileProcessing extends \yii\base\Component
{

    /**
     * @var phantomd\filedaemon\db\Connection Database manager
     */
    protected static $adapter = null;

    /**
     * @var mixed List or one mime types for controll files
     */
    protected static $mimeType = null;

    /**
     * @var array Daemon configuration
     */
    public $config = null;

    /**
     * @var array Options for the curl
     */
    public $curlOptions = [
        CURLOPT_USERAGENT      => 'Yii2 file daemon',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ];

    /**
     * Initialization
     */
    public function init()
    {
        parent::init();

        if (empty($this->config['db'])) {
            $message = 'Incorrect param `db`!';
            \Yii::error(PHP_EOL . $message, __METHOD__ . '(' . __LINE__ . ')');
            throw new InvalidParamException($message);
        }

        $params = [
            'class'  => __NAMESPACE__ . '\db\Connection',
            'params' => $this->config['db'],
        ];

        static::$adapter = \Yii::createObject($params);
    }

    /**
     * Calls the named method which is not a class method.
     * Call this method directly from database manager [[FileProcessing::$adapter]]
     */
    public function __call($name, $params)
    {
        return call_user_func_array([static::$adapter, $name], $params);
    }

    /**
     * Get object HttpClient
     *
     * @return \phantomd\filedaemon\components\Curl Component
     */
    public function getWebClient()
    {
        $params = [
            'class' => __NAMESPACE__ . '\components\Curl',
        ];

        $client = \Yii::createObject($params)
            ->setOptions($this->curlOptions);

        return $client;
    }

    /**
     * Request file from url
     *
     * @param string $url Url for request
     * @param string $method HTTP request method
     * @param array $data Data to send
     * @return \phantomd\filedaemon\components\Curl
     */
    public function sendRequest($url, $method = 'get', $data = [])
    {
        $return       = false;
        $sanitizedUrl = filter_var($url, FILTER_SANITIZE_URL);
        try {
            $webClient = $this->getWebClient();
            if ($data) {
                $data = http_build_query($data);
                if ('post' === $method) {
                    $webClient->setOption(CURLOPT_POSTFIELDS, $data);
                }
                if ('get' === $method) {
                    $sanitizedUrl .= (mb_strpos($sanitizedUrl, '?') ? '&' : '?') . $data;
                }
            }
            if (method_exists($webClient, $method)) {
                if ($webClient->$method($sanitizedUrl)) {
                    $return = $webClient;
                } else {
                    $error = $webClient->error;
                    if (empty($error)) {
                        if (isset(\yii\web\Response::$httpStatuses[$webClient->code])) {
                            $error = \yii\web\Response::$httpStatuses[$webClient->code];
                        }
                    }
                    $message = "\nCurl error:" . PHP_EOL
                        . "code: {$webClient->code}" . PHP_EOL
                        . "error: {$error}" . PHP_EOL
                        . "errNo: {$webClient->errNo}" . PHP_EOL
                        . "info: " . print_r($webClient->info, true);
                    \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        } catch (\Exception $e) {
            \Yii::error(PHP_EOL . "Error URL: {$url}.\n{$e->getMessage()}", __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Add sources to database
     *
     * @param string $name Job name
     * @param array $params Data
     * @return boolean
     */
    public function addSource($name, $params)
    {
        $return = false;
        $count  = 0;

        if (empty($params[0])) {
            $params = array($params);
        }

        foreach ($params as $item) {
            $result = $item;

            $result['score'] = isset($item['score']) ? (int)$item['score'] : 0;
            $result['name']  = $name;

            $model = $this->sourceModel($result);

            if ($model->save()) {
                ++$count;
            } else {
                \Yii::warning($model->getErrors(), __METHOD__ . '(' . __LINE__ . ')');
            }
        }

        if ($count === count($params)) {
            $return = true;
        }

        return $return;
    }

    /**
     * Create jobs
     *
     * @return bool
     */
    public function addJobs()
    {
        $return = false;
        if ($result = $this->sourceNames()) {
            $jobsId = [];
            foreach ($result as $source) {
                $createJob = false;
                $callback  = false;

                YII_DEBUG && \Yii::info($source, __METHOD__ . '(' . __LINE__ . ') --- $source');

                if ($job = $this->jobsOne(['name' => $source])) {
                    $callback = $job->callback;
                    if ($job::STATUS_COMPLETE === (int)$job->status) {
                        $job->status = $job::STATUS_PREPARE;
                        $job->save();
                    }
                    if ($job::STATUS_ERROR === (int)$job->status) {
                        $job->status = $job::STATUS_PREPARE;
                        $job->save();
                    }

                    if ($job::STATUS_PREPARE === (int)$job->status) {
                        $createJob = true;
                    }
                }

                if (empty($callback)) {
                    $group = explode('::', $source)[0];
                    if (isset($this->config['callback'][$group])) {
                        $callback  = $this->config['callback'][$group];
                        $createJob = true;
                    }
                }

                if (YII_DEBUG) {
                    \Yii::info([$createJob], __METHOD__ . '(' . __LINE__ . ') --- $createJob');
                    \Yii::info([$callback], __METHOD__ . '(' . __LINE__ . ') --- $callback');
                }

                if ($createJob && $id = $this->addJob($source, $callback)) {
                    $jobsId[] = $id;
                } else {
                    if (empty($callback)) {
                        if ($model = $this->sourceOne($source)) {
                            $model->remove();
                        }
                    }
                }
            }

            if ($jobsId) {
                $jobs = $this->jobsAll($jobsId);
                if ($jobs) {
                    foreach ($jobs as $job) {
                        if ($job::STATUS_PREPARE === (int)$job->status) {
                            $job->status = $job::STATUS_WAIT;
                            $job->save();
                        }
                    }
                }
                $return = true;
            }
        }
        return $return;
    }

    /**
     * Create job
     *
     * @param string $name Job name
     * @param string $callback Callback url
     * @param int $status Default status for new job
     * @return boolean
     */
    public function addJob($name, $callback, $status = 0)
    {
        $return = false;

        if (empty($callback)) {
            if ($source = $this->sourceOne($name)) {
                $source->remove();
            }
            return $return;
        }

        $total = $this->sourceCount($name);

        if ($total) {
            $jobsModel = $this->jobsModel();
            $params    = [
                'name'     => $name,
                'callback' => $callback,
                'status'   => ($this->checkSorceAccess($name) ? $status : $jobsModel::STATUS_ERROR),
                'complete' => 0,
                'total'    => $total,
            ];

            if ($job = $jobsModel->one(['name' => $name])) {
                if ($job->statusWork) {
                    $params['status'] = $job->status;
                }
                $job->setAttributes($params);
            } else {
                $params['time_create'] = time();

                $job = $this->jobsModel($params);
            }
            if ($job->save()) {
                $job->refresh();
                $return = $job->id;
            }
        }
        return $return;
    }

    /**
     * Check the availability of the source by url
     *
     * @param string $name Job name
     * @return bool
     */
    public function checkSorceAccess($name)
    {
        $return = false;
        $item   = $this->sourceOne($name);
        if ($item && $this->sendRequest($item->url, 'head')) {
            $return = true;
        }
        return $return;
    }

    /**
     * Make file name from url
     *
     * @param string $url Source url
     * @return mixed The new file name, url and extension
     */
    public function getFileName($url)
    {
        YII_DEBUG && \Yii::info(PHP_EOL . $url, __METHOD__ . '(' . __LINE__ . ')');

        $return = false;

        if ($response = $this->sendRequest($url, 'head')) {
            \Yii::info(PHP_EOL . "URL: {$url}", __METHOD__ . '(' . __LINE__ . ')');

            if ($this->checkContentType($response->info['content_type'])) {
                $return = [
                    'url'       => $url,
                    'file'      => md5($url . $response->info['download_content_length']),
                    'extension' => pathinfo($response->getOption(CURLOPT_URL), PATHINFO_EXTENSION),
                ];
            }
        }
        return $return;
    }

    /**
     * Download file from url
     *
     * @param string $url Url
     * @param string $file Full path file to save
     * @return mixed Full path to downloaded file
     */
    public function getFile($url, $file)
    {
        if (YII_DEBUG) {
            $args = func_get_args();
            \Yii::info(PHP_EOL . 'getFile $args: ' . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }

        $return = false;

        if ($file && $response = $this->sendRequest($url)) {
            \Yii::info(PHP_EOL . "URL: {$url}", __METHOD__ . '(' . __LINE__ . ')');
            if ($this->checkContentType($response->info['content_type'])) {
                if (file_put_contents($file, $response->response)) {
                    $return = $file;
                } else {
                    \Yii::error(PHP_EOL . "Could not save file: {$file}", __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        }
        return $return;
    }

    /**
     * Check mime type
     *
     * @param string $contentType Mime type
     * @return boolean
     */
    public function checkContentType($contentType = '')
    {
        $return = true;
        if ($contentType && static::$mimeType) {
            $return = false;
            if (is_array(static::$mimeType)) {
                foreach (static::$mimeType as $value) {
                    if (false !== mb_strpos($contentType, $value)) {
                        $return = true;
                        break;
                    }
                }
            } else {
                if (false !== mb_strpos($contentType, static::$mimeType)) {
                    $return = true;
                }
            }
        }

        if (false === $return) {
            $message = "Incorrect mime type: " . implode(',', (array)static::$mimeType);
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
        }

        return $return;
    }

    /**
     * Sending processing results from not active tasks
     */
    public function transferResults()
    {
        $separator = '::';

        if ($names = $this->resultNames(['separator' => $separator])) {
            foreach ($names as $name) {
                $id  = end($name);
                if ($job = $this->jobsOne($id)) {
                    if ($job->status && false === $job->statusWork) {
                        try {
                            $this->transfer($id);
                        } catch (\Exception $e) {
                            \Yii::error(PHP_EOL . $e->getMessage(), __METHOD__ . '(' . __LINE__ . ')');
                        }
                    }
                } else {
                    $model = $this->resultModel(implode($separator, $name));
                    $model->remove();
                }
            }
        }
        YII_DEBUG && \Yii::info([$names], __METHOD__ . '(' . __LINE__ . ') --- $names');
    }

    /**
     * Send processing results
     *
     * @param string $id Task ID
     * @return boolean
     */
    public function transfer($id)
    {
        \Yii::info(PHP_EOL . 'Do transfer - start!', __METHOD__ . '(' . __LINE__ . ')');
        $return = false;
        if (false === empty($id)) {
            $job = $this->jobsOne($id);
            if ($job) {
                YII_DEBUG && \Yii::info($job->toArray(), __METHOD__ . '(' . __LINE__ . ') --- $job');
                $name  = $job->group . '::' . $id;
                $total = $this->resultCount($name);
                $empty = true;
                $page  = 0;

                while ($result = $this->resultAll($name, 100, $page++)) {
                    $empty = false;
                    $data  = [];
                    foreach ($result as $model) {
                        $data[] = [
                            'command'   => $model->command,
                            'object_id' => $model->object_id,
                            'url'       => $model->time_dir . DIRECTORY_SEPARATOR . $model->file_name,
                            'file_id'  => $model->file_id,
                        ];
                    }

                    YII_DEBUG && \Yii::info($data, __METHOD__ . '(' . __LINE__ . ') --- $data');
                    if ($data) {
                        if ($response = $this->sendRequest($job->callback, 'post', ['data' => $data])) {
                            $message = "\n\tSend data successful!\n\t\t"
                                . "table: {$name},\n\t\t"
                                . "total: {$total},\n\t\t"
                                . "sended: " . count($data) . ",\n\t\t"
                                . "page: {$page}";
                            \Yii::info($message, __METHOD__ . '(' . __LINE__ . ')');
                            $return  = true;
                        }
                    }
                }
                if ($empty) {
                    $return = true;
                    \Yii::info(PHP_EOL . 'Empty table: ' . $name, __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        } else {
            \Yii::error(PHP_EOL . 'Transfer error: Incorrect job ID: ' . $id, __METHOD__ . '(' . __LINE__ . ')');
        }

        if ($return) {
            \Yii::info(PHP_EOL . 'Delete table: ' . $name, __METHOD__ . '(' . __LINE__ . ')');
            if ($model = $this->resultModel($name)) {
                \Yii::info($model->toArray(), __METHOD__ . '(' . __LINE__ . ') --- $model');
                $model->remove();
            }
            $job->delete();
        }

        \Yii::info(PHP_EOL . 'Do transfer - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

    /**
     * Save file
     *
     * @param array $params Data for saving
     *
     * ```php
     * $param = [
     *     'source'        => 'temp_file',
     *     'source_delete' => true,
     *     'file'          => 'target_file',
     *     'directories'   => [
     *         'source' => '@app/temp/',
     *         'target' => '@app/../uploads/',
     *     ],
     *     'extension'     => 'pdf',
     * ];
     * ```
     *
     * @return boolean
     */
    public function makeFile($params = [])
    {
        YII_DEBUG && \Yii::info($params, __METHOD__ . '(' . __LINE__ . ')');

        $return = false;
        if (empty($params)) {
            return $return;
        }

        $source = $params['source'];
        if (false === is_file($source)) {
            $source = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $params['directories']['source'] . DIRECTORY_SEPARATOR
                        . basename($params['source'])
                    )
            );
        }

        if (is_file($source)) {
            $target = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $params['directories']['target'] . DIRECTORY_SEPARATOR
                        . basename($params['file']) . '.' . $params['extension']
                    )
            );
            if ($return = copy($source, $target)) {
                if (false === empty($params['source_delete'])) {
                    unlink($source);
                }
            }
        }
        return $return;
    }

}
