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
        $params = \Yii::$app->request->get();
        if (false === empty($params['id'])) {
            static::$configAlias = $params['id'];
        }
        $this->configName = empty(static::$configAlias) ? '' : static::$configAlias;
        $this->getConfig();

        if (empty($this->config)) {
            throw new InvalidParamException(\Yii::t('yii', 'Unknown daemon ID!'));
        }

        $this->reloadComponent();

        return parent::runAction($id, $params);
    }

}