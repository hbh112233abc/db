<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/bingher/think-test/src/init.php';
use think\facade\Db;
$db     = Db::connect('gbase');
$bookId = $db->table('books')->where('isbn', '9787544274188')->value('book_id');
$book   = [
    'book_id'          => $bookId,
    'title'            => '了不起的锅盖饭',
    'isbn'             => '9787544274188',
    'publisher'        => '上海文艺出版社',
    'publication_date' => '1999-01-01',
    'language'         => '中文',
    'page_count'       => 218,
    'summary'          => '一部以爵士时代为背景的小说。'
];
$res    = $db->table('books')->replace()->insert($book);
var_dump($res);
