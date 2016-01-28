<?php

namespace phantomd\filedaemon;

use yii\base\ErrorException;
use phantomd\filedaemon\db\redis\models\Jobs;

/**
 * Компонент для работы
 */
class FileProcessing extends \yii\base\Component
{

    protected static $adapter = null;

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
                    if (Jobs::STATUS_COMPLETE === (int)$job->status) {
                        $job->status = Jobs::STATUS_PREPARE;
                        $job->save();
                    }
                    if (Jobs::STATUS_ERROR === (int)$job->status) {
                        $job->status = Jobs::STATUS_PREPARE;
                        $job->save();
                    }
                    $job->refresh();

                    if (Jobs::STATUS_PREPARE === (int)$job->status) {
                        $createJob = true;
                    }
                }

                if (empty($callback)) {
                    $group = explode('::', $source)[0];
                    if (isset($this->config['callbacks'][$group])) {
                        $callback  = $this->config['callbacks'][$group];
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
                        if (Jobs::STATUS_PREPARE === (int)$job->status) {
                            $job->status = Jobs::STATUS_WAIT;
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
    public function addJob($name, $callback, $status = Jobs::STATUS_PREPARE)
    {
        $return = false;

        if (empty($callback)) {
            return $return;
        }

        $total = $this->sourceCount($name);

        if ($total) {
            $params = [
                'name'     => $name,
                'callback' => $callback,
                'status'   => ($this->checkSorceAccess($name) ? $status : Jobs::STATUS_ERROR),
                'total'    => $total,
            ];

            if ($job = $this->jobsOne(['name' => $name])) {
                if ($job->statusWork) {
                    $params['status'] = $job->status;
                }
                $job->setAttributes($params);
            } else {
                $params['time_create'] = time();

                $job = new Jobs($params);
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
        if (YII_DEBUG) {
            \Yii::trace($url, __METHOD__ . '(' . __LINE__ . ')');
        }

        $return       = false;
        $sanitizedUrl = filter_var($url, FILTER_SANITIZE_URL);

        if ($sanitizedUrl) {
            $curl = new components\Curl();
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
     * @param string $type Тип файла.
     * @return mixed Имя полный путь к полученному файлу | FALSE
     */
    public function getFile($url, $file, $type = 'text')
    {
        if (YII_DEBUG) {
            $args = func_get_args();
            \Yii::trace('getFile $args: ' . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }

        if (empty($type)) {
            $type = 'text';
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
            \Yii::trace($command, __METHOD__ . '(' . __LINE__ . ')');

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

}
