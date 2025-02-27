<?php

use think\facade\Db;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/bingher/think-test/src/init.php';

$db   = Db::connect('open_gauss');
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
dump($res);
assert($res == 2);
