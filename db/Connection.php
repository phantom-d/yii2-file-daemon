<?php

namespace phantomd\filedaemon\db;

use yii\base\Component;
use yii\db\Exception;
use yii\helpers\Inflector;

/**
 * Connection
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 * 
 * MYTODO: Реализовать универсальную инициализацию моделей для разных типов БД
 */
class Connection extends Component
{

    public $type = 'redis';

    private $_source = null;

    private $_result = null;

    private $_arc = null;

    private $_jobs = null;

    function init()
    {
        parent::init();

        $namespace = $this->type . '\\models\\';

        $source = $namespace . 'Source';
        $result = $namespace . 'Result';
        $arc    = $namespace . 'Arc';
        $jobs   = $namespace . 'Joblist';

        $this->_source = new $source;
        $this->_result = new $result;
        $this->_arc    = new $arc;
        $this->_jobs   = $jobs;
    }

}
