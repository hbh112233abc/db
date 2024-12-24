<?php
declare(strict_types=1);
namespace bingher\tests;
require_once __DIR__ . '/../vendor/autoload.php';

use PHPUnit\Framework\Attributes\Depends;
use bingher\ThinkTest\ThinkTest;
use bingher\db\connector\OpenGauss;
use think\facade\Db;

final class OpenGaussTest extends ThinkTest
{
    /**
     * OpenGauss
     * @var OpenGauss
     */
    static $DB;
    public static function setUpBeforeClass(): void
    {
        static::$DB = Db::connect('open_gauss');
    }
    public function testConnect()
    {
        $data = static::$DB->getTables();
        dump($data);
        $this->assertIsArray($data);
        $this->assertTrue(in_array('books', $data));
    }

    public function testGetFields()
    {
        $fields = static::$DB->getFields('authors');
        dump($fields);
        $this->assertIsArray($fields);
    }

    public function __testDropTable()
    {
        $tables = static::$DB->getTables();
        foreach ($tables as $table) {
            $sql = "DROP TABLE IF EXISTS {$table}";
            $res = static::$DB->execute($sql);
            dump($table, $res);
        }
    }

    public function testMax()
    {
        $maxId = static::$DB->table('books')->max('book_id');
        dump($maxId);
        $this->assertIsFloat($maxId);
        $this->assertTrue($maxId > 0);
    }

    public function testInsert()
    {
        $author = [
            'first_name' => '罗',
            'last_name'  => '罗贯中',
            'biography'  => '元末明初小说家，以《三国演义》最为人所知。',
        ];
        $res    = static::$DB->name('authors')->insert($author);
        var_dump($res);
        $this->assertTrue($res == 1);
    }

    /**
     * TODO 带有text字段的数据执行成功但是没插入数据,当前暂时改为遍历单条插入
     * @return void
     */
    public function testMutInsert()
    {
        $data = [
            [
                'category_name' => '历史小说',
                'description'   => '以历史事件为背景的小说',
            ],
            [
                'category_name' => '文学经典',
                'description'   => '具有重要文学价值和历史意义的经典作品',
            ],
        ];

        $res = static::$DB->name('categories')->insertAll($data);
        $this->assertEquals($res, count($data));

        $books = [
            [
                'title'            => '测试书籍1',
                'isbn'             => '1234567890123',
                'publisher'        => '测试出版社1',
                'publication_date' => '2023-01-01 00:00:00',
                'language'         => '中文',
                'page_count'       => 100,
                'summary'          => '这是一本测试书籍1的简介。',
            ],
            [
                'title'            => '测试书籍2',
                'isbn'             => '1234567890124',
                'publisher'        => '测试出版社2',
                'publication_date' => '2023-02-01 00:00:00',
                'language'         => '英文',
                'page_count'       => 200,
                'summary'          => 'This is a summary of Test Book 2.',
            ],
            [
                'title'            => '测试书籍3',
                'isbn'             => '1234567890125',
                'publisher'        => '测试出版社3',
                'publication_date' => '2023-03-01 00:00:00',
                'language'         => '中文',
                'page_count'       => 300,
                'summary'          => '这是一本测试书籍3的简介。',
            ],
        ];
        $res   = static::$DB->table('books')->insertAll($books);
        $this->assertEquals($res, count($books));
        static::$DB->table('books')->where('title', 'like', '测试%')->delete();
    }

    public function testGetInsertId()
    {
        $bookData = [
            'title'            => '三国演义',
            'isbn'             => '9787101127418',
            'publisher'        => '中华书局',
            'publication_date' => '2006-01-01',
            'language'         => '中文',
            'page_count'       => 1230,
            'summary'          => '中国古代历史小说的巅峰之作，描绘了三国时期的英雄人物和战争故事。'
        ];
        $maxId    = static::$DB->table('books')->max('book_id');
        dump($maxId);
        $bookId = static::$DB->table('books')->insertGetId($bookData);
        dump($bookId);
        $this->assertGreaterThan($maxId, $bookId);
        return $bookId;
    }

    #[Depends('testInsertGetId')]
    public function testUpdate($bookId = 0)
    {
        if (!$bookId) {
            $bookId = static::$DB->table('books')->value('book_id');
        }
        $setPageCount = 959;
        $res          = static::$DB->name('books')->where('book_id', $bookId)->update(['page_count' => $setPageCount]);
        dump($res);
        $this->assertTrue($res == 1);
        $pageCount = static::$DB->table('books')->where('book_id', $bookId)->value('page_count');
        $this->assertEquals($pageCount, $setPageCount);
    }

    #[Depends('testInsertGetId')]
    public function testKeyword($bookId = 0)
    {
        if (!$bookId) {
            $bookId = static::$DB->table('books')->value('book_id');
        }
        $data = [
            "title"      => "三国演义",
            "publisher"  => '岳麓书社',
            "page_count" => 638,
            "isbn"       => '9787805200132',
        ];
        $res  = static::$DB->table('books')->where('book_id', $bookId)->update($data);
        $this->assertEquals($res, 1);
        $pageCount = static::$DB->table('books')->where('book_id', $bookId)->value('page_count');
        $this->assertEquals($pageCount, $data['page_count']);
    }


    public function testNotAutoIncPk()
    {
        $data = [
            'usci'        => '901234567890123456',
            'name'        => '岳麓书社',
            'address'     => '长沙市岳麓区',
            'city'        => '长沙',
            'province'    => '湖南',
            'country'     => '中国',
            'postal_code' => '410000',
            'phone'       => '0731-23456789',
            'email'       => 'info@yuelu.com',
        ];
        static::$DB->table('publishers')->where('usci', $data['usci'])->delete();
        $pk = static::$DB->table('publishers')->insertGetId($data);
        dump($pk);
        $this->assertEquals($pk, 1);
        $res = static::$DB->table('publishers')->where('usci', $data['usci'])->findOrEmpty();
        dump($res);
        $this->assertIsArray($res);
        static::$DB->table('publishers')->where('usci', $data['usci'])->delete();
    }

    public function testSelect()
    {
        // 测试查询数据
        $result = static::$DB->table('books')->select();
        $this->assertGreaterThan(0, count($result));
    }

    public function testFind()
    {
        // 测试查询单条数据
        $result = static::$DB->table('books')->find(1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('book_id', $result);
        $this->assertEquals($result['book_id'], 1);
    }

    public function testWhere()
    {
        // 测试条件查询
        $res = static::$DB->table('books')->where('book_id', 1)->find();
        dump($res);
        $this->assertIsArray($res);

        $res = static::$DB->table('books')->where('title', '=', '三国演义')->find();
        $this->assertIsArray($res);

        $where = [
            ['page_count', '>', 300],
            ['publication_date', '>', '1939-12-12'],
        ];
        $res   = static::$DB->table('books')->where($where)->select();
        $this->assertInstanceOf(\think\Collection::class, $res);
        $this->assertTrue(count($res) >= 0);
    }

    public function testGroup()
    {
        $res = static::$DB->table('books')
            ->field('language,sum(page_count) as pages')
            ->group('language')
            ->select();
        dump($res);
        $this->assertTrue(count($res) > 0);
        $this->assertTrue(in_array('pages', array_keys($res[0])));
    }

    public function testReplaceFail1()
    {
        $createTableSql = "create table if not exists test (name varchar(20),state smallint)";
        static::$DB->execute($createTableSql);
        $this->expectExceptionMessageMatches('/.* must has a primary key$/');
        static::$DB->table('test')->replace()->insert(['name' => 'hbh', 'state' => 1]);
        $dropTableSql = 'drop table if exists test';
        static::$DB->execute($dropTableSql);
    }
    public function testReplaceFail2()
    {
        $bookId = static::$DB->table('books')->where('isbn', '9787544274188')->value('book_id');
        $book   = [
            'title'            => '了不起的锅盖饭',
            'isbn'             => '9787544274188',
            'publisher'        => '上海文艺出版社',
            'publication_date' => '1999-01-01',
            'language'         => '中文',
            'page_count'       => 218,
            'summary'          => '一部以爵士时代为背景的小说。'
        ];
        $this->expectExceptionMessageMatches('/.* require data with primary key \[\w+\]/');
        static::$DB->table('books')->replace()->insert($book);
    }
    public function testReplaceOk()
    {
        $bookId = static::$DB->table('books')->where('isbn', '9787544274188')->value('book_id');
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
        $res    = static::$DB->table('books')->replace()->insert($book);
        $this->assertEquals($res, 1);
        $title = static::$DB->table('books')->where('book_id', $bookId)->value('title');
        $this->assertEquals($title, $book['title']);
        $maxBookId       = static::$DB->table('books')->max('book_id');
        $book['book_id'] = $maxBookId + 1;
        $count           = static::$DB->table('books')->count();
        $res             = static::$DB->table('books')->replace()->insert($book);
        $this->assertEquals($res, 1);
        $newCount = static::$DB->table('books')->count();
        $this->assertEquals($newCount, $count + 1);
    }
}
