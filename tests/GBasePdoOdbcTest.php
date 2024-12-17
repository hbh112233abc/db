<?php
namespace bingher\tests;

use bingher\ThinkTest\ThinkTest;
use PDO;

class GBasePdoOdbcTest extends ThinkTest
{
    public function query($conn, $sql, $params = [])
    {
        $sth = $conn->prepare($sql);
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $sth->bindValue($k, $v);
            }
        }
        $sth->execute();
        $res = $sth->fetchAll(PDO::FETCH_ASSOC);
        return $res;
    }
    public function test()
    {
        $dsn = 'odbc:Driver={GBase ODBC DRIVER (64-bit)};HOST=192.168.102.137;SERV=9088;PROT=onsoctcp;SRVR=gbase351;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8;UID=gbasedbt;PWD=GBase123$%;sqlmode=oracle;';
        $dbh = new PDO($dsn, '', '', [PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => 2]);
        $dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
        # 指定数据库连接指令

        $sql = "select dbms_random.value() from dual;";
        $sql = "select * from test order by dbms_random.value();";
        $sql = "SELECT MAX(book_id) AS think_max FROM books";
        $res = $this->query($dbh, $sql);
        var_dump($res);

    }
}
