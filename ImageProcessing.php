<?php

namespace phantomd\filedaemon;

use yii\base\ErrorException;
//use app\models\Joblist;

/**
 * Компонент для работы
 */
class ImageProcessing extends FileProcessing
{

    const CROP_DEFAULT = 'center';

    protected $garvity = [
        'northwest',
        'north',
        'northeast',
        'west',
        'center',
        'east',
        'southwest',
        'south',
        'southeast',
    ];

    public $httpUseragent = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.152 Safari/537.36';

    public $downloader = "/usr/bin/env wget -T 5 -t 1 -q --no-check-certificate --user-agent='%s' -O '%s' '%s' 2>/dev/null";

    public $curlOptions = [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ];

    /**
     * Схема источника данных
     *
     * @var mixed
     */
    private $itemSrcScheme = [
        'command'   => 0,
        'object_id' => 0,
        'url'       => '',
        'image_id'  => 0,
    ];

    /**
     * Схема обработанных данных
     *
     * @var mixed
     */
    private $itemDstScheme = [
        'command'   => 0,
        'file_name' => '',
        'image_id'  => 0,
        'object_id' => 0,
        'time_dir'  => '',
    ];
    
    public function test()
    {
        echo '<pre>',
        '$this(' . __LINE__ . '): ', print_r($this, true), "\n",
        '</pre>';
    }

    /**
     * Переименование ключа в Redis DB
     *
     * @param string $nameSrc Исходное наименование ключа
     * @param string $nameDst Новое наименование ключа
     * @param string $db Исходная база данных.
     * @return bool Статус переименования ключа
     */
    public function renameTable($nameSrc, $nameDst, $db = 'redis0')
    {
        $return = false;

        if ($connectionDb = $this->getConnection($db)) {
            $return = (bool)$connectionDb->renamenx($nameSrc, $nameDst);
            \Yii::trace("RanameNX:<br>\n" . var_export($return, true), __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Получение одной записи из источника.
     *
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @param bool $remove Удалять запись после получения.
     * @return mixed|FALSE Массив записи в установленном формате
     */
    public function getSource($table, $db = 'redis0', $remove = true)
    {
        $return = false;

        if ($table && $row = $this->getOne($table, $db, $remove)) {
            if (is_bool($row)) {
                $return = $row;
            } else {
                if (strpos($row['item'], '::') === false) {
                    $item = json_decode($row['item'], true);
                } else {
                    $item = array_combine(array_keys($this->itemSrcScheme), explode('::', $row['item']));
                }
                $prepare = [];
                foreach ($this->itemSrcScheme as $key => $value) {
                    $prepare[$key] = isset($item[$key]) ? $item[$key] : $value;
                }
                $item   = $prepare;
                $return = [
                    'item'  => $item,
                    'score' => $row['score']
                ];
            }
        }
        return $return;
    }

    public function getOne($table, $db, $remove = true)
    {
        return $this->getZOne($table, $db, $remove);
    }

    /**
     * Получение данных из Сортированного множества (Sorted set).
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @param bool $remove Удалять запись после получения.
     * @return mixed|FALSE Массив данных и очки записи
     */
    public function getZOne($table, $db, $remove = true)
    {
        $return       = false;
        if ($table && $connectionDb = $this->getConnection($db)) {
            if ($connectionDb->exists($table)) {
                if ('zset' === $connectionDb->type($table)) {
                    $script = "local element = redis.pcall('ZRANGEBYSCORE', '{$table}', '-inf', '+inf', 'WITHSCORES', 'LIMIT' , '0' , '1')"
                        . ((bool)$remove ? " redis.pcall('ZREM', '{$table}', element[1])" : '')
                        . " return element";

                    $row = $connectionDb->eval($script, 0);
                    if ($row) {
                        $return = [
                            'item'  => $row[0],
                            'score' => $row[1]
                        ];
                    }
                } else {
                    \Yii::error("Incorrect table '{$table}' type! Must be sorted sets!", __METHOD__ . '(' . __LINE__ . ')');
                }
            } else {
                \Yii::error("Table not exists: {$table}", __METHOD__ . '(' . __LINE__ . ')');
                $return = true;
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    public function getAll($table, $db, $limit = 10, $page = 0, $remove = false)
    {
        return $this->getZAll($table, $db, $limit, $page, $remove);
    }

    /**
     * Получение списка записей из сортированного множества
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @param integer $limit Количество.
     * @param integer $page Номер страницы.
     * @param boolean $remove Удалять записи после получения.
     * @return array|FALSE
     * @todo Реализовать механизм удаление полученных строк из RediDB
     * 
     * MYTODO: Реализовать механизм удаление полученных строк из RediDB
     */
    public function getZAll($table, $db, $limit = 10, $page = 0, $remove = false)
    {
        $return = false;

        if ($table && (int)$limit && $connectionDb = $this->getConnection($db)) {
            if ($connectionDb->exists($table)) {
                if ('zset' === $connectionDb->type($table)) {
                    $limit  = (int)$limit;
                    $offset = (int)$limit * (int)$page;

                    $row = $connectionDb->zrangebyscore(
                        $table, //
                        '-inf', '+inf', //
                        'WITHSCORES', //
                        'LIMIT', $offset, $limit
                    );
                    if ($row) {
                        $return = [];
                        $key    = 0;

                        while (isset($row[$key])) {
                            $return[] = [
                                'item'  => $row[$key++],
                                'score' => $row[$key++]
                            ];
                        }
                    }
                } else {
                    \Yii::error("Incorrect table '{$table}' type! Must be sorted sets!", __METHOD__ . '(' . __LINE__ . ')');
                }
            } else {
                \Yii::error("Table not exists: {$table}", __METHOD__ . '(' . __LINE__ . ')');
                $return = true;
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Получение данных результата обработки
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @param integer $limit Количество.
     * @param integer $page Номер страницы.
     * @param boolean $remove Удалять записи после получения.
     * @return array|FALSE
     */
    public function getResult($table, $db = 'redis1', $limit = 10, $page = 0, $remove = false)
    {
        $return = false;
        if ($table && (int)$limit) {
            if ($result = $this->getAll($table, $db, $limit, $page, $remove)) {
                if (true === $result) {
                    $return = true;
                } else {
                    $return = [];
                    foreach ($result as $value) {
                        $return[] = json_decode($value['item'], true);
                    }
                    if (empty($return)) {
                        $return = false;
                    }
                }
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Запись данных для задач в RedisDB
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param array $params Массив данных в формате $this->itemSrcScheme + score
     * @param string $db Исходная база данных.
     * @return boolean
     */
    public function setSource($table, $params, $db = 'redis0')
    {
        $return = false;
        if ($table && false === empty($params)) {
            if ($connectionDb = $this->getConnection($db)) {
                if (0 === (int)$connectionDb->exists($table) || 'zset' === $connectionDb->type($table)) {
                    $count = 0;
                    if (empty($params[0])) {
                        $params = array($params);
                    }
                    foreach ($params as $item) {
                        $result = [];
                        $score  = isset($item['score']) ? (int)$item['score'] : 0;
                        foreach ($this->itemSrcScheme as $key => $value) {
                            $result[$key] = isset($item[$key]) ? $item[$key] : $value;
                        }
                        if (empty($result['url']) || empty($result['image_id']) || empty($result['object_id'])) {
                            \Yii::warning("There are no one of required parameters (object_id, url, image_id)!<br>\n" . var_export($result, true), __METHOD__ . '(' . __LINE__ . ')');
                        } else {
                            $data = [
                                'item'  => $result,
                                'score' => $score
                            ];
                            if ($this->setZItem($table, $data, $db)) {
                                ++$count;
                            }
                        }
                    }
                    if ($count === count($params)) {
                        $return = true;
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
     * Проверка наличия записи в архивной базе
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $field Наименование поля в указанном ключе
     * @param string $db Исходная база данных.
     * @return boolean
     */
    public function checkArcResult($table, $field, $db = 'redis2')
    {
        $return = false;
        if ($table && $field) {
            if ($connectionDb = $this->getConnection($db)) {
                if ('hash' === $connectionDb->type($table)) {
                    $return = (bool)$connectionDb->hexists($table, $field);
                } else {
                    \Yii::error("Incorrect table '{$table}' type! Must be hashes!", __METHOD__ . '(' . __LINE__ . ')');
                }
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
        }
        return $return;
    }

    /**
     * Получение данных из архивной базы в RedisDB
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $field Наименование поля в указанном ключе.
     * @param string $db Архивная база данных.
     * @return mixed|FALSE Ассоциативный массив | Скалярное зачение указанного поля <b>$field</b> 
     */
    public function getArcResult($table, $field = null, $db = 'redis2')
    {
        $return = false;
        if ($table) {
            if ($connectionDb = $this->getConnection($db)) {
                if ($connectionDb->exists($table)) {
                    if ('hash' === $connectionDb->type($table)) {
                        if (empty($field)) {
                            $source = $connectionDb->hgetall($table);
                        } else {
                            $source = $connectionDb->hget($table, $field);
                        }
                        if (false === empty($source)) {
                            if (is_array($source)) {
                                $key = 0;
                                while (isset($source[$key])) {
                                    $result[$source[$key++]] = $source[$key++];
                                }
                            } else {
                                $result = $source;
                            }
                            $return = $result;
                        }
                    } else {
                        \Yii::error("Incorrect table '{$table}' type! Must be hashes!", __METHOD__ . '(' . __LINE__ . ')');
                    }
                } else {
                    \Yii::error("Table not exists: {$table}", __METHOD__ . '(' . __LINE__ . ')');
                    $return = true;
                }
            }
        } else {
            $args = func_get_args();
            \Yii::error("Incorrect params!<br>\n" . var_export($args, true), __METHOD__ . '(' . __LINE__ . ')');
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
