<?php

namespace phantomd\filedaemon\common\controllers;

use yii\base\NotSupportedException;
use phantomd\filedaemon\console\controllers\DaemonController;

/**
 * Class StreakDaemonController. Intermediate class for daemons.
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
