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

    private $_shortName = '';

    private $configAlias = 'image-server';

    protected $component  = null;

    public $modelClass = 'app\models\Joblist';

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
            $this->configAlias = $params['id'];
        }
        $this->configName = $this->configAlias;
        $this->getConfig();

        return parent::runAction($id, $params);
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
     *              'image_id'  => 'a34c0e31-aaf8-43d9-a6ca-be9800dea5b7',
     *          ],
     *          [
     *              'command'   => 1, // Стандартная обработка + главная картинка
     *              'object_id' => 'c1b270c4-4b51-11e5-885d-feff819cdc9f',
     *              'url'       => 'http://www.bn.ru/images/photo/2015_08/201508252016121946987850b.jpg',
     *              'image_id'  => '92d05f7c-c8fb-472f-9f9a-b052521924e1',
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

        if (empty($this->config)) {
            throw new NotFoundHttpException(\Yii::t('yii', 'Unknown daemon ID!'));
        }

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
            \Yii::$app->images->setSource($name, $source, $this->config['db']['source']);
            if (\Yii::$app->images->createJob($name, $callback, $this->config['db']['source'], \app\models\Joblist::STATUS_WAIT)) {
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
        if (empty($this->config)) {
            throw new NotFoundHttpException(\Yii::t('yii', 'Unknown daemon ID!'));
        }

        $url    = (string)parse_url($url, PHP_URL_PATH);
        $params = preg_split('/^([a-z0-9]{32})(.+)/i', basename($url, '.' . $this->config['extension']), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if (false === empty($params[1])) {
            $fileName = $params[0];
            $suffix   = $params[1];
            $dirName  = dirname($url);
        } else {
            throw new BadRequestHttpException(\Yii::t('yii', 'Incorrect url!'));
        }

        $return = false;

        if ($connectionDb = \Yii::$app->images->getConnection($this->config['db']['arc'])) {
            if ($result = $connectionDb->hget('jobsArc', $fileName)) {
                $targetPath = \yii\helpers\FileHelper::normalizePath($this->config['directories']['target'] . $dirName);

                $itemData = [
                    'extension'   => $this->config['extension'],
                    'quality'     => (int)$this->config['quality'],
                    'file'        => $fileName,
                    'source'      => $targetPath . DIRECTORY_SEPARATOR . $fileName . '.' . $this->config['extension'],
                    'directories' => [
                        'source' => $targetPath,
                        'target' => $targetPath,
                    ],
                    'targets'     => [],
                ];

                if (false === is_file($itemData['source'])) {
                    if ($files = glob($targetPath . DIRECTORY_SEPARATOR . $fileName . '*')) {
                        $fileSize = 0;
                        foreach ($files as $file) {
                            if ($fileSize < filesize($file)) {
                                $itemData['source'] = $file;
                                $fileSize           = filesize($file);
                            }
                        }
                    }
                }

                if (is_file($itemData['source'])) {
                    if (false === empty($this->config['targets'])) {
                        foreach ($this->config['targets'] as $name => $target) {
                            if (isset($target['suffix']) && $suffix === $target['suffix']) {
                                $itemData['targets'][$name] = $target;
                                break;
                            }
                        }
                    }

                    if (empty($itemData['targets'])) {
                        if (false === empty($this->config['commands'])) {
                            $status = false;
                            foreach ($this->config['commands'] as $command) {
                                if (false === empty($command['targets'])) {
                                    foreach ($command['targets'] as $name => $target) {
                                        if (isset($target['suffix']) && $suffix === $target['suffix']) {
                                            $itemData['targets'][$name] = $target;

                                            $status = true;
                                            break;
                                        }
                                    }
                                }
                                if ($status) {
                                    break;
                                }
                            }
                        }
                    }

                    if (\Yii::$app->images->convertImage($itemData)) {
                        if (is_file($targetPath . DIRECTORY_SEPARATOR . basename($url))) {
                            header('Content-Type: image/' . $this->config['extension']);
                            echo file_get_contents($targetPath . DIRECTORY_SEPARATOR . basename($url));
                            exit;
                        }
                    }
                }
            }
        }

        throw new NotFoundHttpException(\Yii::t('yii', 'Unknown image!'));
    }

    public function actionCallback()
    {
        return \Yii::$app->request->post();
    }

    protected function verbs()
    {
        return [
            'index'    => ['GET'],
            'view'     => ['GET'],
            'create'   => ['POST'],
            'callback' => ['POST'],
        ];
    }

}
