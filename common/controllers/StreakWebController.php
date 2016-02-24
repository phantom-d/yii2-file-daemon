<?php

namespace phantomd\filedaemon\common\controllers;

use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

/**
 * Class StreakWebController. Intermediate class for web controllers.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class StreakWebController extends Controller
{

    use \phantomd\filedaemon\traits\DaemonTrait;

    /**
     * Returns a list of behaviors that this component should behave as.
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $rulesIps = [];
        if (empty(\Yii::$app->params['secret']['allowIPs'])) {
            $rulesIps = \Yii::$app->params['secret']['allowIPs'];
        }

        $behaviors['AccessControl'] = [
            'class'        => 'yii\filters\AccessControl',
            'denyCallback' => function ($rule, $action) {
                throw new ForbiddenHttpException(\Yii::t('yii', 'You are not allowed to perform this action.'));
            },
            'rules'                     => [
                [
                    'allow' => true,
                    'ips'   => $rulesIps,
                ],
            ]
        ];
        return $behaviors;
    }

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

        if (empty(static::$config)) {
            throw new NotFoundHttpException(\Yii::t('yii', 'Unknown daemon ID!'));
        }

        $this->reloadComponent();

        return parent::runAction($id, $params);
    }

}
