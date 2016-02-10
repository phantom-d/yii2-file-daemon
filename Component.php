<?php

namespace phantomd\filedaemon;

use yii\base\InvalidParamException;

/**
 * Class Component
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class Component
{

    /**
     * Non static initialization
     *
     * @param array $config
     * @return \phantomd\filedaemon\FileProcessing Object
     */
    public function __construct($config)
    {
        return static::init($config);
    }

    /**
     * Initialization
     *
     * @param array $config
     * @return \phantomd\filedaemon\FileProcessing Object
     * @throws InvalidParamException
     */
    public static function init($config)
    {
        if (empty($config)) {
            $message = 'Component error: Could not be empty `config`!';
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
                $message = "Component error: Not exist - `{$class}`";
                \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                throw new InvalidParamException($message);
            }
            $params = [
                'class'  => $class,
                'config' => $config,
            ];
            $object = \Yii::createObject($params);
            if ($object instanceof FileProcessing) {
                return $object;
            } else {
                $message = "Component error: `{$class}` must be instance of class `FileProcessing`!";
                \Yii::error($message, __METHOD__ . '(' . __LINE__ . ')');
                throw new InvalidParamException($message);
            }
        }

        return null;
    }

}
