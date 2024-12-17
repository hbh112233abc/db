<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/bingher/think-test/src/init.php';
use think\facade\Db;
$db   = Db::connect('gbase');
$data = [
    [
        'category_name' => '历史小说',
        'description'   => '以历史事件为背景的小说',
    ],
    [
        'category_name' => '文学经典',
        'description'   => '具有重要文学价值和历史意义的经典作品',
    ],
];

$res = $db->name('categories')->insertAll($data);
var_dump($res);
