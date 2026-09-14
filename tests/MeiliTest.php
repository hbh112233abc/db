<?php
declare(strict_types=1);
namespace bingher\tests;

require_once __DIR__ . '/../vendor/autoload.php';

use bingher\db\meilisearch\Client as Meili;
use bingher\db\meilisearch\Collection;
use bingher\db\meilisearch\Model;
use bingher\db\meilisearch\Paginator;
use bingher\ThinkTest\ThinkTest;

/**
 * 测试用图书模型（启用软删除）.
 */
class BookModel extends Model
{
    protected $index = 'phpunit_books';

    protected $deleteTime = 'delete_time';

    protected $type = [
        'price'  => 'float',
        'status' => 'int',
        'stock'  => 'int',
    ];
}

/**
 * Meilisearch 客户端测试.
 */
final class MeiliTest extends ThinkTest
{
    /**
     * 测试索引名.
     *
     * @var string
     */
    protected static $index = 'phpunit_books';

    /**
     * 是否已配置可用连接.
     *
     * @var bool
     */
    protected static $configured = false;

    public static function setUpBeforeClass(): void
    {
        // 连接信息取自本地 config/meilisearch.php（未纳入版本控制）或 MEILI_HOST 环境变量
        $config = function_exists('config') ? (array) config('meilisearch') : [];

        if (empty($config['connections']['main']['host'])) {
            $host = getenv('MEILI_HOST');
            if ($host) {
                $config = [
                    'default' => 'main',
                    'connections' => [
                        'main' => [
                            'host'    => $host,
                            'api_key' => (string) getenv('MEILI_KEY'),
                        ],
                    ],
                ];
            }
        }

        if (empty($config['connections']['main']['host'])) {
            static::$configured = false;

            return;
        }

        Meili::setConfig($config);

        // 重建测试索引，保证每次运行结果一致
        $connection = Meili::connect();
        if ($connection->indexExists(static::$index)) {
            $connection->deleteIndex(static::$index);
        }

        static::$configured = true;
    }

    protected function setUp(): void
    {
        if (!static::$configured) {
            $this->markTestSkipped('未配置 Meilisearch 连接,请创建 config/meilisearch.php 或设置 MEILI_HOST 环境变量');
        }
    }

    /**
     * 生成测试数据.
     *
     * @return array
     */
    protected function dataSet(): array
    {
        return [
            [
                'id'          => 1001,
                'title'       => 'PHP 从入门到实践',
                'author'      => '罗贯中',
                'category'    => '技术',
                'price'       => 88.5,
                'stock'       => 30,
                'status'      => 1,
                'create_time' => time(),
            ],
            [
                'id'          => 1002,
                'title'       => 'MySQL 高性能优化',
                'author'      => '施耐庵',
                'category'    => '技术',
                'price'       => 129.0,
                'stock'       => 8,
                'status'      => 1,
                'create_time' => time(),
            ],
            [
                'id'          => 1003,
                'title'       => 'ThinkPHP 实战教程',
                'author'      => '吴承恩',
                'category'    => '技术',
                'price'       => 69.9,
                'stock'       => 0,
                'status'      => 0,
                'create_time' => time(),
            ],
            [
                'id'          => 1004,
                'title'       => '红楼梦',
                'author'      => '曹雪芹',
                'category'    => '文学',
                'price'       => 45.0,
                'stock'       => 100,
                'status'      => 1,
                'create_time' => time(),
            ],
            [
                'id'          => 1005,
                'title'       => '深入浅出 Meilisearch',
                'author'      => '蒲松龄',
                'category'    => '技术',
                'price'       => 99.0,
                'stock'       => 12,
                'status'      => 1,
                'create_time' => time(),
            ],
        ];
    }

    public function testHealth()
    {
        $health = Meili::connect()->health();
        dump($health);
        $this->assertIsArray($health);
    }

    public function testVersion()
    {
        $version = Meili::connect()->version();
        dump($version);
        $this->assertIsArray($version);
    }

    public function testCreateIndex()
    {
        $connection = Meili::connect();
        $task       = $connection->createIndex(static::$index, ['primaryKey' => 'id']);
        dump($task);

        $settings = Meili::table(static::$index)->updateSettings([
            'searchableAttributes' => ['title', 'author', 'category'],
            'filterableAttributes' => ['id', 'category', 'price', 'stock', 'status', 'delete_time'],
            'sortableAttributes'   => ['id', 'price', 'stock'],
        ]);
        dump($settings);

        $this->assertTrue(in_array($task['status'] ?? '', ['enqueued', 'succeeded'], true));
    }

    public function testInsertAll()
    {
        $count = Meili::table(static::$index)->insertAll($this->dataSet(), true);
        dump($count);
        $this->assertEquals(5, $count);
    }

    public function testSelect()
    {
        $list = Meili::table(static::$index)->select();
        dump($list->toArray());
        $this->assertInstanceOf(Collection::class, $list);
        $this->assertEquals(5, $list->count());
    }

    public function testWhere()
    {
        $list = Meili::table(static::$index)->where('category', '技术')->where('status', 1)->select();
        dump($list->column('title'));
        $this->assertEquals(3, $list->count());

        // 字符串条件带操作符时按原生 filter 解析
        $row = Meili::table(static::$index)->where('price > 100')->select();
        $this->assertGreaterThan(0, $row->count());
    }

    public function testWhereInAndBetween()
    {
        $list = Meili::table(static::$index)->whereIn('id', [1001, 1003, 1005])->select();
        $this->assertEquals(3, $list->count());

        $list = Meili::table(static::$index)->whereBetween('price', [50, 90])->select();
        dump($list->column('title'));
        $this->assertGreaterThanOrEqual(2, $list->count());

        // 空集合查询直接返回空结果
        $list = Meili::table(static::$index)->whereIn('id', [])->select();
        $this->assertTrue($list->isEmpty());
        $this->assertEquals(0, Meili::table(static::$index)->whereIn('id', [])->count());
    }

    public function testWhereOrAndGroup()
    {
        $list = Meili::table(static::$index)
            ->where('category', '文学')
            ->whereOr('status', 0)
            ->select();
        dump($list->column('title'));
        $this->assertEquals(2, $list->count());

        // 闭包分组
        $list = Meili::table(static::$index)
            ->where(function ($query) {
                $query->where('category', '文学')->whereOr('status', 0);
            })
            ->where('price', '<', 100)
            ->select();
        $this->assertEquals(2, $list->count());
    }

    public function testSearch()
    {
        $list = Meili::table(static::$index)->search('Meilisearch')->select();
        dump($list->column('title'));
        $this->assertGreaterThanOrEqual(1, $list->count());

        // like 查询转换为全文检索
        $list = Meili::table(static::$index)->whereLike('title', '%PHP%')->select();
        $this->assertGreaterThanOrEqual(1, $list->count());
    }

    public function testOrderAndLimit()
    {
        $list = Meili::table(static::$index)->order('price', 'desc')->limit(3)->select();
        dump($list->column('price'));
        $this->assertEquals(3, $list->count());
        $this->assertEquals(129.0, $list->first()['price']);

        $list = Meili::table(static::$index)->limit(2, 2)->select();
        $this->assertEquals(2, $list->count());
    }

    public function testField()
    {
        $row = Meili::table(static::$index)->field('id,title')->where('id', 1001)->select()->first();
        dump($row);
        $keys = array_keys($row);
        sort($keys);
        $this->assertEquals(['id', 'title'], $keys);
    }

    public function testFind()
    {
        $row = Meili::table(static::$index)->find(1001);
        dump($row);
        $this->assertIsArray($row);
        $this->assertEquals('PHP 从入门到实践', $row['title']);

        $this->assertNull(Meili::table(static::$index)->find(999999));
    }

    public function testValueAndColumn()
    {
        $title = Meili::table(static::$index)->where('id', 1002)->value('title');
        $this->assertEquals('MySQL 高性能优化', $title);

        $titles = Meili::table(static::$index)->order('id', 'asc')->column('title');
        $this->assertEquals(5, count($titles));

        $map = Meili::table(static::$index)->order('id', 'asc')->column('title', 'id');
        $this->assertEquals('PHP 从入门到实践', $map[1001]);
    }

    public function testCount()
    {
        $count = Meili::table(static::$index)->where('category', '技术')->count();
        dump($count);
        $this->assertEquals(4, $count);
        $this->assertTrue(Meili::table(static::$index)->exists());
    }

    public function testPaginate()
    {
        $page = Meili::table(static::$index)->order('id', 'asc')->paginate(2, false, ['page' => 1]);
        dump($page->toArray());
        $this->assertInstanceOf(Paginator::class, $page);
        $this->assertEquals(2, $page->count());
        $this->assertEquals(5, $page->total());
        $this->assertEquals(3, $page->lastPage());

        $page = Meili::table(static::$index)->order('id', 'asc')->paginate(2, false, ['page' => 2]);
        $this->assertEquals(2, $page->currentPage());
        $this->assertEquals(1003, $page->items()[0]['id']);
    }

    public function testChunk()
    {
        $count = 0;
        Meili::table(static::$index)->order('id', 'asc')->chunk(2, function ($row) use (&$count) {
            $count++;

            return true;
        });
        $this->assertEquals(5, $count);
    }

    public function testInsertAndGetId()
    {
        $id = Meili::table(static::$index)->insertGetId([
            'title'    => 'Go 并发编程实战',
            'author'   => '兰陵笑笑生',
            'category' => '技术',
            'price'    => 78.0,
            'stock'    => 20,
            'status'   => 1,
        ]);
        dump($id);
        $this->assertNotEmpty($id);

        $row = Meili::table(static::$index)->find($id);
        $this->assertEquals('Go 并发编程实战', $row['title']);

        return $id;
    }

    public function testUpdate()
    {
        $row = Meili::table(static::$index)->search('并发编程')->select()->first();
        $id  = $row['id'];

        $num = Meili::table(static::$index)->where('id', $id)->update(['price' => 66.6, 'stock' => 5]);
        $this->assertEquals(1, $num);

        $row = Meili::table(static::$index)->find($id);
        $this->assertEquals(66.6, $row['price']);
    }

    public function testUpdateByClosure()
    {
        $num = Meili::table(static::$index)
            ->where('category', '文学')
            ->update(function ($document) {
                $document['stock'] = 88;

                return $document;
            });
        $this->assertEquals(1, $num);

        $row = Meili::table(static::$index)->find(1004);
        $this->assertEquals(88, $row['stock']);
    }

    public function testDelete()
    {
        $row = Meili::table(static::$index)->search('并发编程')->select()->first();
        $id  = $row['id'];

        $num = Meili::table(static::$index)->where('id', $id)->delete();
        $this->assertEquals(1, $num);
        $this->assertNull(Meili::table(static::$index)->find($id));
    }

    public function testFilterExpression()
    {
        $filter = Meili::table(static::$index)
            ->where('category', '技术')
            ->where('price', '>=', 50)
            ->whereOr('stock', '<', 10)
            ->getFilter();
        dump($filter);
        $this->assertEquals('category = "技术" AND price >= 50 OR stock < 10', $filter);

        $params = Meili::table(static::$index)->where('status', 1)->limit(5)->getSearchParams();
        dump($params);
        $this->assertEquals('status = 1', $params['filter']);
        $this->assertEquals(5, $params['limit']);
    }

    public function testLog()
    {
        $log = [];
        Meili::listen(function ($info) use (&$log) {
            $log[] = $info;
        });

        Meili::table(static::$index)->search('PHP')->select();

        dump($log);
        $this->assertNotEmpty($log);
        $this->assertEquals('search', $log[0]['type']);
    }

    public function testModelFindAndSave()
    {
        $book = BookModel::find(1001);
        dump($book->toArray());
        $this->assertInstanceOf(BookModel::class, $book);
        $this->assertEquals('PHP 从入门到实践', $book->title);
        $this->assertTrue($book->isExists());

        // 修改属性后保存（仅更新变更字段）
        $book->price = 77.7;
        $this->assertTrue($book->save());

        $book = BookModel::find(1001);
        $this->assertEquals(77.7, $book->price);
    }

    public function testModelCreate()
    {
        $book = BookModel::create([
            'id'       => 1006,
            'title'    => 'Elasticsearch 权威指南',
            'author'   => '冯梦龙',
            'category' => '技术',
            'price'    => 118.0,
            'stock'    => 15,
            'status'   => 1,
        ]);
        dump($book->toArray());

        $this->assertInstanceOf(BookModel::class, $book);
        $this->assertTrue($book->isExists());
        $this->assertNotNull(BookModel::find(1006));
    }

    public function testModelSelect()
    {
        $list = BookModel::where('category', '技术')->order('id', 'desc')->limit(3)->select();
        dump($list->toArray());
        $this->assertInstanceOf(Collection::class, $list);
        $this->assertInstanceOf(BookModel::class, $list->first());
        $this->assertEquals(3, $list->count());
    }

    public function testModelSoftDelete()
    {
        $book = BookModel::find(1006);
        $this->assertNotNull($book);
        $this->assertFalse($book->trashed());

        // 软删除
        $this->assertTrue($book->delete());
        $this->assertNull(BookModel::find(1006));

        // 含软删除数据
        $trashed = BookModel::withTrashed()->where('id', 1006)->find();
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());

        // 仅软删除数据
        $this->assertEquals(1, BookModel::onlyTrashed()->where('id', 1006)->count());

        // 恢复
        $this->assertTrue($trashed->restore());
        $this->assertNotNull(BookModel::find(1006));

        // 物理删除
        $this->assertEquals(1, BookModel::destroy(1006, true));
        $this->assertNull(BookModel::withTrashed()->where('id', 1006)->find());
    }

    public function testModelTimestampAndType()
    {
        $book = BookModel::find(1001);
        dump($book->toArray());
        $this->assertIsFloat($book->price);
        $this->assertIsInt($book->status);
    }

    public function testModelEvent()
    {
        $hit = [];
        BookModel::event('before_insert', function ($model) use (&$hit) {
            $hit[] = 'before_insert:' . $model->title;
        });

        BookModel::create([
            'id'       => 1007,
            'title'    => '事件测试书籍',
            'author'   => ' tester ',
            'category' => '测试',
            'price'    => 1.0,
            'stock'    => 1,
            'status'   => 1,
        ]);

        dump($hit);
        $this->assertEquals(['before_insert:事件测试书籍'], $hit);

        BookModel::destroy(1007, true);
    }
}
