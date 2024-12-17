<?php
namespace bingher\tests;

use bingher\ThinkTest\ThinkTest;

class GBaseODBCTest extends ThinkTest
{
    public function test()
    {
        $dsn  = 'Driver={GBase ODBC DRIVER (64-bit)};HOST=192.168.102.137;SERV=9088;PROT=onsoctcp;SRVR=gbase351;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8;UID=gbasedbt;PWD=GBase123$%;sqlmode=oracle;';
        $conn = odbc_connect($dsn, '', '');
        if (!$conn) {
            exit("Connection Failed: " . $conn);
        }

        $sql = "SELECT MAX(book_id) AS think_max FROM books";
        $rs  = odbc_exec($conn, $sql);
        $res = odbc_fetch_array($rs);
        print_r($res);

    }
}
