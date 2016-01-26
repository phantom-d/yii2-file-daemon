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
