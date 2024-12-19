<?php
$options = [
    PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => 2,
    // PDO::ATTR_PERSISTENT              => true,
];

$conn = new PDO("odbc:Driver={GBase ODBC DRIVER (64-bit)};HOST=192.168.102.137;SERV=9088;PROT=onsoctcp;SRVR=gbase351;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8;sqlmode=oracle;", "gbasedbt", "GBase123$%", $options);

$conn = new PDO("gbasedbt:HOST=192.168.102.137;SERV=9088;PROT=onsoctcp;SRVR=gbase351;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8;sqlmode=oracle;", "gbasedbt", "GBase123$%");

# 指定数据库连接指令
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_NATURAL);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);

function query($conn, $sql, $params = [])
{
    $sth = $conn->prepare($sql);
    if (!empty($params)) {
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $sth->bindValue($k, $v[0], $v[1]);
            } else {
                $sth->bindValue($k, $v);
            }
        }
    }
    $res = $sth->execute();
    if (stripos($sql, 'select') !== false) {
        $res = $sth->fetchAll(PDO::FETCH_ASSOC);
    }
    return $res;
}

$sql = "select dbinfo('version_gbase','full') from dual;";
$v   = query($conn, $sql);
var_dump($v[0]['']);

$sql    = "INSERT INTO authors (first_name , last_name , biography) VALUES (:ThinkBind_1_227461117_ , :ThinkBind_2_594694550_ , :ThinkBind_3_184691396_) ";
$params = [
    'ThinkBind_1_227461117_' => ['罗', 2],
    'ThinkBind_2_594694550_' => ['贯中', 2],
    'ThinkBind_3_184691396_' => ['元末明初小说家，以《三国演义》最为人所知。', 2],
];

// $sql = "INSERT INTO authors (first_name , last_name ) VALUES (?,?)";
// $res = query($conn, $sql, [1 => "西汉", 2=> '阿道夫1']);
// $sql = "INSERT INTO authors (first_name , last_name ) VALUES (:first,:last)";
// $res = query($conn, $sql, ['first' => "西汉", 'last' => '阿道夫1']);
// $sql = "select * from authors where first_name like ?";
// $res = query($conn, $sql, [1 => '%西%']);

// $sql    = "INSERT INTO categories2 (category_name , description) VALUES ( :ThinkBind_1_967973704_,:ThinkBind_2_411574836_ ) , ( :ThinkBind_3_1616502750_,:ThinkBind_4_1426350195_ )";
// $params = [
//     'ThinkBind_1_967973704_'  => ["历史小说", 2],
//     'ThinkBind_2_411574836_'  => ["以历史事件为背景的小说", 3],
//     'ThinkBind_3_1616502750_' => ["文学经典", 2],
//     'ThinkBind_4_1426350195_' => ["具有重要文学价值和历史意义的经典作品", 3],
// ];

$sql = "MERGE INTO books t1 USING
(SELECT :ThinkBind_1_267255032_1 AS book_id,:ThinkBind_2_1660145108_1 AS title,:ThinkBind_3_2096761282_1 AS isbn,:ThinkBind_4_377606364_1 AS publisher,:ThinkBind_5_931818477_1 AS publication_date,:ThinkBind_6_1629230867_1 AS language,:ThinkBind_7_815776853_1 AS page_count,:ThinkBind_8_63928704_1 AS summary from dual) t2
ON (t1.book_id = t2.book_id)
WHEN MATCHED THEN
UPDATE SET title = :ThinkBind_2_1660145108_2,isbn = :ThinkBind_3_2096761282_2,publisher = :ThinkBind_4_377606364_2,publication_date = :ThinkBind_5_931818477_2,language = :ThinkBind_6_1629230867_2,page_count = :ThinkBind_7_815776853_2,summary = :ThinkBind_8_63928704_2
WHEN NOT MATCHED THEN
INSERT (book_id,title,isbn,publisher,publication_date,language,page_count,summary) VALUES (:ThinkBind_1_267255032_3,:ThinkBind_2_1660145108_3,:ThinkBind_3_2096761282_3,:ThinkBind_4_377606364_3,:ThinkBind_5_931818477_3,:ThinkBind_6_1629230867_3,:ThinkBind_7_815776853_3,:ThinkBind_8_63928704_3)";

$params = [
    'ThinkBind_1_267255032_1'  => [5, 1],
    'ThinkBind_2_1660145108_1' => '了不起的锅盖饭',
    'ThinkBind_3_2096761282_1' => '9787544274188',
    'ThinkBind_4_377606364_1'  => '上海文艺出版社',
    'ThinkBind_5_931818477_1'  => '1999-01-01',
    'ThinkBind_6_1629230867_1' => '中文',
    'ThinkBind_7_815776853_1'  => [218, 1],
    'ThinkBind_8_63928704_1'   => '一部以爵士时代为背景的小说。',
    'ThinkBind_1_267255032_2'  => [5, 1],
    'ThinkBind_2_1660145108_2' => '了不起的锅盖饭',
    'ThinkBind_3_2096761282_2' => '9787544274188',
    'ThinkBind_4_377606364_2'  => '上海文艺出版社',
    'ThinkBind_5_931818477_2'  => '1999-01-01',
    'ThinkBind_6_1629230867_2' => '中文',
    'ThinkBind_7_815776853_2'  => [218, 1],
    'ThinkBind_8_63928704_2'   => '一部以爵士时代为背景的小说。',
    'ThinkBind_1_267255032_3'  => [5, 1],
    'ThinkBind_2_1660145108_3' => '了不起的锅盖饭',
    'ThinkBind_3_2096761282_3' => '9787544274188',
    'ThinkBind_4_377606364_3'  => '上海文艺出版社',
    'ThinkBind_5_931818477_3'  => '1999-01-01',
    'ThinkBind_6_1629230867_3' => '中文',
    'ThinkBind_7_815776853_3'  => [218, 1],
    'ThinkBind_8_63928704_3'   => '一部以爵士时代为背景的小说。',
];

$res = query($conn, $sql, $params);
print_r($res);
