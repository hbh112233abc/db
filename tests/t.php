<?php

use think\facade\Db;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/bingher/think-test/src/init.php';

$db   = Db::connect('dm');
$res = $db->name('users')->alias('u')->join('task t','t.uid = u.id','inner')->field('u.id,u.name,t.task')->select();
var_dump($res);
