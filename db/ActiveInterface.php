<?php

namespace phantomd\filedaemon\db;

/**
 * DataInterface
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface ActiveInterface
{

    public static function getDb();

    public static function getData($params = []);

    public static function setData($params = []);

    public static function getJobs();

    public static function renameJob($params = []);

    public static function removeJobData();

    public static function getCount($params = []);

    public static function getOne($params = []);

    public static function getAll($params = []);
}
