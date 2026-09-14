# AGENT.md

面向 AI 智能体与协作者的项目上手指南。开始改动前请先通读本文，再动手。

---

## 1. 项目是什么

`bingher/db` 是一个 **ThinkPHP ORM 驱动扩展库**，为国产数据库提供 `think-orm` 驱动支持：

| 数据库 | 连接标识 | 状态 |
| --- | --- | --- |
| 达梦 DM8 | `dm` | 已验证 |
| 南大通用 GBase 8s | `gbase` | 已验证 |
| OpenGauss(高斯) | `open_gauss` / `gauss` | 已验证 |
| 人大金仓 KingBase | `kdb` | 初始版本已完成（**MySQL 兼容模式**），暂无用例覆盖 |
| Meilisearch 搜索引擎 | `meilisearch` | 已验证（非 SQL 驱动，独立的 ThinkORM 风格客户端） |

本库不是独立应用，而是一个 **Composer 依赖包**，被 ThinkPHP 项目通过 `config/database.php` 注册后使用。
其中 Meilisearch 部分为独立的命名空间（`bingher\db\meilisearch`），不走 `config/database.php`，
通过 `config/meilisearch.php` 或 `Client::setConfig()` 配置。

## 2. 技术栈与版本要求

- PHP：`composer.json` 声明为 `>=7.1|>= 7.2.5|>=8.0.0`（写法宽松，等价于 `>=7.1`），但**源码实际要求 PHP 8.0+**——大量使用 `str_contains()` / `str_starts_with()`、联合类型 `string | int | Raw`、PHPUnit Attributes 等 PHP 8 特性。当前开发环境为 PHP 8.2，**以 PHP 8.0+ 为最低支持线**。
- topthink/framework `^5|^6|^8.0`（当前锁定 `v8.1.1`）
- topthink/think-orm `^2.0|^3.0|^4.0`（当前锁定 `v3.0.34`）
- 开发依赖：phpunit/phpunit `^9.6`、topthink/think-cache `^2.0`、bingher/think-test `^1.0`

## 3. 目录结构

```
src/                     驱动源码（PSR-4: bingher\db\）
├── builder/             各数据库 SQL 生成器（继承 think\db\Builder）
│   ├── DM.php
│   ├── GBase.php
│   ├── OpenGauss.php
│   └── KingBase.php
├── connector/           各数据库连接器（继承 think\db\PDOConnection）
│   ├── DM.php
│   ├── GBase.php
│   ├── OpenGauss.php
│   └── KingBase.php
└── query/               各数据库查询类（继承 think\db\Query）
    ├── DM.php           DM 有自定义逻辑（schema 前缀）
    ├── GBase.php        空占位实现
    ├── OpenGauss.php    空占位实现
    └── KingBase.php     空占位实现

src/meilisearch/         Meilisearch 客户端（命名空间 bingher\db\meilisearch）
├── Client.php           静态门面，等价 think\Db（table/name/connect/getConfig）
├── Manager.php          连接管理器，等价 think\DbManager（配置解析与连接池）
├── Connection.php       连接对象，封装官方 SDK 客户端、索引前缀、任务等待、日志
├── Query.php            链式查询器（where/order/limit/search/select/insert/delete）
├── Builder.php          把链式条件编译为 Meilisearch filter 表达式与请求参数
├── Model.php            模型基类（属性读写、类型转换、时间戳、软删除、事件）
├── Collection.php       结果集（继承 think\Collection）
├── Paginator.php        分页器（继承 think\paginator\driver\Bootstrap）
└── exception/           Exception、ModelNotFoundException

config/                  ★ 本地私有配置，未纳入版本控制
├── database.php         数据库连接配置（含测试库地址与密码）
├── meilisearch.php      Meilisearch 连接配置（含服务地址与 API Key）
├── cache.php
└── log.php

docs/                    各数据库部署与适配文档
├── dm/deploy.md
└── gbase/deploy.md

tests/                   测试用例（PSR-4: bingher\tests\）
├── DmTest.php
├── GBaseTest.php
├── GBaseODBCTest.php    原生 odbc 扩展直连验证
├── GBasePdoOdbcTest.php 原生 PDO ODBC 直连验证
├── OpenGaussTest.php
├── TypeTest.php
├── MeiliTest.php       Meilisearch 客户端用例（含 BookModel 模型与软删除）
├── odbc.php / pdo.php / t.php   临时调试脚本
├── readme.md            测试数据结构说明（图书系统）
└── sql/                 建表与初始化数据脚本
    ├── mysql.sql
    ├── gbase.sql
    └── open_gauss.sql

test.bat                 Windows 测试入口，封装 tpt + PHPUnit
```

## 4. 核心架构：三个角色

ThinkPHP ORM 的数据库驱动由三部分组成，本库对每个数据库都实现了这三件套，缺一不可：

1. **Connector（连接器）** — 负责 DSN 拼接、`getFields()` 表结构探测、`getTables()`、`getLastInsID()`、`insert()` / `insertAll()` 等实际执行逻辑。继承 `think\db\PDOConnection`。
2. **Builder（构建器）** — 负责把链式查询转换为 SQL 字符串，重点是 `parseKey()`（标识符引号/大小写）、`parseLimit()`、`insert()`、`insertAll()`、`replaceSql()`、`select()`、`update()`、`delete()` 等。继承 `think\db\Builder`。
3. **Query（查询类）** — 继承 `think\db\Query`，通常为空，仅在需要改写表名解析等行为时才重写（如 DM）。

在 ThinkPHP 中注册方式（详见 README.md）：

```php
'connections' => [
    'dm' => [
        'builder' => \bingher\db\builder\DM::class,
        'query'   => \bingher\db\query\DM::class,
        'type'    => \bingher\db\connector\DM::class,
        // ...
    ],
],
```

## 5. 各数据库适配要点

### DM(达梦)

- DSN：`dm:host=...;dbname=...;charset=...`
- `builder\DM::parseKey()` 使用双引号包裹标识符；`query\DM` 会把 `database` 转为大写作为 schema 前缀，`getTable()` 返回 `SCHEMA.prefix_table`。
- 自增主键写入前需要 `SET IDENTITY_INSERT 表 ON/OFF`（见 `connector\DM::setIdentityInsert()`），`insert()` 与 `insertAll()` 均已重写以处理该逻辑。
- `getLastInsID()` 使用 `SELECT SCOPE_IDENTITY()`。
- 字段探测 SQL 依赖 `all_tab_columns` / `SYSOBJECTS` 等系统表，通过 `DECODE(...,1,0)` 判断主键与自增。
- 该连接器会临时关闭 `trigger_sql` 再查询系统表，修改时不要破坏这一逻辑。

### GBase(南大通用 8s)

- 支持两种驱动：`pdo_odbc`（Windows 可用）与 `pdo_gbasedbt`（推荐 Linux 使用），通过配置项 `driver` 切换，非法值会抛 `InvalidArgumentException`。
- `getFields()` 依赖 `systables/syscolumns/sysconstraints` 等系统表，并做了结果缓存（`$this->fields`），注意缓存失效场景。
- `insertAll()` 有重要限制：若表存在 `text` 类型字段，会强制 `limit = 1` 逐条插入（text 字段批量插入会静默失败）；默认 5000 条以上分批（1000/批）并自动开启事务。
- `replaceSql()` 通过 `MERGE INTO ... USING (SELECT ... FROM dual)` 实现，要求表有主键且数据必须包含主键字段，否则抛异常。
- `getFieldBindType()` 额外支持 `blob`(`PARAM_LOB`) 与 `clob`。

### OpenGauss(高斯)

- DSN 使用 `pgsql:` 并拼接 `options=--search_path=<schema>`，`schema` 取自配置，默认 `public`。
- `insert()` 使用 `RETURNING <pk>`，并在连接器重写 `pdoInsertExecute()` 从结果集首列取回自增 ID。
- `replaceSql()` 通过 `INSERT ... ON CONFLICT (<pk>) DO UPDATE SET ...` 实现。
- 字段类型通过 `pgType()` 做映射（`int4→integer`、`bpchar→char` 等）。
- `getFields()` 中 `notnull` 使用 `attnotnull`，不要按 MySQL 语义理解。

### KingBase(人大金仓)

- 面向**兼容 MySQL 模式**的金仓实例，因此大量行为刻意沿用 MySQL 语义，属于有意设计而非遗留代码：
  - `connector\KingBase::getFields()` 使用 `SHOW FULL COLUMNS FROM`（标识符用反引号），`getTables()` 使用 `SHOW TABLES [FROM db]`。
  - `builder\KingBase::parseDuplicate()` 生成 `ON DUPLICATE KEY UPDATE`；`insert()` / `insertAll()` 在 `replace` 场景生成 `REPLACE`。
  - 保留 `FIND_IN_SET`、`PARTITION`、`rand()`、JSON 的 `->` / `->>` 解析。
- DSN：`kdb:host=...;port=...;dbname=...`（也支持 `unix_socket`）；`charset` 拼接目前被注释掉，中文依赖数据库端配置。
- 标识符仍使用**双引号**：`builder\KingBase::parseKey()` 输出 `"field"` / `"table"."field"`，与 `getFields()` 中的反引号不是同一套引号，修改引号逻辑时两者要一起评估。
- 提供 XA 事务方法：`startTransXa()` / `prepareXa()` / `commitXa()` / `rollbackXa()`。
- `cursorUnbuffered()` 通过 `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY` 切换无缓冲游标，该常量由 `pdo_mysql` 扩展定义；在未加载该扩展的环境中调用前需先确认常量可用。
- 除 DM/GBase/OpenGauss 外，KingBase 的所有改动都**必须经过真实库验证**后才能提交，当前无自动化用例兜底。

### Meilisearch(搜索引擎)

`src/meilisearch/` 不是 think-orm 驱动，而是一套**独立的 ThinkORM 风格客户端**，底层依赖官方 SDK
`meilisearch/meilisearch-php`（用户明确要求引入），HTTP 客户端由 `guzzlehttp/guzzle` 提供
（php-http/discovery 会自动发现 `GuzzleHttp\Client` 与 `GuzzleHttp\Psr7\HttpFactory`）。

- **四个角色**：`Manager`（连接池/配置）→ `Connection`（SDK 客户端、前缀、任务等待）→ `Query`（链式）→ `Builder`（条件编译）。
  `Client` 是静态门面，等价于 ThinkORM 的 `Db`，`Model` 等价于 `think\Model`。
- **条件编译**：`where` 生成 Meilisearch 的 filter 表达式（`Builder::buildFilter()`），
  逻辑分组规则与 ThinkORM 一致（同组内按同一 logic 连接，组与组之间用后一组的 logic 连接，闭包生成括号分组）。
- **全文检索**：`search()` / `keyword()` 设置 `q` 参数；`whereLike()` 会被转换为 `q` 关键词（去掉 `%`、`_`），
  `NOT LIKE` 服务端不支持，直接抛异常。
- **空值语义**：Meilisearch 把**缺失字段视为非空**（实测 `字段 IS NULL` 只匹配显式 null）。
  因此 `whereNull()` 编译为 `( 字段 IS NULL OR 字段 NOT EXISTS )`，`whereNotNull()` 编译为
  `( 字段 IS NOT NULL AND 字段 EXISTS )`，软删除默认条件同样使用这两组表达式。改动前务必先确认该语义。
- **写操作**：`insert` / `insertAll` 走 `addDocuments`（全量替换），`update` 走 `updateDocuments`（部分更新）。
  按条件更新/删除时，先用搜索分批拉取主键（`fetchDocuments()`，受 `max_update_rows` 限制），再批量提交。
  `delete()` 无条件时调用 `deleteAllDocuments()`。任务失败（除 `index_already_exists`）会抛异常。
- **软删除**：模型声明 `protected $deleteTime = 'delete_time';` 即启用，`Query::setModel()` 会自动注入
  `useSoftDelete()`；`withTrashed()` / `onlyTrashed()` / `force()` / `restore()` 与 ThinkORM 语义一致。
- **不支持**：`join`、事务、`sum/avg/max/min` 聚合、`NOT LIKE`、服务端字段排除（`withoutField()` 为客户端过滤）。

## 6. 本地开发环境

```shell
# 1. 安装依赖
composer install

# 2. 准备本地配置（config/ 被 .gitignore 忽略，仓库内不存在）
#    参考 README.md 与 src/connector/*.php 中的 config 默认值，
#    创建 config/database.php、config/cache.php、config/log.php
```

**重要**：`config/database.php` 由本地维护，内含测试数据库的真实地址与密码，**禁止提交到仓库、禁止在输出中泄露**。`vendor/`、`runtime/`、`*.lock` 同样被忽略。

被忽略的关键目录：`config/`、`runtime/`、`vendor/`、`*.lock`、`.env`、`.vscode/`。

## 7. 测试与调试

本项目**没有 CI，全部依赖本地手动调试**。因此每次改动后，请自行在真实数据库上跑通相关用例。

- 测试基于 PHPUnit 9.6，通过 `bingher/think-test` 提供的 `tpt` 引导脚本运行（它会加载 ThinkPHP 应用、清理 `runtime/log/`、再调用 PHPUnit）。
- 所有用例继承 `bingher\ThinkTest\ThinkTest`（基于 `PHPUnit\Framework\TestCase`，额外提供 `call()` 反射调用与 `prop()` 反射属性）。
- 测试依赖真实数据库连接，连接名与 `config/database.php` 中的 key 对应：`dm`、`gbase`、`open_gauss`。
- `tests/MeiliTest.php` 依赖真实 Meilisearch 服务，连接信息写在用例的 `setUpBeforeClass()`
  （默认读取 `config/meilisearch.php`，用例内已显式注入，可独立运行）；用例会自动重建 `phpunit_books` 索引。
- 测试数据集为「图书管理系统」，建表脚本见 `tests/sql/`，导入对应数据库后即可跑用例。

运行方式（Windows，项目根目录执行）：

```shell
# 通过封装脚本（已带 --colors=auto -v）
./test.bat tests/GBaseTest.php
./test.bat tests/OpenGaussTest.php
./test.bat --filter=testReplaceOk tests/GBaseTest.php

# 直接使用 tpt
./vendor/bin/tpt.bat tests/OpenGaussTest.php
```

Linux/macOS 使用 `./vendor/bin/tpt tests/OpenGaussTest.php`。

**注意**：
- 项目根目录没有 `phpunit.xml`，因此运行时必须显式指定测试文件或目录，否则 PHPUnit 找不到用例。
- 用例会真实读写数据库（insert/update/delete），请勿在业务库上运行；用例之间对同一批数据有依赖（如 `#[Depends]`、`book_id` 自增假设），不要随意调整执行顺序。
- 部分用例需要对应 PHP 扩展，如 `GBaseTest` 标注了 `#[RequiresPhpExtension('pdo_odbc')]`。
- `tests/` 下 `odbc.php`、`pdo.php`、`t.php` 是临时调试脚本，可直接用于验证 DSN/驱动连通性；`__testDropTable()` 这类以 `__` 开头的方法是刻意禁用的，不要改成 `test` 前缀。
- 手动调试完成后请清理临时脚本与 `runtime/log/`，不要把调试产物留在仓库。

## 8. 开发规范

本节为本仓库的约定，新增代码一律遵循。

### 代码风格

- 遵循 PSR-12，**4 空格缩进**，文件使用 UTF-8 无 BOM。
- 每个文件以 `<?php` 开头并声明 `namespace`，不写 `?>` 结束标签。
- 类、方法使用 DocBlock 注释；注释、日志、异常文案一律使用**中文**；生成代码后需检查是否出现中文乱码。
- 一个类一个文件，命名空间与目录严格对应：`bingher\db\<layer>\<Driver>`。
- 类名与数据库命名保持一致（`DM`、`GBase`、`OpenGauss`、`KingBase`）。

### 兼容性

- 不引入新的生产依赖，不改动 `composer.json` 的 `require` 范围，除非用户明确要求。
- 保持 PHP 8.0+ 可运行；避免使用 8.1+ 独有语法（如 `enum`、只读属性、纯交集类型）。
- 三个角色（builder / connector / query）应成套修改：新增或改动驱动时，必须确认其余两个角色是否需要同步调整。

### 测试

- 修改任一驱动后，必须在对应数据库上手动跑通用例并在提交说明中写明验证结果。
- 用户未明确要求时，不主动新增测试脚本；如需补充用例，沿用 `tests/sql/` 的数据集与图书系统数据结构。
- 保持异常消息中的英文关键字不变（如 `must has a primary key`、`require data with primary key [xxx]`），既有用例通过正则匹配这些文案，改写会直接破坏测试。

### 提交与版本

- 提交信息使用 Conventional Commits 格式，描述用中文，例如：
  - `feat(gbase): 支持 clob 字段绑定`
  - `fix(dm): 修正自增主键 IDENTITY_INSERT 未关闭的问题`
  - `docs: 补充 KingBase 接入说明`
- 分支：`main` 为主干（保持可运行），改动通过 `feat/xxx`、`fix/xxx` 短生命周期分支进行。
- 版本：遵循语义化版本，通过 `vX.Y.Z` 标签发布。
- **禁止提交** `config/`、`vendor/`、`runtime/`、`*.lock`、`.env` 以及任何包含数据库地址、账号密码的内容。
- 不提交与当前需求无关的格式化、重命名或重构改动。

### 改动原则

- 修改函数前先理解并保留原有逻辑，只做必要的最小改动。
- 专注当前需求，不"顺手"重构未提及的功能，也不擅自修正其他驱动的可疑实现（可先与维护者确认）。

## 9. 常见任务指引

### 新增一种数据库驱动

1. 在 `src/builder/`、`src/connector/`、`src/query/` 各新增一个类，命名与数据库一致。
2. Connector 至少实现：`parseDsn()`、`getFields()`、`getTables()`、`getLastInsID()`（如需）、`supportSavepoint()`。
3. Builder 至少确认：`parseKey()`、`parseLimit()`、`select()` / `insert()` / `update()` / `delete()` 模板与目标数据库语法一致。
4. 在 `config/database.php` 增加连接配置，在 `README.md` 增加接入示例。
5. 在 `tests/sql/` 补建表脚本，并在对应数据库实例上手动验证增删改查、批量插入、replace、事务。

### 排查 SQL 生成问题

优先写临时脚本直接调用链式查询并打印生成的 SQL（参考 `tests/t.php` 的写法），再回到 Builder 中定位；调试脚本排查完即清理。

## 10. 参考文档

- `README.md` — 各数据库在 ThinkPHP 中的注册配置示例
- `tests/readme.md` — 测试数据结构、GBase 已知坑（text/blob/clob、中文预处理语句限制等）
- `docs/dm/deploy.md` — 达梦安装、扩展配置、迁移适配问题与常用 SQL
- `docs/gbase/deploy.md` — GBase 8s 安装、客户端、PHP 扩展编译、迁移与 dbaccess 增强

## 11. 已知限制与后续事项

以下为当前实现中已知的边界情况，改动相关代码前请先确认：

1. **KingBase 无用例覆盖**：所有验证依赖真实库手动执行，改动风险高于其他三个驱动。
2. **KingBase 未设置连接字符集**：`parseDsn()` 中 `charset` 拼接被注释，中文乱码问题需要从数据库端或 DSN 侧另行处理。
3. **KingBase 无缓冲游标依赖 `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY`**：该常量来自 `pdo_mysql`，使用前请确认扩展已加载。
4. **GBase `text` 字段无法批量插入**：`insertAll()` 会退化为逐条插入，属于数据库侧限制。
5. **GBase replace 必须有主键**：缺少主键或数据缺失主键字段时会抛异常。
6. **DM 自增列需 `IDENTITY_INSERT`**：相关开关逻辑分散在 `insert()` 与 `insertAll()`，改动时注意成对开关，避免残留 ON 状态。
7. **无 CI 流水线**：回归完全依赖人工，合并前的验证记录应写入提交说明。
8. **Meilisearch 依赖新增的生产依赖**：`meilisearch/meilisearch-php` 与 `guzzlehttp/guzzle`
   （经用户确认后引入），Guzzle 需锁定 `^7`（8.x 要求 psr/http-message 2.0，与当前锁版本冲突）。
9. **Meilisearch 索引属性需服务端配置**：过滤/排序/检索字段必须先写入
   `filterableAttributes` / `sortableAttributes` / `searchableAttributes`，否则服务端报错，
   客户端会在异常中附带中文排查提示。
