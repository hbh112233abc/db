<?php

use think\facade\Db;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/bingher/think-test/src/init.php';

$db   = Db::connect('dm');
$res = $db->name('users')->select();
var_dump($res);
