<?php

$group    = 'test_source';
$name     = $group . '::' . microtime(true);
$fileName = '92ef71ca0f8ad068d2c66987bc64636e';
$objectId = 'test_object';
$fileId   = 'test_file';
$timeDir  = '/2016/02/19/15/02/';

$source = [
    'command'   => 0,
    'object_id' => $objectId,
    'url'       => 'https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_150x54dp.png',
    'file_id'   => $fileId,
    'score'     => 0,
    'name'      => $name,
];

$result = [
    'command'   => 0,
    'object_id' => $objectId,
    'file_id'   => $fileId,
    'file_name' => $fileName,
    'time_dir'  => $timeDir,
    'score'     => 0,
    'name'      => $group . '::' . md5($name),
];

$arcresult = [
    'name' => $fileName,
    'path' => '/uploads/images' . $timeDir . $fileName,
];

$jobs = [
    'pid'           => 0,
    'name'          => $name,
    'group'         => $group,
    'callback'      => 'http://www.image-server.local/daemon/callback/',
    'status'        => 1,
    'total'         => 1,
    'complete'      => 1,
    'errors'        => 1,
    'time_create'   => 1455884399,
    'time_elapsed'  => 0,
    'time_per_item' => 0,
    'time_to_left'  => 0,
    'time_end'      => 1455884399,
];

return [
    'source'    => $source,
    'result'    => $result,
    'arcresult' => $arcresult,
    'jobs'      => $jobs,
    'fields'    => [
        'source'    => 'file_id',
        'result'    => 'file_id',
        'arcresult' => 'name',
        'jobs'      => 'name',
    ],
    'test'      => [
        'source',
        'result',
    ],
];
