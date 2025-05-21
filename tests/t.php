<?php

use think\facade\Db;
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/bingher/think-test/src/init.php';

$where = [
    ["b.status", '=',1],
	["a.projid", '=', 'ls20241114091939'],
];

$res = Db::connect('dm')->table('hy_projects')->alias('a')
->leftJoin('EFILEYUN.hy_projunits b', 'a.recid = b.recid')
->leftJoin('hy_memberrec c', 'b.memid=c.memid')
->leftJoin('hy_cabinets d', 'b.memid=d.memid')
->field('b.memid,b.comname,c.mtype,b.comtype,d.cno AS comcode')
->where($where)
->order('b.memid')
->select();
var_dump($res);
