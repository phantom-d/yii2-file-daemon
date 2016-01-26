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

    public $config = null;

    public $httpUseragent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36';

    public $downloader = "/usr/bin/env wget -T 5 -t 1 -q --no-check-certificate --user-agent='%s' -O '%s' '%s' 2>/dev/null";

    public $curlOptions = [
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

            $model = static::$adapter->sourceModel($result);
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
     * Запись результатов обработки в RedisDB
     *
     * @param string $table Наименование ключа в RedisDB
     * @param mixed $params Массив (<i>$this->itemDstScheme</i>) вида:
     * 
     * ```php
     * $param = [
     *  'item' => [
     *      'command'   => 0,
     *      'file_name' => '',
     *      'image_id'  => 0,
     *      'object_id' => 0,
     *      'time_dir'  => '',
     *  ],
     *  'score' => 0,
     * ];
     * ```
     * 
     * в массиве обязательными является все данные
     * @param string $db Исходная база данных
     * @param string $dbArc Архивная база данных
     * @param string|FALSE $tableArc Ключ в архивной база данных
     * @return bool
     */
    public function setResult($table, $params, $db = 'redis1', $dbArc = 'redis2', $tableArc = false)
    {
        $return = false;
        if ($table && empty($params['item']) === false) {
            if ($connectionDb = $this->getConnection($db)) {
                if (0 === (int)$connectionDb->exists($table) || 'zset' === $connectionDb->type($table)) {
                    $item    = array_merge($this->itemResultScheme, $params['item']);
                    $prepare = [];
                    foreach ($this->itemResultScheme as $key => $value) {
                        $prepare[$key] = isset($item[$key]) ? $item[$key] : $value;
                    }
                    $params['item'] = $prepare;

                    if (empty($params['item']['file_name']) || empty($params['item']['time_dir']) || empty($params['item']['object_id'])) {
                        \Yii::error("There are no one of required parameters (file_name, time_dir, object_id)!<br>\n" . var_export($result, true), __METHOD__ . '(' . __LINE__ . ')');
                    } else {
                        $return = $this->setZItem($table, $params, $db);
                        if ($dbArc && $tableArc) {
                            if ($connectionArc = $this->getConnection($dbArc)) {
                                if (0 === (int)$connectionArc->exists($tableArc) || 'hash' === $connectionArc->type($tableArc)) {
                                    $connectionArc->hset(
                                        $tableArc, //
                                        $params['item']['file_name'], //
                                        "{$params['item']['time_dir']}/{$params['item']['file_name']}"
                                    );
                                } else {
                                    \Yii::error("Incorrect table '{$tableArc}' type! Must be hash!", __METHOD__ . '(' . __LINE__ . ')');
                                }
                            }
                        }
                    }
                } else {
                    \Yii::error("Incorrect table '{$table}' type! Must be sorted sets!", __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Запись данных в sorted set
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param array $params Массив данных в формате
     * 
     * ```php
     * $param = [
     *  'item' => [],
     *  'score' => 0,
     * ];
     * ```
     * 
     * Содержимое <b>$params['item']</b> кодируется в формать JSON
     * @param string $db База данных в RedisDB
     * @return boolean
     */
    public function setZItem($table, $params, $db)
    {
        $args = func_get_args();
        \Yii::trace('$args: ' . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');

        $return = false;
        if ($table && false === empty($params['item']) && $db) {
            if ($connectionDb = $this->getConnection($db)) {
                if (0 === (int)$connectionDb->exists($table) || 'zset' === $connectionDb->type($table)) {
                    $result = $connectionDb->zadd(
                        $table, //
                        (isset($params['score']) ? (int)$params['score'] : 0), //
                        json_encode($params['item'])
                    );

                    \Yii::trace('$result: ' . var_export($result, true), __METHOD__ . '(' . __LINE__ . ')');

                    $return = true;
                } else {
                    \Yii::error("Incorrect table '{$table}' type! Must be sorted sets!", __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
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
        if ($result = static::$adapter->sourceNames()) {
            $jobsId = [];
            foreach ($result as $source) {
                $createJob = false;
                $callback  = false;

                if ($job = static::$adapter->jobsOne(['name' => $source])) {
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
                $jobs = static::$adapter->jobsAll($jobsId);
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

        $total = static::$adapter->sourceCount($name);

        if ($total) {
            $params = [
                'name'     => $name,
                'callback' => $callback,
                'status'   => ($this->checkSorceAccess($name) ? $status : Jobs::STATUS_ERROR),
                'total'    => $total,
            ];

            if ($job = static::$adapter->jobsOne(['name' => $name])) {
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
        $item   = static::$adapter->sourceOne($name);
        if ($item) {
            $curl = new components\Curl();
            $curl->setOptions($this->curlOptions);
            $curl->head($item->url);

            if ($curl->responseCode) {
                $return = true;
            }
        }
        return $return;
    }

    /**
     * Удаление данных из RedisDB
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $db База данных в RedisDB.
     * @param string $field Наименование поля в указанном ключе (Hash, Set, Sorted set).
     * @return boolean
     */
    public function removeTable($table, $db = 'redis2', $field = null)
    {
        $return = false;
        if ($table) {
            if ($connectionDb = $this->getConnection($db)) {
                $source = false;
                if (empty($field)) {
                    $source = (bool)$connectionDb->del($table);
                } else {
                    $type = $connectionDb->type($table);
                    switch ($type) {
                        case 'hash':
                            $source = (bool)$connectionDb->hdel($table, $field);
                            break;
                        case 'set':
                            $source = (bool)$connectionDb->srem($table, $field);
                            break;
                        case 'zset':
                            $source = (bool)$connectionDb->zrem($table, $field);
                            break;
                        default:
                            \Yii::error("Incorrect table '{$table}' type!<br>\nMust be one of these: hash, set, sorted set!", __METHOD__ . '(' . __LINE__ . ')');
                            break;
                    }
                }
                $return = $source;
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
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
    public function getFileName($url, $type = 'image')
    {
        \Yii::trace(var_export($url, true), __METHOD__ . '(' . __LINE__ . ')');
        if (empty($type)) {
            $type = 'image';
        }

        $return = false;
        if ($url) {
            $curl = new components\Curl();
            foreach ($this->curlOptions as $name => $value) {
                $curl->setOption($name, $value);
            }
            if ($curl->head(filter_var($url, FILTER_SANITIZE_URL)) &&
                false !== strpos($curl->responseInfo['content_type'], $type)) {

                $return = [
                    'url'  => $curl->responseInfo['url'],
                    'file' => md5($curl->responseInfo['url'] . $curl->responseInfo['download_content_length']),
                ];
            } else {
                \Yii::error("Could not get file from URL: {$url}", __METHOD__ . '(' . __LINE__ . ')');
            }
        }
        \Yii::trace(var_export($url, true), __METHOD__ . '(' . __LINE__ . ')');
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
    public function getFile($url, $file, $type = 'image')
    {
        $args = func_get_args();
        \Yii::trace('getFile $args: ' . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');

        if (empty($type)) {
            $type = 'image';
        }

        $return = false;
        if ($url && $file) {
            $command = sprintf(
                $this->downloader, // Wget
                $this->httpUseragent, // HTTP User-Agent
                $file, // Полный путь для сохранения файла
                filter_var($url, FILTER_SANITIZE_URL) // Ссылка для скачивания
            );

            `{$command}`;

            \Yii::trace(var_export($command, true), __METHOD__ . '(' . __LINE__ . ')');
            if (is_file($file)) {
                if (false === mb_strpos(\yii\helpers\FileHelper::getMimeType($file), $type)) {
                    unlink($file);
                } else {
                    $return = $file;
                }
            }
        }
        return $return;
    }

    /**
     * Конвертирование изображения в указанный формат
     * (консольный GraphicsMagick)
     *
     * @param array $params Массив в формате:
     * 
     * ```php
     * $param = [
     *     'source'        => 'test_file',
     *     'source_delete' => true,
     *     'file'          => 'target_file',
     *     'directories'   => [
     *         'source' => '/var/www/temp/',
     *         'target' => '/var/www/uploads/',
     *     ],
     *     'extension'     => 'jpg',
     *     'targets'       => [
     *         'origin' => [
     *             'suffix' => '_o',
     *         ],
     *         'big'    => [
     *             'width'  => 1024,
     *             'suffix' => '_b',
     *         ],
     *         'medium' => [
     *             'height' => 220,
     *             'suffix' => '_m',
     *         ],
     *         'small'  => [
     *             'width'  => 70,
     *             'height' => 51,
     *             'suffix' => '_s',
     *         ],
     *     ],
     * ];
     * ```
     * 
     * @return boolean
     */
    public function convertImage($params)
    {
        \Yii::trace('$params: ' . var_export($params, true), __METHOD__ . '(' . __LINE__ . ')');

        $return = false;
        if (empty($params)) {
            return $return;
        }
        $source = $params['source'];
        if (false === is_file($source)) {
            $source = $params['directories']['source'] . DIRECTORY_SEPARATOR . basename($params['source']);
        }
        $command = "/usr/bin/env gm convert -limit threads 2 '{$source}'";

        if (is_file($source) && false === empty($params['targets'])) {
            $targets      = $this->sortTargets($params['targets']);
            $count        = count($targets);
            $imageQuality = 75;
            if (false === empty($params['quality'])) {
                $imageQuality = (int)$params['quality'];
            }
            $command .= " -quality {$imageQuality} +profile '*' "
                . "-write '{$params['directories']['target']}/{$params['file']}.{$params['extension']}'";

            foreach ($targets as $index => $image) {
                $quality = $imageQuality;
                if (false === empty($image['quality'])) {
                    $quality = (int)$image['quality'];
                }
                $command .= " -quality {$quality} +profile '*' ";

                $crop         = (false === empty($image['crop']));
                $resizeParams = '';

                if ($crop && false === in_array(mb_strtolower($image['crop']), $this->garvity)) {
                    $image['crop'] = self::CROP_DEFAULT;
                }
                if ($crop) {
                    $info = getimagesize($source);
                    if ($info) {
                        $crop = $info[0] / $info[1];
                    } else {
                        $crop = false;
                    }
                }

                if (false === empty($image['width']) || false === empty($image['height'])) {
                    \Yii::trace('$image: ' . print_r($image, true), __METHOD__ . '(' . __LINE__ . ')');
                    if (empty($image['width'])) {
                        if (false === empty($image['height'])) {
                            if ($crop && 1 > $crop) {
                                $resizeParams .= "-resize '";
                            } else {
                                $resizeParams .= "-resize 'x";
                            }
                        }
                    } else {
                        if ($crop && 1 <= $crop) {
                            if (false === empty($image['height'])) {
                                if ((int)$image['height'] > (int)$image['width']) {
                                    $resizeParams .= "-resize 'x";
                                } else {
                                    $resizeParams .= "-resize 'x{$image['width']}";
                                }
                            } else {
                                $resizeParams .= "-resize 'x{$image['width']}";
                            }
                        } else {
                            $resizeParams .= "-resize '{$image['width']}x";
                        }
                    }

                    \Yii::trace('$resizeParams: ' . $resizeParams, __METHOD__ . '(' . __LINE__ . ')');

                    if (empty($image['height'])) {
                        if (false === empty($image['width'])) {
                            $resizeParams .= ">' ";
                        }
                    } else {
                        if ($crop) {
                            if (1 > $crop) {
                                if (false === empty($image['width'])) {
                                    if ((int)$image['height'] > (int)$image['width']) {
                                        $resizeParams .= "{$image['height']}>' ";
                                    } else {
                                        $resizeParams .= ">' ";
                                    }
                                } else {
                                    $resizeParams .= "{$image['height']}x>' ";
                                }
                            } else {
                                $resizeParams .= ">' ";
                            }
                        } else {
                            $resizeParams .= "{$image['height']}>' ";
                        }
                    }
                    \Yii::trace('$resizeParams: ' . $resizeParams, __METHOD__ . '(' . __LINE__ . ')');
                }

                if ($crop && false === empty($resizeParams)) {
                    $resizeParams .= " -gravity {$image['crop']} -crop {$image['width']}x{$image['height']}+0+0 +repage ";
                }
                $command .= $resizeParams;

                if ($count > ($index + 1)) {
                    $command .= " -write ";
                }
                $command .= "'{$params['directories']['target']}/{$params['file']}{$image['suffix']}.{$params['extension']}'";
            }
            $return = !(bool)`{$command}`;

            if (false === empty($params['source_delete'])) {
                unlink($source);
            }
        }

        \Yii::trace('$command: ' . $command, __METHOD__ . '(' . __LINE__ . ')');
        \Yii::trace('$return: ' . var_export($return, true), __METHOD__ . '(' . __LINE__ . ')');

        return $return;
    }

    /**
     * Сортировка массива параметров конвертирования изображений по размерам<br/>
     * От большего к меньшему
     * 
     * @param mixed $targets Массив параметров
     * @return array Отсортированный массив параметров
     */
    public function sortTargets($targets)
    {
        $sorted = [];
        if (empty($targets) || false === is_array($targets)) {
            return $sorted;
        }

        $sources = $targets;

        foreach ($sources as $source) {
            $width  = 0;
            $height = 0;
            if (empty($source['width']) && empty($source['height'])) {
                $width  = 1000000;
                $height = 1000000;
            } else {
                if (false === empty($source['width'])) {
                    $width = (int)$source['width'];
                }
                if (false === empty($source['height'])) {
                    $height = (int)$source['height'];
                }
                if (0 < $width && 0 === $height) {
                    $height = $width;
                }
                if (0 < $height && 0 === $width) {
                    $width = $height;
                }
            }

            $sorted[$width * $height] = $source;
        }
        krsort($sorted, SORT_NUMERIC);
        return array_values($sorted);
    }

}
