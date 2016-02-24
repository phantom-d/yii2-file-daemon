<?php

namespace phantomd\filedaemon\common\controllers;

use yii\console\Controller;
use yii\base\InvalidParamException;

/**
 * Class StreakConsoleController. Intermediate class for console controllers.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class StreakConsoleController extends Controller
{

    use \phantomd\filedaemon\traits\DaemonTrait;

    /**
     * Runs an action within this controller with the specified action ID and parameters.
     */
    public function runAction($id, $params = [])
    {
        if (false === empty($params['id'])) {
            static::$configAlias = $params['id'];
        }
        $this->configName = empty(static::$configAlias) ? '' : static::$configAlias;
        $this->getConfig();

        if (empty(static::$config)) {
            throw new InvalidParamException(\Yii::t('yii', 'Unknown daemon ID!'));
        }

        $this->reloadComponent();

        return parent::runAction($id, $params);
    }

    public function options($actionID)
    {
        return [
            'id',
        ];
    }

    public function getActionOptionsHelp($actionID)
    {
        $name = $this->ansiFormat('$configAlias', \yii\helpers\Console::FG_GREEN);
        return [
            'id' => [
                'type'    => 'string',
                'default' => null,
                'comment' => "Daemon name for get component config.\n"
                . "If not set, controller property {$name} is used.",
            ],
        ];
    }

}
