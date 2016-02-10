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

    /**
     * Get database connection
     */
    public static function getDb();

    /**
     * Create model
     * 
     * @param array $params Data for creating
     */
    public static function model($params = []);

    /**
     * Get count rows from database
     * 
     * @param array $params Condition array
     */
    public static function count($params = []);

    /**
     * Get one row from database
     * 
     * @param array $params Condition array
     * @param bool $remove Delete reciving row from database
     */
    public static function one($params = [], $remove = false);

    /**
     * Get rows from database
     * 
     * @param array $params Condition array
     * @param int $limit Limit rows
     * @param int $page Page of request
     */
    public static function all($params = [], $limit = 10, $page = 0);

    /**
     * Get names from database
     * 
     * @param array $params Condition array
     */
    public static function names($params = []);

    /**
     * Get groups from database
     * 
     * @param array $params Condition array
     */
    public static function groups($params = []);

    /**
     * Change name of row
     * 
     * @param array $params Condition array
     */
    public function rename($params = []);

    /**
     * Delete all rows with current name
     */
    public function remove();

    /**
     * Save data to database
     */
    public function save();
}
