<?php

use think\facade\Db;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/bingher/think-test/src/init.php';

$db   = Db::connect('gbase_gauss');
$data = array(
    'key'         => md5(time()),
    'code'        => '35b91e042650d08c619d0446fc6629c5',
    'captcha'     => '{"text":[{"size":20,"icon":true,"name":"banana","text":"<香蕉>","width":40,"height":32,"x":191,"y":45},{"size":15,"icon":false,"name":"banana","text":"赶","width":20,"height":19,"x":315,"y":161}],"width":350,"height":200}',
    'create_time' => 1735616906,
    'expire_time' => 1735617506,
);
$res  = $db->table('ba_captcha')->insertGetId($data);
dump($res);
assert($data['key'] == $res);
