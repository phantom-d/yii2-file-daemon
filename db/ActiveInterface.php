<?php

namespace phantomd\filedaemon\db;

/**
 * DataInterface
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface ActiveInterface
{

    /**
     * @event ModelEvent an event that is triggered before remove a table.
     * You may set [[ModelEvent::isValid]] to be false to stop the remove.
     */
    const EVENT_BEFORE_REMOVE = 'beforeRemove';

    /**
     * @event Event an event that is triggered after a table is removed.
     */
    const EVENT_AFTER_REMOVE = 'afterRemove';

    /**
     * @event ModelEvent an event that is triggered before rename a table.
     * You may set [[ModelEvent::isValid]] to be false to stop the rename.
     */
    const EVENT_BEFORE_RENAME = 'beforeRename';

    /**
     * @event Event an event that is triggered after a table is renamed.
     */
    const EVENT_AFTER_RENAME = 'afterRename';

    public static function getDb();

    public static function model($params = []);

    public static function count($params = []);

    public static function one($params = []);

    public static function all($params = [], $limit = 10, $page = 0);

    public static function names($params = []);

    public static function groups($params = []);

    public function rename($params = []);

    public function remove();

    public function save($params = []);
}
