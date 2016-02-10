<?php

namespace phantomd\filedaemon\db;

use yii\base\Component;
use yii\helpers\Inflector;
use yii\base\UnknownMethodException;
use yii\base\InvalidParamException;

/**
 * Class Connection
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
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

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->renewConnections();
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        $method = explode('-', Inflector::camel2id($name));
        $model  = array_shift($method);
        $method = lcfirst(Inflector::id2camel(implode('-', $method)));
        $class  = isset($this->models[$model]) ? $this->models[$model] : null;

        if ($class && method_exists($class, $method)) {
            return call_user_func_array([$class, $method], $params);
        }

        $message = 'Calling unknown method: '
            . ($class ? $class : get_class($this)) . "::{$name}()";

        throw new UnknownMethodException($message);
    }

    /**
     * Renew connections
     */
    public function renewConnections()
    {
        if (empty($this->params)) {
            return null;
        }

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

}
