<?php

namespace phantomd\filedaemon;

use yii\base\InvalidParamException;

/**
 * Component
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Component
{

    public function __construct($config)
    {
        return static::init($config);
    }

    /**
     * Initialization
     * 
     * @param array $config
     * @return object Object instanceof phantomd\filedaemon\FileProcessing
     * @throws InvalidParamException
     */
    public static function init($config)
    {
        if (empty($config)) {
            $message = 'Could not be empty `config`!';
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            throw new InvalidParamException($message);
        }

        $class = null;
        if (false === empty($config['component'])) {
            if (class_exists($config['component'])) {
                $class = $config['component'];
            } else {
                $class = __NAMESPACE__ . '\\'
                    . ucfirst(strtolower((string)$config['component'])) . 'Processing';
            }
        }

        if ($class) {
            if (false === class_exists($class)) {
                $message = 'Not exist component: `' . $class . '`';
                \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                throw new InvalidParamException($message);
            }
            $params = [
                'class'  => $class,
                'config' => $config,
            ];
            return \Yii::createObject($params);
        }

        return null;
    }

}
