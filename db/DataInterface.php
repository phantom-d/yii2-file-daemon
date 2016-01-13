<?php

namespace phantomd\filedaemon\db;

/**
 * DataInterface
 * 
 * @author Anton Ermolovich <anton.ermolovich@gmail.com>
 */
interface DataInterface
{

    public static function getDb();

    public static function getData($remove = false);

    public static function setData($params, $arc = false);

    public static function getJobs();

    public static function renameJob($source, $target);

    public static function removeJobData();

    public static function getCount();

    public static function getOne($remove = false);

    public static function getAll();
}
