# think-orm driver for DM(达梦),KingDatabase(金仓)

## 安装

```shell
composer require bingher/db
```

## 参考资料

- [达梦数据库-快速上手](https://eco.dameng.com/document/dm/zh-cn/start)
- [达梦数据库-应用开发指南-PHP 数据库接口](https://eco.dameng.com/document/dm/zh-cn/app-dev/php-php.html)
- [thinkphp6 phpstudy php8 达梦数据库](https://blog.csdn.net/qq_22471701/article/details/127785640)

## 达梦数据库适配问题及解决方案

1. MySQL 迁移后,自增主键,执行 insert 报错:仅当指定列列表，且 SET IDENTITY_INSERT 为 ON 时，才能对自增列赋值

```
1、如果自增列所在的列是由应用程序自动维护该数值的，那么可以将该列设置为非自增列；
2、如果自增列需要保留，只是对特殊的记录需要维护，那每次在更新前执行：
    SET IDENTITY_INSERT 模式名.表名 ON；
    然后执行具体SQL；
    执行后再关闭：
    SET IDENTITY_INSERT 模式名.表名 OFF；
```

2. 数据库插入中文乱码
   [php7 使用 PDO 连接达梦数据库 V8 出现中文乱码](https://blog.csdn.net/churujianghu/article/details/128351636)

3. mysql 中 varchar 默认单位是字符，达梦默认是字节，需要在达梦数据库初始化的时候设置初始化参数 LENGTH_IN_CHAR=1 （图形界面勾选 VARCHAR 类型长度是否以字符为单位）

4. mysql 迁移 DATETIME 报错
   [mysql 迁移到 dm 报错【错误消息: 不支持该数据类型】](https://eco.dameng.com/community/question/a5ea59f9fedec3df4e9d424e7c6378ef)

5. 迁移报错
   [执行 MySQL 迁移任务时报错](https://eco.dameng.com/community/question/1ad87c68e4de83ea1c8fd1a87d72246d)

6. ## mysql5.7 迁移 dm8 配置
   - mysql 数据库驱动选择 mysql-connector-java-8.0.30.jar 类名:com.mysql.jdbc.Driver
   - dm8 数据库驱动选择 dmdbms\drivers\jdbc\DmJdbcDriver18.jar 类名:dm.jdbc.driver.DmDriver
   - 表定义 -> 如果目的表已存在,先删除 √
   - 约束 -> 保留引用表原有模式信息 x
   - 数据 -> 删除后拷贝记录 √

## 达梦常用操作

1. 创建表空间及用户

```sql
create tablespace "efileyun" datafile '/data/dmdata/DAMENG/efileyun.DBF' size 2048 ;--创建表空间efileyun，数据文件为efileyun.DBF。

create user "EFILEYUN" identified by "xmhymake123456" --创建用户
default tablespace "efileyun"--指定用户EFILEYUN表空间为efileyun
default index tablespace "efileyun";--指定用户EFILEYUN索引表空间为efileyun
grant "PUBLIC","RESOURCE","SOI","SVI","VTI" to "efileyun";--授予用户EFILEYUN常规权限
```

2. 兼容 mysql 模式
   |数据库参数| 参数值|
   |-|-|
   |DB_NAME（数据库名）|DAMENG（根据需求设置）|
   |INSTANCE_NAME（实例名）|DMSERVER（根据需求设置）|
   |PORT_NUM（端口）|5236（正式移植环境下，为保证数据库安全，不建议使用默认端口 5236）|
   |管理员、审计员、安全员密码（安全版本特有）|不推荐使用默认密码|
   |EXTENT_SIZE（簇大小）|16|
   |PAGE_SIZE（页大小）|32|
   |LOG_SIZE （日志大小）|2048M|
   |CHARSET（字符集）|UTF-8（一般是 UTF8，根据实际要求设置）|
   |CASE_SENSITIVE（大小写敏感）|不敏感（一般是不敏感，根据实际要求设置）|
   |LENGTH_IN_CHAR（ VARCHAR 类型以字符为计算单位）|是|
   |BLANK_PAD_MODE（尾部空格填充）|否|

   兼容性参数设置:
   |参数名|含义|建议值|
   |-|-|-|
   |COMPATIBLE_MODE|是否兼容其他数据库模式。0：不兼容，1：兼容 SQL92 标准 2：兼容 ORACLE 3：兼容 MS SQL SERVER 4：兼容 MYSQL 5：兼容 DM6 6：兼容 TERADATA。|4（表示部分语法兼容 MYSQL），重启数据库生效。|
   |LENGTH_IN_CHAR|VARCHAR 类型对象的长度是否以字符为单位。1：是，设置为以字符为单位时，定义长度并非真正按照字符长度调整，而是将存储长度值按照理论字符长度进行放大。所以会出现实际可插入字符数超过定义长度的情况，这种情况也是允许的。|1（MYSQL4.0 以下版本以字节为单位，5.0 以上版本以字符为单位，当前一般是 5.0 以上，所以一般选择设置 1），该参数只能初始化阶段设置，后续不能修改。|
   |ORDER_BY_NULLS_FLAG|控制排序时 NULL 值返回的位置，取值 0、 1、2。 0 表示 NULL 值始终在最前面返回； 1 表示 ASC 升序排序时 NULL 值在最后返回， DESC 降序排序时 NULL 值在最前面返回， 在参数等于 1 的情况下， NULL 值的返回与 ORACLE 保持一致； 2 表示 ASC 升序排序时 NULL 值在最前面返回， DESC 降序排序时 NULL 值在最后返回，在参数等于 2 的情况下， NULL 值的返回与 MYSQL 保持一致。|2（兼容 MYSQL）。|
   |MY_STRICT_TABLES|是否开启 STRICT 模式（严格模式），仅在 COMPATIBLE_MODE=4 时有效。0：不开启，数据超长时自动截断；字符类型转换数值类型（包括 INT、SMALLINT、TINYINT、BIGINT、DEC、FLOAT、DOUBLE）失败时，转换为 0；1：开启，数据超长或计算错误时报错。|建议值：1。|

3. 达梦数据库导入导出 SQL
   使用迁移工具
   导入:选择 sql->dm 导入 sql
   导出:选择 dm->sql 导出 sql
