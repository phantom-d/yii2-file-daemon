<?php

namespace phantomd\filedaemon\commands\controllers;

use yii\base\NotSupportedException;

/**
 * Class StreakDaemonController
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
