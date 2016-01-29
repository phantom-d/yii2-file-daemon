<?php

namespace phantomd\filedaemon\db;

use yii\base\Component;
use yii\helpers\Inflector;
use yii\base\UnknownMethodException;
use yii\base\InvalidParamException;

/**
 * Connection
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 * 
 * MYTODO: Реализовать универсальную инициализацию моделей для разных типов БД
 */
class Connection extends Component
{

    public $params = [];

    private $models = [
        'source'    => null,
        'result'    => null,
        'arcresult' => null,
        'jobs'      => null,
    ];

    function init()
    {
        parent::init();
        $this->renewConnections();
    }

    public function __call($name, $params)
    {
        if (false === empty($params)) {
            $params = $params[0];
        }

        $method = explode('-', Inflector::camel2id($name));
        $model  = array_shift($method);
        $method = lcfirst(Inflector::id2camel(implode('-', $method)));
        $class  = isset($this->models[$model]) ? $this->models[$model] : null;

        if ($class && method_exists($class, $method)) {
            $params = $params ? [$params] : [];
            return call_user_func_array([$class, $method], $params);
        }

        throw new UnknownMethodException('Calling unknown method: ' . get_class($this) . "::{$name}()");
    }

    /**
     * Renew connections
     */
    public function renewConnections()
    {
        $defaults = isset($this->params['default']) ? $this->params['default'] : null;

        foreach (array_keys($this->models) as $model) {
            $config = [];
            if ($defaults) {
                if (isset($this->params['merge'][$model])) {
                    foreach ($defaults as $key => $value) {
                        if (isset($this->params['merge'][$model][$key])) {
                            $config[$key] = array_merge($value, $this->params['merge'][$model][$key]);
                        } else {
                            $config[$key] = $value;
                        }
                    }
                } else {
                    $config = $defaults;
                }
            }

            if (isset($this->params['config'][$model])) {
                $config = $this->params['config'][$model];
            }

            if ($config) {
                if (empty($config['driver'])) {
                    $message = 'Incorrect parameter `driver` for "' . $model . '"';
                    \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                    throw new InvalidParamException($message);
                }

                \Yii::$app->set('filedaemon_' . $model, $config['db']);
                $class = __NAMESPACE__ . '\\' . $config['driver'] . '\\models\\' . ucfirst($model);
                
                if (class_exists($class)) {
                    $this->models[$model] = $class;
                } else {
                    throw new \yii\db\Exception('Model not exists: ' . $class);
                }
            }
        }
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

}
