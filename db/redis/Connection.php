<?php

namespace phantomd\filedaemon\db;

use yii\base\Component;
use yii\db\Exception;
use yii\helpers\Inflector;
use phantomd\filedaemon\models\Joblist;

class Connection extends Component
{

    function init()
    {
        parent::init();
    }

    /**
     * Получения списка ключей из RedisDB
     *
     * @param string $table Паттерн ключа в RedisDB.
     * @param string $db Исходная база данных.
     * @return mixed|FALSE Массив ключей из RedisDB
     */
    public function getTables($table = '*', $db = 'redis0')
    {
        $return = false;

        if ($connectionDb = $this->getConnection($db)) {
            if ($table === '') {
                $table = '*';
            }
            $return = $connectionDb->keys($table);
        }
        return $return;
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
     * Получение объекта для работы с базой данных.
     *
     * @param string $component Наименование компонента из настроек.
     * @return object|NULL
     */
    public function getConnection($component)
    {
        $connection = null;
        if (\Yii::$app->has($component)) {
            $connection = \Yii::$app->get($component);
            $connection->open();
        }
        if (false === $connection->isActive) {
            $connection = null;
            \Yii::error("Not connected to DB '{$component}'!", __METHOD__ . '(' . __LINE__ . ')');
        }
        return $connection;
    }

    /**
     * Получение данных из Сортированного множества (Sorted set).
     * 
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @param bool $remove Удалять запись после получения.
     * @return mixed|FALSE Массив данных и очки записи
     */
    public function getOne($table, $db, $remove = true)
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
    public function getAll($table, $db, $limit = 10, $page = 0, $remove = false)
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
     * Получение количества записей в сортированном множестве в RedisDB
     *
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @return integer
     */
    public function getCount($table, $db = 'redis0')
    {
        $return = 0;

        if ($table && $connectionDb = $this->getConnection($db)) {
            if ('zset' === $connectionDb->type($table)) {
                $return = (int)$connectionDb->zcount($table, '-inf', '+inf');
            }
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
                            if ($this->setItem($table, $data, $db)) {
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
                    $item    = array_merge($this->itemDstScheme, $params['item']);
                    $prepare = [];
                    foreach ($this->itemDstScheme as $key => $value) {
                        $prepare[$key] = isset($item[$key]) ? $item[$key] : $value;
                    }
                    $params['item'] = $prepare;

                    if (empty($params['item']['file_name']) || empty($params['item']['time_dir']) || empty($params['item']['object_id'])) {
                        \Yii::error("There are no one of required parameters (file_name, time_dir, object_id)!<br>\n" . var_export($result, true), __METHOD__ . '(' . __LINE__ . ')');
                    } else {
                        $return = $this->setItem($table, $params, $db);
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
    public function setItem($table, $params, $db)
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
     * Проверка доступности источника для добавления в список задач.
     * Условия для положительного ответа:
     * <ul>
     * <li>Задачи с таким ID не существует</li>
     * <li>Домен в источнике доступен по запросу</li>
     * </ul>
     *
     * @param string $table Наименование ключа в RedisDB
     * @param string $db Исходная база данных.
     * @return mixed|bool Источник доступен для добаления в список задач или FALSE
     */
    public function checkSourceAccess($table, $db = 'redis0')
    {
        $return = false;
        if ($table && $db) {
            if ($connectionDb = $this->getConnection($db)) {
                if ('zset' === $connectionDb->type($table)) {
                    $item = $this->getSource($table, $db, false);
                    if (false === empty($item['item']['url'])) {
                        $curl = new components\Curl();
                        foreach ($this->curlOptions as $name => $value) {
                            $curl->setOption($name, $value);
                        }
                        $curl->head($item['item']['url']);

                        if ($curl->responseCode) {
                            $return = true;
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
     * Создание списка задач для работы в соответствии с имеющимися данными в источнике.
     *
     * @param string $db Исходная база данных.
     * @param string $checkDb Проверочная база данных.
     * @return boolean Список создан и находится в режиме ожидания или FALSE
     */
    public function createJoblist($db = 'redis0', $checkDb = 'redisjoblist')
    {
        $return = false;
        if ($result = $this->getTables('*', $db)) {
            $jobsId = [];
            if (is_array($result)) {
                foreach ($result as $value) {
                    $createJob = false;
                    $callback  = null;

                    if ($job = Joblist::chooseJob(md5($value))) {
                        $callback = $job->callback;
                        if (Joblist::STATUS_COMPLETE === (int)$job->status) {
                            $job->status = Joblist::STATUS_PREPARE;
                            $resultJob   = $job->save();
                        }
                        if (Joblist::STATUS_ERROR === (int)$job->status) {
                            $job->status = Joblist::STATUS_PREPARE;
                            $resultJob   = $job->save();
                        }

                        if (Joblist::STATUS_PREPARE === (int)$job->status) {
                            $createJob = true;
                        }
                    }

                    \Yii::trace($createJob, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '--- $createJob');

                    if ($createJob && $this->createJob($value, $callback, $db)) {
                        $jobsId[] = md5($value);
                    }
                }
            }

            \Yii::trace($jobsId, __METHOD__ . '(' . __LINE__ . ')' . "\n" . '--- $jobsId');

            if ($jobsId) {
                $jobList = Joblist::findAll($jobsId);
                if ($jobList) {
                    foreach ($jobList as $job) {
                        if (Joblist::STATUS_PREPARE === (int)$job->status) {
                            $job->status = Joblist::STATUS_WAIT;
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
     * @param string $table Наименование ключа в RedisDB
     * @param string $callback Ссылка для отправки результатов обработки
     * @param string $db Исходная база данных.
     * @param int $status Статус по умолчанию
     * @return boolean
     */
    public function createJob($table, $callback, $db = 'redis0', $status = Joblist::STATUS_PREPARE)
    {
        $return = false;

        if (empty($callback)) {
            return $return;
        }

        $countTotal = (int)$this->getCount($table);

        if ($countTotal > 0) {
            $params = [
                'id'           => md5($table),
                'name'         => $table,
                'callback'     => $callback,
                'status'       => ($this->checkSourceAccess($table, $db) ? $status : Joblist::STATUS_ERROR),
                'total'        => $countTotal,
                'time_elapsed' => 0,
                'complete'     => 0,
            ];

            if ($job = Joblist::chooseJob(md5($table))) {
                if ($job->statusWork) {
                    $params['status'] = $job->status;
                }
                $job->setAttributes($params);
            } else {
                $params['time_create'] = time();

                $job = new Joblist($params);
            }
            if ($job->save()) {
                $return = true;
            }
        }
        return $return;
    }

}
