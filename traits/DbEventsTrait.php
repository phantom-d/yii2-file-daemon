<?php

namespace phantomd\filedaemon\traits;

use yii\base\ModelEvent;

trait DbEventsTrait
{

    /**
     * This method is invoked before removing a table.
     * The default implementation raises the [[EVENT_BEFORE_REMOVE]] event.
     * When overriding this method, make sure you call the parent implementation like the following:
     *
     * ```php
     * public function beforeRemove()
     * {
     *     if (parent::beforeRemove()) {
     *         // ...custom code here...
     *         return true;
     *     } else {
     *         return false;
     *     }
     * }
     * ```
     *
     * @return boolean whether the table should be removed. Defaults to true.
     */
    public function beforeRemove()
    {
        $event = new ModelEvent;
        $this->trigger(self::EVENT_BEFORE_REMOVE, $event);

        return $event->isValid;
    }

    /**
     * This method is invoked after remove a table.
     * The default implementation raises the [[EVENT_AFTER_REMOVE]] event.
     * You may override this method to do postprocessing after the table is removed.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    public function afterRemove()
    {
        $event = new ModelEvent;
        $this->trigger(self::EVENT_AFTER_REMOVE, $event);

        return $event->isValid;
    }

    /**
     * This method is invoked before renaming a table.
     * The default implementation raises the [[EVENT_BEFORE_RENAME]] event.
     * When overriding this method, make sure you call the parent implementation like the following:
     *
     * ```php
     * public function beforeRename()
     * {
     *     if (parent::beforeRename()) {
     *         // ...custom code here...
     *         return true;
     *     } else {
     *         return false;
     *     }
     * }
     * ```
     *
     * @return boolean whether the table should be renamed. Defaults to true.
     */
    public function beforeRename()
    {
        $event = new ModelEvent;
        $this->trigger(self::EVENT_BEFORE_RENAME, $event);

        return $event->isValid;
    }

    /**
     * This method is invoked after rename a table.
     * The default implementation raises the [[EVENT_AFTER_RENAME]] event.
     * You may override this method to do postprocessing after the table is renamed.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    public function afterRename()
    {
        $event = new ModelEvent;
        $this->trigger(self::EVENT_AFTER_RENAME, $event);

        return $event->isValid;
    }
}
