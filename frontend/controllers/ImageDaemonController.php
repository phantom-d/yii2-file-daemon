<?php

namespace phantomd\filedaemon\frontend\controllers;

use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;
use phantomd\filedaemon\common\controllers\StreakRestController;

/**
 * Class FileDaemonController. Frontend REST controller.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class ImageDaemonController extends StreakRestController
{

    /**
     * Declares the allowed HTTP verbs.
     */
    protected function verbs()
    {
        return [
            'index'    => ['GET'],
            'view'     => ['GET'],
            'create'   => ['POST'],
            'callback' => ['POST'],
        ];
    }

    /**
     * The method is not implemented
     */
    public function actionIndex()
    {
        throw new ForbiddenHttpException(\Yii::t('yii', 'You are not allowed to perform this action.'));
    }

    /**
     * Create jobs from recived data
     *
     * ```php
     *  $_POST = [
     *      'callback'   => 'http://www.bn.ru/../', // Callback url
     *      'name'       => 'www.bn.ru',
     *      'data'       => [
     *          [
     *              'command'   => 0,
     *              'object_id' => '772dab1a-4b4f-11e5-885d-feff819cdc9f',
     *              'url'       => 'http://www.bn.ru/images/photo/2015_08/201508252016081835551362b.jpg',
     *              'file_id'  => 'a34c0e31-aaf8-43d9-a6ca-be9800dea5b7',
     *          ],
     *          [
     *              'command'   => 1,
     *              'object_id' => 'c1b270c4-4b51-11e5-885d-feff819cdc9f',
     *              'url'       => 'http://www.bn.ru/images/photo/2015_08/201508252016121946987850b.jpg',
     *              'file_id'  => '92d05f7c-c8fb-472f-9f9a-b052521924e1',
     *          ],
     *      ],
     *  ];

     * ```
     *
     * @return string JSON
     */
    public function actionCreate()
    {
        $return = [
            'status' => false,
        ];

        $errors = [];

        $name     = \Yii::$app->request->post('name', null);
        $callback = \Yii::$app->request->post('callback', null);
        $data     = \Yii::$app->request->post('data', null);

        $config = [
            [['name', 'callback', 'data'], 'required',],
            [
                'callback',
                'url',
                'enableIDN' => true,
                'message'   => \Yii::t('yii', "{attribute} is not a valid URL: '{$callback}'!")
            ],
        ];

        $model = \yii\base\DynamicModel::validateData(compact('name', 'callback', 'data'), $config);

        if ($model->hasErrors()) {
            throw new BadRequestHttpException(json_encode($model->getErrors()));
        }

        $name = $name . '::' . microtime(true);

        $sources = array_chunk($data, $this->maxPostCount);

        $countJobs = 0;
        foreach ($sources as $source) {
            $this->component->addSource($name, $source);
            if ($this->component->addJob($name, $callback, \app\models\Joblist::STATUS_WAIT)) {
                ++$countJobs;
            }
        }

        if ($countJobs) {
            $return['status'] = true;
        } else {
            throw new BadRequestHttpException(\Yii::t('yii', 'Error create project!'));
        }

        return $return;
    }

    /**
     * Show image from URI
     *
     * @param string $url Image URI
     */
    public function actionView($url)
    {
        if ($path = $this->component->getImageByUri($url)) {
            header('Content-Type: image/' . $this->config['extension']);
            exit(file_get_contents($path));
        }
        throw new NotFoundHttpException(\Yii::t('yii', 'Unknown image!'));
    }

    /**
     * Callback action for test
     *
     * @return json
     */
    public function actionCallback()
    {
        return \Yii::$app->request->post();
    }

}
