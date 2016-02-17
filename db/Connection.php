<?php

namespace phantomd\filedaemon\db;

use yii\base\Component;
use yii\helpers\Inflector;
use yii\base\UnknownMethodException;
use yii\base\InvalidParamException;

/**
 * Class Connection. Database manager for the processing.
 *
 * @method ActiveInterface sourceModel(array $params Data) Create source model with data from params
 * @method ActiveInterface sourceOne(array $params Condition) Get one source model by params from database
 * @method ActiveInterface sourceAll(array $params Condition) Get array of source models by params from database
 * @method ActiveInterface sourceNames(array $params Condition) Get source names from database
 * @method ActiveInterface sourceGroups(array $params Condition) Get source names from database
 * @method ActiveInterface sourceRename(string $params New name) Rename all sources which has current name
 * @method ActiveInterface sourceRemove() Delete all sources with current name from database
 * @method ActiveInterface sourceSave() Save source to database
 *
 * @method ActiveInterface resultModel(array $params Data) Create result model with data from params
 * @method ActiveInterface resultOne(array $params Condition) Get one result model by params from database
 * @method ActiveInterface resultAll(array $params Condition) Get array of result models by params from database
 * @method ActiveInterface resultNames(array $params Condition) Get result names from database
 * @method ActiveInterface resultGroups(array $params Condition) Get result groups from database
 * @method ActiveInterface resultRename(string $params New name) Rename all results which has current name
 * @method ActiveInterface resultRemove() Delete all results with current name from database
 * @method ActiveInterface resultSave() Save result to database
 *
 * @method ActiveInterface arcresultModel(array $params Data) Create arcresult model with data from params
 * @method ActiveInterface arcresultOne(array $params Condition) Get one arcresult model by params from database
 * @method ActiveInterface arcresultAll(array $params Condition) Get array of arcresult models by params from database
 * @method ActiveInterface arcresultNames(array $params Condition) Get arcresult names from database
 * @method ActiveInterface arcresultGroups(array $params Condition) Get arcresult groups from database
 * @method ActiveInterface arcresultRename(string $params New name) Rename all arcresults which has current name
 * @method ActiveInterface arcresultRemove() Delete all arcresults with current name from database
 * @method ActiveInterface arcresultSave() Save arcresult to database
 *
 * @method ActiveInterface jobsModel(array $params Data) Create jobs model with data from params
 * @method ActiveInterface jobsOne(array $params Condition) Get one jobs model by params from database
 * @method ActiveInterface jobsAll(array $params Condition) Get array of jobs models by params from database
 * @method ActiveInterface jobsNames(array $params Condition) Get job names from database
 * @method ActiveInterface jobsGroups(array $params Condition) Get job groups from database
 * @method ActiveInterface jobsRename(string $params New name) Rename all jobs which has current name
 * @method ActiveInterface jobsRemove() Delete all jobs with current name from database
 * @method ActiveInterface jobsSave() Save jobs to database
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Connection extends Component
{

    /**
     * @var array Parameters for database connections
     */
    public $params = [];

    /**
     * @var array Database connections for daemons
     */
    private $models = [
        'source'    => null,
        'result'    => null,
        'arcresult' => null,
        'jobs'      => null,
    ];

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     */
    public function init()
    {
        parent::init();
        $this->renewConnections();
    }

    /**
     * Calls the named method which is not a class method.
     * Call this method directly from database connections
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

            if (isset($this->params['config']) && isset($this->params['config'][$model])) {
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
