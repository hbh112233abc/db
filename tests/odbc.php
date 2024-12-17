<?php
$dsn  = 'Driver={GBase ODBC DRIVER (64-bit)};HOST=192.168.102.137;SERV=9088;PROT=onsoctcp;SRVR=gbase351;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8;UID=gbasedbt;PWD=GBase123$%;sqlmode=oracle;';
$conn = odbc_connect($dsn, '', '');
if (!$conn) {
    exit("Connection Failed: " . $conn);
}

function query($conn, $sql)
{
    $res = odbc_exec($conn, $sql);
    if (stripos($sql, 'select') !== false) {
        $res = odbc_fetch_array($res);
    }
    return $res;
}

$sql = "select dbinfo('version_gbase','full') from dual;";
$v   = query($conn, $sql);
var_dump($v);

$sql = "INSERT INTO authors1 (first_name , last_name ) VALUES ('张','飞')";
$sql = "INSERT INTO authors1 (first_name , last_name ) VALUES ('zhang','fei')";
$res = odbc_exec($conn, $sql);
print_r($res);
