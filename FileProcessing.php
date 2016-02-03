<?php

namespace phantomd\filedaemon;

use yii\base\ErrorException;
use yii\helpers\FileHelper;
use yii\httpclient\Client;

/**
 * Компонент для работы
 * 
 */
class FileProcessing extends \yii\base\Component
{

    /**
     * Database manager
     * @var phantomd\filedaemon\db\Connection 
     */
    protected static $adapter = null;

    /**
     * List or one mime types for controll files
     * @var mixed
     */
    protected static $mimeType = null;

    /**
     * Array of configuration of daemon
     * @var array
     */
    public $config = null;

    /**
     * Options for the curl
     * @var array
     */
    public $curlOptions = [
        CURLOPT_USERAGENT      => 'Yii2 file daemon',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->config['db'])) {
            $message = 'Incorrect param `db`!';
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            throw new InvalidParamException($message);
        }

        $params = [
            'class'  => __NAMESPACE__ . '\db\Connection',
            'params' => $this->config['db'],
        ];

        static::$adapter = \Yii::createObject($params);
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        return call_user_func_array([static::$adapter, $name], $params);
    }

    /**
     * Get object HttpClient
     * @param string $url Url
     * @return object
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
     * @param array $data
     * @return bool|object
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
                    $message = "Curl error:" . PHP_EOL
                        . "code: {$webClient->code}" . PHP_EOL
                        . "error: {$error}" . PHP_EOL
                        . "errNo: {$webClient->errNo}" . PHP_EOL
                        . "info: " . print_r($webClient->info, true);
                    \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        } catch (\Exception $e) {
            \Yii::error("Error URL: {$url}.\n{$e->getMessage()}", __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Запись данных для задач
     *
     * @param string $name Наименование задачи
     * @param array $params Массив данных
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
     * Создание списка задач для работы в соответствии с имеющимися данными в источнике.
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

                YII_DEBUG && \Yii::info($createJob, __METHOD__ . '(' . __LINE__ . ') --- $createJob');
                YII_DEBUG && \Yii::info($callback, __METHOD__ . '(' . __LINE__ . ') --- $callback');

                if ($createJob && $id = $this->addJob($source, $callback)) {
                    $jobsId[] = $id;
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
     * Добавление задачи в Redis DB
     *
     * @param string $name Наименование ключа в RedisDB
     * @param string $callback Ссылка для отправки результатов обработки
     * @param string $db Исходная база данных.
     * @param int $status Статус по умолчанию
     * @return boolean
     */
    public function addJob($name, $callback, $status = 0)
    {
        $return = false;

        if (empty($callback)) {
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
     * Проверка доступности источника по URL для добавления в список задач.
     *
     * @param string $name Наименование задачи
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
     * Получение имени для файла по ссылке
     *
     * @param string $url URL для скачивания
     * @param string $type Тип файла.
     * @return mixed Массив с существующей ссылкой и новым именем для файла | FALSE
     */
    public function getFileName($url)
    {
        YII_DEBUG && \Yii::info($url, __METHOD__ . '(' . __LINE__ . ')');

        $return = false;

        if ($response = $this->sendRequest($url, 'head')) {
            \Yii::info("URL: {$url}", __METHOD__ . '(' . __LINE__ . ')');

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
     * Получение файла по ссылке
     *
     * @param string $url Ссылка для получения файла
     * @param string $file Полный путь для сохранения файла
     * @return mixed Имя полный путь к полученному файлу | FALSE
     */
    public function getFile($url, $file)
    {
        if (YII_DEBUG) {
            $args = func_get_args();
            \Yii::info('getFile $args: ' . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }

        $return = false;

        if ($file && $response = $this->sendRequest($url)) {
            \Yii::info("URL: {$url}", __METHOD__ . '(' . __LINE__ . ')');
            if ($this->checkContentType($response->info['content_type'])) {
                if (file_put_contents($file, $response->response)) {
                    $return = $file;
                } else {
                    \Yii::error("Could not save file: {$file}", __METHOD__ . '(' . __LINE__ . ')');
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
            \Yii::error("Incorrect mime type: " . implode(',', (array)static::$mimeType), __METHOD__ . '(' . __LINE__ . ')');
        }

        return $return;
    }

    /**
     * Sending the results of treatment are not active tasks
     */
    public function transferResults()
    {
        $separator = '::';

        if ($names = $this->resultNames(['separator' => $separator])) {
            foreach ($names as $name) {
                $id  = end($name);
                if ($job = $this->jobsOne($id)) {
                    if ($job->status && false === $job->statusWork) {
                        $this->transfer($id);
                    }
                } else {
                    $model = $this->resultModel(implode($separator, $name));
                    $model->remove();
                }
            }
        }
    }

    /**
     * Send results processing tasks
     *
     * @param string $id ID задачи
     * @return boolean
     */
    public function transfer($id)
    {
        \Yii::info('Do transfer - start!', __METHOD__ . '(' . __LINE__ . ')');
        $return = false;
        if (false === empty($id)) {
            $job = $this->jobsOne($id);
            if ($job) {
                $name  = $job->group . '::' . $id;
                $total = $this->resultCount($name);
                $page  = 0;

                while ($result = $this->resultAll($name, 100, $page++)) {
                    $data = [];
                    foreach ($result as $model) {
                        $data[] = [
                            'command'   => $model->command,
                            'object_id' => $model->object_id,
                            'url'       => $model->time_dir . DIRECTORY_SEPARATOR . $model->file_name,
                            'image_id'  => $model->image_id,
                        ];
                    }

                    if ($data) {
                        if ($response = $this->sendRequest($job->callback, 'post', ['data' => $data])) {
                            $message = "Send data successful!\n\t"
                                . "table: {$name},\n\t"
                                . "total: {$total},\n\t"
                                . "sended: " . count($data) . ",\n\t"
                                . "page: {$page}";
                            \Yii::info($message, __METHOD__ . '(' . __LINE__ . ')');
                            $return  = true;
                        }
                    }
                }
            }
        } else {
            \Yii::error("Incorrect job ID: " . $id, __METHOD__ . '(' . __LINE__ . ')');
        }

        if ($return) {
            \Yii::info('Delete table: ' . $name, __METHOD__ . '(' . __LINE__ . ')');
            $model = $this->resultModel($name);
            $model->remove();
        }

        \Yii::info('Do transfer - end!', __METHOD__ . '(' . __LINE__ . ')');
        return $return;
    }

    /**
     * Save file
     *
     * @param array $params Массив в формате:
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
