<?php
namespace bingher\tests;

use bingher\ThinkTest\ThinkTest;
use think\facade\Db;

class TypeTest extends ThinkTest
{
    public function testNumber()
    {
        $data = Db::table('hy_ad')->where('id', 1)->find();
        dump($data);
        $this->assertTrue($data['id'] === 1);
    }
}
