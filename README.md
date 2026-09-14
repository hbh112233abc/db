# think-orm driver for DM(达梦),GBase8s(南大通用),OpenGauss(高斯),KingBase(金仓)

## 安装

```shell
composer require bingher/db
```

## DM8(达梦)

`config.database.php`配置参考如下:

```php
return [
    // 默认使用的数据库连接配置
    'default'         => env('DB_DRIVER', 'dm'),

    ...

    // 数据库连接配置信息
    'connections'     => [
        'dm'          => [
            // ★Builder类
            'builder'         => \bingher\db\builder\DM::class,
            // ★Query类
            'query'         => \bingher\db\query\DM::class,
            // ★数据库类型
            'type'            => \bingher\db\connector\DM::class,
            ...
        ],
    ]
];
```

## GBase8s(南大通用)

`config.database.php`配置参考如下:

```php
return [
    // 默认使用的数据库连接配置
    'default'         => env('DB_DRIVER', 'gbase'),

    ...

    // 数据库连接配置信息
    'connections'     => [
        'gbase'          => [
            // ★Builder类
            'builder'         => bingher\db\builder\GBase::class,
            // ★Query类
            'query'         => bingher\db\query\GBase::class,
            // ★数据库类型
            'type'            => bingher\db\connector\GBase::class,
            // ★驱动类型: pdo_gbasedbt,pdo_odbc
            'driver'          => env('C_DRIVER', 'pdo_odbc'),
            ...
        ],
    ]
];
```

## OpenGauss(高斯)

`config.database.php`配置参考如下:

```php
return [
    // 默认使用的数据库连接配置
    'default'         => env('DB_DRIVER', 'gauss'),

    ...

    // 数据库连接配置信息
    'connections'     => [
        'gauss'          => [
            // ★Builder类
            'builder'         => bingher\db\builder\OpenGauss::class,
            // ★Query类
            'query'         => bingher\db\query\OpenGauss::class,
            // ★数据库类型
            'type'            => bingher\db\connector\OpenGauss::class,
            ...
        ],
    ]
];
```

## KingBase(金仓)

`config.database.php`配置参考如下:

```php
return [
    // 默认使用的数据库连接配置
    'default'         => env('DB_DRIVER', 'kdb'),

    ...

    // 数据库连接配置信息
    'connections'     => [
        'kdb'          => [
            // ★Builder类
            'builder'         => bingher\db\builder\KingBase::class,
            // ★Query类
            'query'         => bingher\db\query\KingBase::class,
            // ★数据库类型
            'type'            => bingher\db\connector\KingBase::class,
            ...
        ],
    ]
];
```


## Meilisearch（搜索引擎）

`src/meilisearch/` 提供了一套 **ThinkORM 风格的 Meilisearch 客户端**，与 ThinkPHP 的 `Db` 门面写法保持一致，
底层基于官方 SDK `meilisearch/meilisearch-php`，写操作默认等待服务端任务完成（同步语义）。

### 安装

```shell
composer require bingher/db
```

Meilisearch 客户端为**可选功能**，按需安装其 SDK 依赖即可（不安装不影响达梦/GBase 等既有驱动）：

```shell
composer require meilisearch/meilisearch-php guzzlehttp/guzzle
```

若未安装就调用 `Client` / `Model` 连接 Meilisearch，会抛出提示安装的异常。

### 配置

新增 `config/meilisearch.php`（该目录未纳入版本控制，密钥不会入库）：

```php
return [
    // 默认连接
    'default'          => 'main',
    // 索引名前缀
    'prefix'           => '',
    // 写操作是否等待 Meilisearch 任务完成
    'wait'             => true,
    // 等待任务超时时间(毫秒)
    'task_timeout'     => 10000,
    // 轮询任务间隔(毫秒)
    'task_interval'    => 50,
    // 未指定 limit 时的默认返回条数
    'limit'            => 1000,
    // 按条件批量更新/删除时最多处理的文档数(0 表示不限)
    'max_update_rows'  => 1000,

    'connections'      => [
        'main' => [
            // 服务地址
            'host'    => 'http://127.0.0.1:7700',
            // API Key
            'api_key' => '',
        ],
    ],
];
```

也可以在运行时注入配置（不依赖框架配置时）：

```php
use bingher\db\meilisearch\Client as Meili;

Meili::setConfig([
    'connections' => [
        'main' => [
            'host'    => 'http://127.0.0.1:7700',
            'api_key' => 'your-key',
        ],
    ],
]);
```

### 查询器写法

```php
use bingher\db\meilisearch\Client as Meili;

// 查询
$list = Meili::table('books')
    ->where('category', '技术')
    ->where('price', '>=', 50)
    ->whereIn('id', [1, 2, 3])
    ->order('price', 'desc')
    ->limit(10)
    ->select();

// 全文检索（等价于 Meilisearch 的 q 参数）
$list = Meili::table('books')->search('php', ['title', 'author'])->select();

// like 查询会自动转换为全文检索关键词
$list = Meili::table('books')->whereLike('title', '%php%')->select();

// 单条、字段、统计、分页
$book    = Meili::table('books')->find(1);
$title   = Meili::table('books')->where('id', 1)->value('title');
$count   = Meili::table('books')->where('status', 1)->count();
$page    = Meili::table('books')->paginate(15);   // 返回 think\Paginator

// 写入
Meili::table('books')->insert([...]);
Meili::table('books')->insertAll($dataList);
Meili::table('books')->where('category', '技术')->update(['status' => 1]);
Meili::table('books')->where('id', 1)->delete();

// 调试：查看编译后的 filter 与请求参数
echo Meili::table('books')->where('status', 1)->getFilter();
dump(Meili::table('books')->where('status', 1)->getSearchParams());

// 原生参数透传
Meili::table('books')->option('showRankingScore', true)->select();
```

### 模型写法

```php
use bingher\db\meilisearch\Model;

class Book extends Model
{
    // 索引名（等价 ThinkORM 的表名，未设置时由类名转换）
    protected $index = 'books';

    // 软删除字段（置空表示不启用）
    protected $deleteTime = 'delete_time';

    protected $type = ['price' => 'float', 'status' => 'int'];
}

Book::find(1);
Book::where('category', '技术')->order('price', 'desc')->select();
Book::create(['title' => 'PHP 实战', 'price' => 88.5]);

$book        = Book::find(1);
$book->price = 99;
$book->save();

$book->delete();                 // 软删除
Book::destroy([1, 2], true);     // 物理删除
Book::withTrashed()->where('id', 1)->find();
Book::onlyTrashed()->select();
$book->restore();                // 恢复软删除
```

### 注意事项

1. **必须先配置索引属性**：参与 `where` 过滤、排序、检索的字段需要加入 `filterableAttributes`、
   `sortableAttributes`、`searchableAttributes`，否则服务端会报错：

```php
Meili::table('books')->updateSettings([
    'searchableAttributes' => ['title', 'author'],
    'filterableAttributes' => ['id', 'category', 'price', 'status', 'delete_time'],
    'sortableAttributes'   => ['id', 'price'],
]);
```

2. **Meilisearch 的空值语义与 SQL 不同**：缺失字段被服务端视为"非空"。因此 `whereNull()`
   与软删除的默认条件会编译为 `( 字段 IS NULL OR 字段 NOT EXISTS )`，与 ORM 直觉保持一致。
3. **Meilisearch 无聚合与关联**：未提供 `sum()` / `avg()` / `join()` 等方法，需要时请自行取回数据处理。
4. **写操作为异步任务**：本客户端默认等待任务完成（`wait` 配置），失败会抛出异常并附带中文排查提示。
5. **`withoutField()` 为客户端过滤**：Meilisearch 只支持指定返回字段，无法在服务端排除字段。

## 参考资料

### Meilisearch

- [官方文档](https://www.meilisearch.com/docs)
- [meilisearch-php](https://github.com/meilisearch/meilisearch-php)

### 达梦数据库

- [达梦数据库-快速上手](https://eco.dameng.com/document/dm/zh-cn/start)
- [达梦数据库-应用开发指南-PHP 数据库接口](https://eco.dameng.com/document/dm/zh-cn/app-dev/php-php.html)
- [thinkphp6 phpstudy php8 达梦数据库](https://blog.csdn.net/qq_22471701/article/details/127785640)

### 南大通用数据库

- [南大通用 GBASE 8s V8.8 最全安装指南（一网打尽）](https://www.gbase.cn/community/post/4718)
- [GBase 8s数据库连接 - PHP PDO_GBASEDBT](https://www.gbase.cn/community/post/156)
- [GBase 8s数据库连接 - PHP ODBC](https://www.gbase.cn/community/post/155)
- [Nginx下PHP连接到GBase 8s数据库 - PDO_GBASEDBT方式](https://blog.csdn.net/liaosnet/article/details/138073622)

### OpenGauss

- [官网](https://opengauss.org/zh/)
- [MySQL迁移openGauss](https://docs.opengauss.org/zh/docs/5.0.0/docs/DataMigrationGuide/%E5%85%A8%E9%87%8F%E8%BF%81%E7%A7%BB.html)
- [【数据库迁移系列】使用pg_chameleon将数据从MySQL迁移至openGauss数据库](https://blog.csdn.net/GaussDB/article/details/127011147)

- 创建DBA用户

```sql
create user 用户名 with sysadmin login password '密码';
```

- 创建兼容mysql的数据库

`DBCOMPATIBILITY` 取值范围：A、B、C、PG。分别表示兼容 O、MY、TD和POSTGRES

```sql
create database 数据库名 owner gbase8s DBCOMPATIBILITY= 'B' ENCODING 'UTF8' LC_COLLATE'en_US.UTF-8' LC_CTYPE'en_US.UTF-8'
```
