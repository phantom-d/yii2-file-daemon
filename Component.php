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

    public static function init($config)
    {
        if (empty($config)) {
            $message = 'Could not be empty `config`!';
            \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
            throw new InvalidParamException($message);
        }

        $type = 'File';

        if (false === empty($config['type'])) {
            $type = ucfirst(strtolower((string)$config['type']));
        }

        $component = 'phantomd\\filedaemon\\' . $type . 'Processing';
        
        if (false === class_exists($component)) {
            throw new InvalidParamException('Incorrect component type!');
        }

        return new $component(['config' => $config]);
    }

}
