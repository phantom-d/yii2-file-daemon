<?php

namespace phantomd\filedaemon\db;

use yii\base\Component;
use yii\db\Exception;

/**
 * Connection
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 * 
 * MYTODO: Реализовать универсальную инициализацию моделей для разных типов БД
 */
class Connection extends Component
{

    private $params = [];

    private $models = [
        'source' => null,
        'result' => null,
        'arc'    => null,
        'jobs'   => null,
    ];

    function init()
    {
        parent::init();

        $defaults = isset($this->params['default']) ? $this->params['default'] : null;

        foreach (array_values($this->models) as $model) {
            $type   = isset($defaults['type']) ? (string)$defaults['type'] : '';
            $config = null;

            if ($defaults) {
                if (isset($this->params['merge'][$model])) {
                    $config = array_merge_recursive($defaults, $this->params['merge'][$model]);
                } else {
                    $config = $defaults;
                }
            }

            if (isset($this->params['config'][$model])) {
                $config = $this->params['config'][$model];
            }

            if ($config) {
                if (empty($config['type'])) {
                    $message = 'Incorrect parameter `type` for "' . $model . '"';
                    \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                    throw new Exception($message);
                }

                \Yii::$app->set('filedaemon-' . $model, $config['db']);
                $this->models[$model] = $config['type'] . '\\models\\' . ucfirst($model);
            }
        }
    }

}
