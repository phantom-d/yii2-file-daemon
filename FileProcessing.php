<?php

namespace phantomd\filedaemon;

use yii\base\ErrorException;
use yii\httpclient\Client;

/**
 * Компонент для работы
 */
class FileProcessing extends \yii\base\Component
{

    protected static $adapter = null;

    protected static $http = null;

    protected static $mimeType = null;

    public $config = null;

    public $downloader = "/usr/bin/env wget -T 5 -t 1 -q --no-check-certificate --user-agent='%s' -O '%s' '%s' 2>/dev/null";

    public $curlOptions = [
        CURLOPT_USERAGENT      => 'Yii2 file daemon',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
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

        static::$adapter = new db\Connection(['params' => $this->config['db']]);
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
     * @return object
     */
    public function getHttpClient()
    {
        if (false === is_object(static::$http)) {
            $params = [
                'class'          => Client::className(),
                'requestConfig'  => [
                    'options' => $this->curlOptions,
                    'format'  => Client::FORMAT_RAW_URLENCODED
                ],
                'responseConfig' => [
                    'format' => Client::FORMAT_RAW_URLENCODED
                ],
            ];

            static::$http = \Yii::createObject($params);
        }
        return static::$http;
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
                'total'    => $total,
            ];

            if ($job = $this->jobsOne(['name' => $name])) {
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
        if ($item) {
            $curl = new components\Curl();
            $curl->setOptions($this->curlOptions);
            $curl->head($item->url);

            if ($curl->code) {
                $return = true;
            }
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
        YII_DEBUG && \Yii::trace($url, __METHOD__ . '(' . __LINE__ . ')');

        $return       = false;
        $sanitizedUrl = filter_var($url, FILTER_SANITIZE_URL);

        if ($sanitizedUrl) {
            $curl = new Client;
            foreach ($this->curlOptions as $name => $value) {
                $curl->setOption($name, $value);
            }

            $type = true;

            if (static::$mimeType) {
                $type = false;
                if (is_array(static::$mimeType)) {
                    foreach (static::$mimeType as $value) {
                        if (false !== mb_strpos($curl->info['content_type'], $value)) {
                            $type = true;
                            break;
                        }
                    }
                } else {
                    if (false !== mb_strpos($curl->info['content_type'], static::$mimeType)) {
                        $type = true;
                    }
                }
            }

            if ($curl->head($sanitizedUrl) && $type) {
                $return = [
                    'url'  => $curl->info['url'],
                    'file' => md5($curl->info['url'] . $curl->info['download_content_length']),
                ];
            } else {
                \Yii::error("Could not get file from URL: {$url}", __METHOD__ . '(' . __LINE__ . ')');
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
            \Yii::trace('getFile $args: ' . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }

        $return       = false;
        $sanitizedUrl = filter_var($url, FILTER_SANITIZE_URL);

        if ($sanitizedUrl && $file) {
            $command = sprintf(
                $this->downloader, // Wget
                $this->curlOptions[CURLOPT_USERAGENT], // HTTP User-Agent
                $file, // Полный путь для сохранения файла
                $sanitizedUrl // Ссылка для скачивания
            );

            `{$command}`;
            YII_DEBUG && \Yii::trace($command, __METHOD__ . '(' . __LINE__ . ')');

            if (is_file($file)) {
                $type = true;
                if (static::$mimeType) {
                    $mime = \yii\helpers\FileHelper::getMimeType($file);
                    $type = false;
                    if (is_array(static::$mimeType)) {
                        foreach (static::$mimeType as $value) {
                            if (false !== mb_strpos($mime, $value)) {
                                $type = true;
                                break;
                            }
                        }
                    } else {
                        if (false !== mb_strpos($mime, static::$mimeType)) {
                            $type = true;
                        }
                    }
                }

                if ($type) {
                    $return = $file;
                } else {
                    unlink($file);
                }
            }
        }
        return $return;
    }

    /**
     * Отправка результатов обработки не активных задач
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
     * Отправка результатов обработки задач
     *
     * @param string $id ID задачи
     * @return boolean
     */
    public function transfer($id)
    {
        \Yii::info('Do transfer - start!', __METHOD__ . '(' . __LINE__ . ')');
        $return = false;
        if (false === empty($id)) {
            $job = $this->jobOne($id);
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
                        $curl = new components\Curl;
                        $curl->setOption(CURLOPT_POSTFIELDS, http_build_query(['data' => $data]));
                        if ($curl->post($job->callback)) {
                            $message = "Send data successful!\n\t"
                                . "table: {$name},\n\t"
                                . "total: {$total},\n\t"
                                . "sended: " . count($data) . ",\n\t"
                                . "page: {$page}";
                            \Yii::info($message, __METHOD__ . '(' . __LINE__ . ')');
                            $return  = true;
                        } else {
                            $message = "Send data to callback was error!\n"
                                . "\nresponse: " . var_export($curl->response, true)
                                . "\ncode: " . var_export($curl->code, true)
                                . "\nerror: " . var_export($curl->error, true)
                                . "\ninfo: " . var_export($curl->info, true);
                            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
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

    public function makeFile($params = [])
    {
        return true;
    }

}
