<?php

namespace phantomd\filedaemon\frontend\controllers;

use yii\web\Response;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\BadRequestHttpException;

/**
 * Класс контроллера управления демоном обработки изображений посредством API
 */
class DaemonController extends Controller
{

    use \phantomd\filedaemon\traits\DaemonTrait;

    public function behaviors()
    {
        $behaviors                      = parent::behaviors();
        $behaviors['contentNegotiator'] = [
            'class'   => 'yii\filters\ContentNegotiator',
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];
        $behaviors['AccessControl']     = [
            'class'        => 'yii\filters\AccessControl',
            'denyCallback' => function ($rule, $action) {
                throw new ForbiddenHttpException(\Yii::t('yii', 'You are not allowed to perform this action.'));
            },
            'rules'                         => [
                [
                    'allow' => true,
                    'ips'   => empty(\Yii::$app->params['secret']['allowIPs']) ? [] : \Yii::$app->params['secret']['allowIPs'],
                ],
            ]
        ];
        return $behaviors;
    }

    public function runAction($id, $params = [])
    {
        $params = \Yii::$app->request->get();
        if (false === empty($params['id'])) {
            static::$configAlias = $params['id'];
        }
        $this->configName = empty(static::$configAlias) ? '' : static::$configAlias;
        $this->getConfig();

        if (empty($this->config)) {
            throw new NotFoundHttpException(\Yii::t('yii', 'Unknown daemon ID!'));
        }

        $this->reloadComponent();

        return parent::runAction($id, $params);
    }

    /**
     * @inheritdoc
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
     * Метод не реализован
     *
     * @param string $id Наименование  демона, для которого отправляются данные
     * @param string $key Ключ авторизации
     */
    public function actionIndex()
    {
        throw new ForbiddenHttpException(\Yii::t('yii', 'You are not allowed to perform this action.'));
    }

    /**
     * Добавление данных для демона обработки данных
     *
     * ```php
     * // Принимаемые параметры
     *  $_POST = [
     *      'callback'   => 'http://www.bn.ru/../', // Ссылка для получения результатов обработки данных
     *      'name'       => 'www.bn.ru',
     *      'data'       => [
     *          [
     *              'command'   => 0, // Стандартная обработка
     *              'object_id' => '772dab1a-4b4f-11e5-885d-feff819cdc9f',
     *              'url'       => 'http://www.bn.ru/images/photo/2015_08/201508252016081835551362b.jpg',
     *              'file_id'  => 'a34c0e31-aaf8-43d9-a6ca-be9800dea5b7',
     *          ],
     *          [
     *              'command'   => 1, // Стандартная обработка + главная картинка
     *              'object_id' => 'c1b270c4-4b51-11e5-885d-feff819cdc9f',
     *              'url'       => 'http://www.bn.ru/images/photo/2015_08/201508252016121946987850b.jpg',
     *              'file_id'  => '92d05f7c-c8fb-472f-9f9a-b052521924e1',
     *          ],
     *      ],
     *  ];

     * ```
     *
     * @param string $id Наименование  демона, для которого отправляются данные
     * @param string $key Ключ авторизации
     * @return string JSON результат добавления данных
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

        $sources = array_chunk($data, (int)$this->config['max-count']);

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
     * Вывод картинки по полученному URI, если соответствует форматам из конфигурации
     *
     * @param string $url URI запрашиваемой картинки
     */
    public function actionView($url)
    {
        if ($path = $this->component->getImageByUri($url)) {
            header('Content-Type: image/' . $this->config['extension']);
            exit(file_get_contents($path));
        }
        throw new NotFoundHttpException(\Yii::t('yii', 'Unknown image!'));
    }

    public function actionCallback()
    {
        return \Yii::$app->request->post();
    }

}
