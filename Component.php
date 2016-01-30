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
     * @return object Component
     * @throws InvalidParamException
     */
    public static function init($config)
    {
        if (empty($config)) {
            $message = 'Could not be empty `config`!';
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            throw new InvalidParamException($message);
        }

        $type = null;

        if (false === empty($config['component'])) {
            $type = ucfirst(strtolower((string)$config['component']));
        }

        if ($type) {
            $component = 'phantomd\\filedaemon\\' . $type . 'Processing';

            if (false === class_exists($component)) {
                $message = 'Not exist component: `' . $component . '`';
                \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                throw new InvalidParamException($message);
            }

            return new $component(['config' => $config]);
        }

        return null;
    }

}
