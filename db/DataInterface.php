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

    public function getData($remove = false);

    public function setData($params, $arc = false);

    public function getJobs();

    public function renameJob($source, $target);

    public function removeJobData();

    public function getCount();

    public function getOne($remove = false);

    public function getAll();
}
