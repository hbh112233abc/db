<?php
$options = [
    PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => 2,
    // PDO::ATTR_PERSISTENT              => true,
];

$conn = new PDO("odbc:Driver={GBase ODBC DRIVER (64-bit)};HOST=192.168.102.137;SERV=9088;PROT=onsoctcp;SRVR=gbase351;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8;sqlmode=oracle;", "gbasedbt", "GBase123$%", $options);

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
// $sql    = "INSERT INTO authors (first_name , last_name , biography) VALUES (?, ?, ?) ";
// $params = [
//     1 => '罗',
//     2 => '贯中',
//     3 => '元末明初小说家，以《三国演义》最为人所知。',
// ];
// $params = [
//     'ThinkBind_1_227461117_' => '罗',
//     'ThinkBind_2_594694550_' => '贯中',
//     'ThinkBind_3_184691396_' => '元末明初小说家，以《三国演义》最为人所知。',
// ];


// $sql = "INSERT INTO authors (first_name , last_name , biography) VALUES ('罗','贯中','元末明初小说家，以《三国演义》最为人所知。')";
// $sql = "INSERT INTO authors (first_name , last_name , biography) VALUES ('Luo','GuanZhong','A novelist of the late Yuan and early Ming dynasties, best known for his Romance of the Three Kingdoms.')";
// $res = query($conn, $sql);
// print_r($res);

// $sql = "INSERT INTO authors (first_name , last_name ) VALUES (?,?)";
$sql = "INSERT INTO authors (first_name , last_name ) VALUES (:first,:last)";
$sql = "INSERT INTO authors (first_name , last_name ) VALUES ('张','飞')";
// $sql = "INSERT INTO authors (first_name , last_name ) VALUES ('Zhang','Fei')";
// $sql = "update authors set first_name = :first where author_id = 6;";
// $sql = "select * from authors where author_id > 2";
// $res = query($conn, $sql, ['first' => "西汉", 'last' => '阿道夫1']);
$sql = "select * from authors where first_name like ?";
// $res = query($conn, $sql, [1 => '%西%']);

$sql    = "INSERT INTO categories2 (category_name , description) VALUES ( :ThinkBind_1_967973704_,:ThinkBind_2_411574836_ ) , ( :ThinkBind_3_1616502750_,:ThinkBind_4_1426350195_ )";
$params = [
    'ThinkBind_1_967973704_'  => ["历史小说", 2],
    'ThinkBind_2_411574836_'  => ["以历史事件为背景的小说", 3],
    'ThinkBind_3_1616502750_' => ["文学经典", 2],
    'ThinkBind_4_1426350195_' => ["具有重要文学价值和历史意义的经典作品", 3],
];
$res    = query($conn, $sql, $params);
print_r($res);
