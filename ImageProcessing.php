<?php

namespace phantomd\filedaemon;

use yii\base\ErrorException;
use yii\helpers\StringHelper;
use yii\helpers\FileHelper;

/**
 * Компонент для работы
 */
class ImageProcessing extends FileProcessing
{

    const CROP_DEFAULT = 'center';

    protected static $mimeType = 'image';

    protected $garvity = [
        'northwest',
        'north',
        'northeast',
        'west',
        'center',
        'east',
        'southwest',
        'south',
        'southeast',
    ];

    /**
     * Возвращае абсолютный путь к файлу по URI
     * 
     * @param string $url
     * @return string
     */
    public function getImageByUri($url)
    {
        $return = false;

        $url  = (string)parse_url($url, PHP_URL_PATH);
        $file = StringHelper::basename($url, '.' . $this->config['extension']);

        if (32 > (int)StringHelper::byteLength($file)) {
            return $return;
        }

        $fileName = StringHelper::byteSubstr($file, 0, 32);
        $suffix   = StringHelper::byteSubstr($file, 32);

        if ($result = $this->arcresultOne($fileName)) {
            $dirName    = dirname($result->path);
            $targetPath = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $this->config['directories']['target'] . $dirName
                    )
            );
            $sourceFile = $targetPath . DIRECTORY_SEPARATOR . $fileName . '.' . $this->config['extension'];

            if (is_file($sourceFile)) {
                $return = $sourceFile;
            }

            if (empty($suffix)) {
                return $return;
            }

            $itemData = [
                'extension'   => $this->config['extension'],
                'quality'     => (int)$this->config['quality'],
                'file'        => $fileName,
                'source'      => $sourceFile,
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

                if ($this->makeFile($itemData)) {
                    if (is_file($targetPath . DIRECTORY_SEPARATOR . basename($url))) {
                        $return = $targetPath . DIRECTORY_SEPARATOR . basename($url);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Конвертирование изображения в указанный формат
     * (консольный GraphicsMagick)
     *
     * @param array $params Массив в формате:
     * 
     * ```php
     * $param = [
     *     'source'        => 'test_file',
     *     'source_delete' => true,
     *     'file'          => 'target_file',
     *     'directories'   => [
     *         'source' => '/var/www/temp/',
     *         'target' => '/var/www/uploads/',
     *     ],
     *     'extension'     => 'jpg',
     *     'targets'       => [
     *         'origin' => [
     *             'suffix' => '_o',
     *         ],
     *         'big'    => [
     *             'width'  => 1024,
     *             'suffix' => '_b',
     *         ],
     *         'medium' => [
     *             'height' => 220,
     *             'suffix' => '_m',
     *         ],
     *         'small'  => [
     *             'width'  => 70,
     *             'height' => 51,
     *             'suffix' => '_s',
     *         ],
     *     ],
     * ];
     * ```
     * 
     * @return boolean
     */
    public function makeFile($params = [])
    {
        YII_DEBUG && \Yii::trace($params, __METHOD__ . '(' . __LINE__ . ')');

        $return = false;
        if (false === parent::makeFile($params)) {
            return $return;
        }
        if (empty($params)) {
            return $return;
        }

        $source = $params['source'];
        if (false === is_file($source)) {
            $source = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $params['directories']['source'] . DIRECTORY_SEPARATOR
                        . basename($params['source'])
                    )
            );
        }
        $command = "/usr/bin/env gm convert -limit threads 2 '{$source}'";

        if (is_file($source) && false === empty($params['targets'])) {
            $targets = $this->sortTargets($params['targets']);
            $target  = FileHelper::normalizePath(
                    \Yii::getAlias(
                        $params['directories']['target'] . DIRECTORY_SEPARATOR
                        . $params['file']
                    )
            );
            $count   = count($targets);

            $imageQuality = 75;
            if (false === empty($params['quality'])) {
                $imageQuality = (int)$params['quality'];
            }
            $command .= " -quality {$imageQuality} +profile '*' "
                . "-write '{$target}.{$params['extension']}'";

            foreach ($targets as $index => $image) {
                $quality = $imageQuality;
                if (false === empty($image['quality']) && (int)$image['quality']) {
                    $quality = (int)$image['quality'];
                }

                $command .= " -quality {$quality} +profile '*' ";

                $crop         = (false === empty($image['crop']));
                $resizeParams = '';

                if ($crop && false === in_array(mb_strtolower($image['crop']), $this->garvity)) {
                    $image['crop'] = self::CROP_DEFAULT;
                }
                if ($crop) {
                    $info = getimagesize($source);
                    if ($info) {
                        $crop = $info[0] / $info[1];
                    } else {
                        $crop = false;
                    }
                }

                if (false === empty($image['width']) || false === empty($image['height'])) {
                    YII_DEBUG && \Yii::trace($image, __METHOD__ . '(' . __LINE__ . ')');
                    if (empty($image['width'])) {
                        if (false === empty($image['height'])) {
                            if ($crop && 1 > $crop) {
                                $resizeParams .= "-resize '";
                            } else {
                                $resizeParams .= "-resize 'x";
                            }
                        }
                    } else {
                        if ($crop && 1 <= $crop) {
                            if (false === empty($image['height'])) {
                                if ((int)$image['height'] > (int)$image['width']) {
                                    $resizeParams .= "-resize 'x";
                                } else {
                                    $resizeParams .= "-resize 'x{$image['width']}";
                                }
                            } else {
                                $resizeParams .= "-resize 'x{$image['width']}";
                            }
                        } else {
                            $resizeParams .= "-resize '{$image['width']}x";
                        }
                    }

                    YII_DEBUG && \Yii::trace('$resizeParams: ' . $resizeParams, __METHOD__ . '(' . __LINE__ . ')');

                    if (empty($image['height'])) {
                        if (false === empty($image['width'])) {
                            $resizeParams .= ">' ";
                        }
                    } else {
                        if ($crop) {
                            if (1 > $crop) {
                                if (false === empty($image['width'])) {
                                    if ((int)$image['height'] > (int)$image['width']) {
                                        $resizeParams .= "{$image['height']}>' ";
                                    } else {
                                        $resizeParams .= ">' ";
                                    }
                                } else {
                                    $resizeParams .= "{$image['height']}x>' ";
                                }
                            } else {
                                $resizeParams .= ">' ";
                            }
                        } else {
                            $resizeParams .= "{$image['height']}>' ";
                        }
                    }
                    YII_DEBUG && \Yii::trace('$resizeParams: ' . $resizeParams, __METHOD__ . '(' . __LINE__ . ')');
                }

                if ($crop && false === empty($resizeParams)) {
                    $resizeParams .= " -gravity {$image['crop']} -crop {$image['width']}x{$image['height']}+0+0 +repage ";
                }
                $command .= $resizeParams;

                if ($count > ($index + 1)) {
                    $command .= " -write ";
                }
                $command .= "'{$target}{$image['suffix']}.{$params['extension']}'";
            }
            $return = !(bool)`{$command}`;

            if (false === empty($params['source_delete'])) {
                unlink($source);
            }
        }

        YII_DEBUG && \Yii::trace('$command: ' . $command, __METHOD__ . '(' . __LINE__ . ')');
        YII_DEBUG && \Yii::trace($return, __METHOD__ . '(' . __LINE__ . ')');

        return $return;
    }

    /**
     * Сортировка массива параметров конвертирования изображений по размерам<br/>
     * От большего к меньшему
     * 
     * @param mixed $targets Массив параметров
     * @return array Отсортированный массив параметров
     */
    public function sortTargets($targets)
    {
        $sorted = [];
        if (empty($targets) || false === is_array($targets)) {
            return $sorted;
        }

        $sources = $targets;

        foreach ($sources as $source) {
            $width  = 0;
            $height = 0;
            if (empty($source['width']) && empty($source['height'])) {
                $width  = 1000000;
                $height = 1000000;
            } else {
                if (false === empty($source['width'])) {
                    $width = (int)$source['width'];
                }
                if (false === empty($source['height'])) {
                    $height = (int)$source['height'];
                }
                if (0 < $width && 0 === $height) {
                    $height = $width;
                }
                if (0 < $height && 0 === $width) {
                    $width = $height;
                }
            }

            $sorted[$width * $height] = $source;
        }
        krsort($sorted, SORT_NUMERIC);
        return array_values($sorted);
    }

}
