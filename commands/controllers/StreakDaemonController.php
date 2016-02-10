<?php

namespace phantomd\filedaemon\commands\controllers;

use yii\base\NotSupportedException;

/**
 * Class StreakDaemonController. Intermediate class.
 *
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
class StreakDaemonController extends DaemonController
{

    use \phantomd\filedaemon\traits\DaemonTrait;

    /**
     * @inheritdoc
     */
    protected function defineJobs()
    {
        throw new NotSupportedException();
    }

    /**
     * @inheritdoc
     */
    protected function doJob($job)
    {
        throw new NotSupportedException();
    }

}
