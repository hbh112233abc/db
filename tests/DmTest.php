<?php
namespace bingher\tests;

use bingher\ThinkTest\ThinkTest;
use think\facade\Db;

class DmTest extends ThinkTest
{
    public function testConnect()
    {
        $db   = Db::connect('dm');
        $data = $db->getTables();
        dump($data);
        $this->assertIsArray($data);
    }

    public function __testDropTable()
    {
        $db     = Db::connect('dm');
        $tables = $db->getTables();
        foreach ($tables as $table) {
            $sql = "DROP TABLE IF EXISTS {$table}";
            $res = $db->execute($sql);
            dump($table, $res);
        }
    }

    public function testInsert()
    {
        $db  = Db::connect('dm');
        $ad  = [
            "id"          => 11,
            "sno"         => 0,
            "title"       => "别动",
            "img_url"     => "https://hymake-efile-download.oss-cn-hangzhou.aliyuncs.com\\carousel/61cad1c472fe2.jpg",
            "link_url"    => "",
            "expire_date" => "2021-12-28 00:00:00",
            "status"      => 0,
        ];
        $res = $db->name('hy_ad')->insert($ad);
        dump($res);
        $this->assertTrue($res == 1);
    }

    public function testInsertAll()
    {
        $db   = Db::connect('dm');
        $data = [
            [
                "id"          => 3,
                "sno"         => 0,
                "comno"       => "003",
                "comname"     => "海迈测试2",
                "addr"        => "观日路20",
                "tel"         => "18950505050",
                "create_time" => "2019-11-20 14:17:34",
                "province"    => "天津市",
                "city"        => "天津城区",
                "area"        => "和平区",
                "status"      => 1,
                "username"    => "admin2",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "123",
                "formal"      => 1,
            ],
            [
                "id"          => 4,
                "sno"         => 0,
                "comno"       => "004",
                "comname"     => "海迈测试1",
                "addr"        => "观日路20",
                "tel"         => "13123232323",
                "create_time" => "2019-11-20 14:20:41",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 1,
                "username"    => "admin3",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "123456",
                "formal"      => 1,
            ],
            [
                "id"          => 5,
                "sno"         => 0,
                "comno"       => "005",
                "comname"     => "海迈测试4",
                "addr"        => "观日路20",
                "tel"         => "13123232323",
                "create_time" => "2019-11-20 14:23:47",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 1,
                "username"    => "admin4",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "123",
                "formal"      => 1,
            ],
            [
                "id"          => 7,
                "sno"         => 0,
                "comno"       => "1212",
                "comname"     => "档案公司001",
                "addr"        => "12121",
                "tel"         => "13832121232",
                "create_time" => "2019-12-04 15:04:12",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 1,
                "username"    => "1204001",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "454545",
                "formal"      => 1,
            ],
            [
                "id"          => 8,
                "sno"         => 0,
                "comno"       => "0021",
                "comname"     => "档案公司0021",
                "addr"        => "档案公司002详细地址345",
                "tel"         => "13645451133",
                "create_time" => "2019-12-04 16:21:14",
                "province"    => "内蒙古自治区",
                "city"        => "呼和浩特市",
                "area"        => "新城区",
                "status"      => 1,
                "username"    => "12040021",
                "password"    => "d41d8cd98f00b204e9800998ecf8427e",
                "contact"     => "002111",
                "formal"      => 1,
            ],
            [
                "id"          => 16,
                "sno"         => 0,
                "comno"       => "0016",
                "comname"     => "海迈装修",
                "addr"        => "福建厦门市市辖区",
                "tel"         => "13678451233",
                "create_time" => "2019-12-10 15:52:21",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 0,
                "username"    => "123456",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "小力",
                "formal"      => 1,
            ],
            [
                "id"          => 17,
                "sno"         => 0,
                "comno"       => "121101",
                "comname"     => "121101",
                "addr"        => "连城县建设工程质量安全监督站",
                "tel"         => "13678451233",
                "create_time" => "2019-12-11 10:24:43",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 2,
                "username"    => "121101",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "大厦",
                "formal"      => 1,
            ],
            [
                "id"          => 18,
                "sno"         => 0,
                "comno"       => "121104",
                "comname"     => "1211035",
                "addr"        => "连城县建设工程质量安全监督站",
                "tel"         => "13678451233",
                "create_time" => "2019-12-11 16:09:43",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 0,
                "username"    => "121104",
                "password"    => "21b95a0f90138767b0fd324e6be3457b",
                "contact"     => "0003",
                "formal"      => 1,
            ],
            [
                "id"          => 19,
                "sno"         => 0,
                "comno"       => "121103",
                "comname"     => "121103",
                "addr"        => "连城县建设工程质量安全监督站",
                "tel"         => "13645451133",
                "create_time" => "2019-12-11 16:10:14",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 2,
                "username"    => "121103",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "00031",
                "formal"      => 1,
            ],
            [
                "id"          => 21,
                "sno"         => 0,
                "comno"       => "535",
                "comname"     => "1211",
                "addr"        => "湖北黄石市西塞山区",
                "tel"         => "18650803789",
                "create_time" => "2019-12-11 16:15:49",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 0,
                "username"    => "232202",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "1211",
                "formal"      => 1,
            ],
            [
                "id"          => 23,
                "sno"         => 0,
                "comno"       => "121102",
                "comname"     => "121102",
                "addr"        => "福建宁德市周宁县",
                "tel"         => "13645451133",
                "create_time" => "2019-12-11 17:01:23",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 1,
                "username"    => "121102",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "状态",
                "formal"      => 1,
            ],
            [
                "id"          => 24,
                "sno"         => 0,
                "comno"       => "121201",
                "comname"     => "121201",
                "addr"        => "广西钦州市灵山县",
                "tel"         => "18650803378",
                "create_time" => "2019-12-12 11:17:12",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 1,
                "username"    => "121201",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "0012",
                "formal"      => 1,
            ],
            [
                "id"          => 25,
                "sno"         => 0,
                "comno"       => "200526",
                "comname"     => "档案公司单位名0526",
                "addr"        => "吉林省通化市通化县",
                "tel"         => "18650802222",
                "create_time" => "2020-05-26 15:17:46",
                "province"    => "吉林省",
                "city"        => "通化市",
                "area"        => "通化县",
                "status"      => 0,
                "username"    => "da052601",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "档案公司0526",
                "formal"      => 1,
            ],
            [
                "id"          => 26,
                "sno"         => 0,
                "comno"       => "052602",
                "comname"     => "档案公司052602",
                "addr"        => "福建省龙岩市武平县",
                "tel"         => "18650801111",
                "create_time" => "2020-05-26 15:31:44",
                "province"    => "福建省",
                "city"        => "龙岩市",
                "area"        => "武平县",
                "status"      => 0,
                "username"    => "da052602",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "da052602",
                "formal"      => 1,
            ],
            [
                "id"          => 27,
                "sno"         => 0,
                "comno"       => "dangan666",
                "comname"     => "testdanan666",
                "addr"        => "福建省厦门市思明区",
                "tel"         => "18711122211",
                "create_time" => "2020-06-12 14:17:12",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 1,
                "username"    => "testdangangs",
                "password"    => "0f7e44a922df352c05c5f73cb40ba115",
                "contact"     => "6666666666",
                "formal"      => 0,
            ],
            [
                "id"          => 28,
                "sno"         => 0,
                "comno"       => "1121212",
                "comname"     => "测试",
                "addr"        => "福建省厦门市思明区",
                "tel"         => "18750089808",
                "create_time" => "2020-07-16 17:09:16",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 1,
                "username"    => "testtest",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "lll",
                "formal"      => 1,
            ],
            [
                "id"          => 29,
                "sno"         => 0,
                "comno"       => "dfsadf3",
                "comname"     => "asdfsa73",
                "addr"        => "sadfsa73",
                "tel"         => "13265322633",
                "create_time" => "2022-08-16 09:39:16",
                "province"    => "北京市",
                "city"        => "北京城区",
                "area"        => "东城区",
                "status"      => 1,
                "username"    => "dfasfsa73",
                "password"    => "c33367701511b4f6020ec61ded352059",
                "contact"     => "xdfsd73",
                "formal"      => 1,
            ],
            [
                "id"          => 30,
                "sno"         => 0,
                "comno"       => "撒地方撒地方",
                "comname"     => "地方的双方各",
                "addr"        => "12312",
                "tel"         => "13623003263",
                "create_time" => "2022-08-16 15:03:13",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 0,
                "username"    => "31231231",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "12312sadf",
                "formal"      => 1,
            ],
            [
                "id"          => 31,
                "sno"         => 0,
                "comno"       => "但是公司大",
                "comname"     => "的SV格式的风格",
                "addr"        => "阿斯蒂芬asd",
                "tel"         => "13623633263",
                "create_time" => "2022-08-16 15:24:10",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 1,
                "username"    => " 阿斯蒂芬sad发送到",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "蓄电池V型橙V",
                "formal"      => 1,
            ],
            [
                "id"          => 32,
                "sno"         => 0,
                "comno"       => "123513213212",
                "comname"     => "啥地方撒的发生的发1",
                "addr"        => "的双方各的双方各大师傅个1",
                "tel"         => "13663266320",
                "create_time" => "2022-08-16 15:34:07",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "湖里区",
                "status"      => 1,
                "username"    => "ds56g4d5s4fg2",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "12幸福感1",
                "formal"      => 1,
            ],
            [
                "id"          => 33,
                "sno"         => 0,
                "comno"       => "haha",
                "comname"     => "haha",
                "addr"        => "ahah",
                "tel"         => "15888888888",
                "create_time" => "2022-09-01 13:41:50",
                "province"    => "辽宁省",
                "city"        => "沈阳市",
                "area"        => "和平区",
                "status"      => 1,
                "username"    => "haha",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "haha",
                "formal"      => 1,
            ],
            [
                "id"          => 34,
                "sno"         => 0,
                "comno"       => "221108",
                "comname"     => "档案company",
                "addr"        => "11",
                "tel"         => "15000000000",
                "create_time" => "2022-11-08 15:23:43",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 1,
                "username"    => "1发过火规范",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "采购部发过火2覆盖",
                "formal"      => 1,
            ],
            [
                "id"          => 35,
                "sno"         => 0,
                "comno"       => "DA0915",
                "comname"     => "档案公司0915",
                "addr"        => "guanrilu ",
                "tel"         => "15000000000",
                "create_time" => "2023-09-15 16:47:45",
                "province"    => "福建省",
                "city"        => "厦门市",
                "area"        => "思明区",
                "status"      => 1,
                "username"    => "dangan 0915",
                "password"    => "e10adc3949ba59abbe56e057f20f883e",
                "contact"     => "yyh",
                "formal"      => 1,
            ],
        ];
        $db->name('hy_archive_company')->where("1=1")->delete();

        $res = $db->name('hy_archive_company')->insertAll($data);
        $this->assertEquals($res, 23);
    }
    public function testUpdate()
    {
        $db = Db::connect('dm');

        $title = "别动UTF-8";
        $res   = $db->name('hy_ad')->where('id', 9)->update(['title' => $title]);
        dump($res);
        $this->assertTrue($res == 1);
    }

    public function testKeyword()
    {
        $db   = Db::connect('dm');
        $data = [
            "mysqlid" => "backup_database",
            "ip"      => "192.168.103.38",
            "port"    => 3306,
            "user"    => "root1",
            "pwd"     => "xmhymake1",
            "state"   => 1
        ];
        $res  = $db->table('hy_mysql')->where('mysqlid', $data['mysqlid'])->update($data);
        $this->assertEquals($res, 1);
    }
    public function testInsertGetId()
    {
        $db   = Db::connect('dm');
        $data = [
            "mysqlid" => "backup_database",
            "ip"      => "192.168.103.38",
            "port"    => 3306,
            "user"    => "root1",
            "pwd"     => "xmhymake1",
            "state"   => 1
        ];
        $res  = $db->table('hy_mysql')->insertGetId($data);
        dump($res);
        $this->assertGreaterThan(1, $res);
    }

    public function testNotPk()
    {
        $dir = array(
            "cdefid"      => "202404180857",
            "ccode"       => "f65d1932e0da11eea6a1005056b220a7",
            "sno"         => 88,
            "dircode"     => "4.2.23",
            "title"       => "竣工图",
            "titleprop"   => "",
            "pcdefid"     => "1684476ce0e011eeac27005056b220a7",
            "subject"     => "project",
            "props"       => "[{\"name\":\"b_projname\",\"displaytext\":\"工程项目名称\",\"roletype\":\"0\",\"visible\":\"1\",\"value\":\"\",\"default\":\"project.s_name\",\"feature\":\"0\",\"no\":1,\"source\":\"0\"},{\"name\":\"b_projid\",\"displaytext\":\"工程项目ID\",\"roletype\":\"0\",\"visible\":\"0\",\"value\":\"\",\"default\":\"project.s_id\",\"feature\":\"1\",\"no\":2,\"source\":\"0\"},{\"name\":\"b_constructor_comrole\",\"displaytext\":\"建设单位\",\"roletype\":\"1\",\"visible\":\"1\",\"value\":\"\",\"default\":\"project.b_constructor_comrole\",\"feature\":\"0\",\"no\":3,\"source\":\"0\"},{\"name\":\"b_qualityorg_comroles\",\"displaytext\":\"质量监督机构\",\"roletype\":\"1\",\"visible\":\"1\",\"value\":\"\",\"default\":\"project.b_qualityorg_comroles\",\"feature\":\"0\",\"no\":4,\"source\":\"0\"},{\"name\":\"b_safetyorg_comroles\",\"displaytext\":\"安全监督机构\",\"roletype\":\"1\",\"visible\":\"1\",\"value\":\"\",\"default\":\"project.b_safetyorg_comroles\",\"feature\":\"0\",\"no\":5,\"source\":\"0\"},{\"name\":\"b_unitprojname\",\"displaytext\":\"单位工程名称\",\"roletype\":\"0\",\"visible\":\"1\",\"value\":\"\",\"default\":\"unitproj.s_name\",\"feature\":\"0\",\"no\":6,\"source\":\"0\"},{\"name\":\"b_unitprojid\",\"displaytext\":\"单位工程ID\",\"roletype\":\"0\",\"visible\":\"1\",\"value\":\"\",\"default\":\"unitproj.s_id\",\"feature\":\"1\",\"no\":7,\"source\":\"0\"},{\"name\":\"b_fileclsno\",\"displaytext\":\"文件分类代码\",\"roletype\":\"0\",\"visible\":\"1\",\"value\":\"4.2.23\",\"default\":\"\",\"feature\":\"1\",\"no\":8,\"source\":1},{\"name\":\"b_fileclsname\",\"displaytext\":\"文件分类名称\",\"roletype\":\"0\",\"visible\":\"1\",\"value\":\"竣工图\",\"default\":\"\",\"feature\":\"1\",\"no\":9,\"source\":1}]",
            "kvs"         => "b_projid,b_unitprojid,b_fileclsno",
            "clevel"      => 5,
            "dircodeprop" => null,
            "clsid"       => null,
            "psno"        => 60,
            "codepath"    => "_1_2_2.4_4.2_4.2.23",
            "left"        => 148,
            "right"       => 149
        );
        Db::table('hy_cabtemplates_dirs')->where('cdefid', $dir['cdefid'])->delete();
        $pk = Db::table('hy_cabtemplates_dirs')->insertGetId($dir);
        dump($pk);
        $this->assertEquals($pk, 1);
        Db::table('hy_cabtemplates_dirs')->where('cdefid', $dir['cdefid'])->delete();
    }

}
