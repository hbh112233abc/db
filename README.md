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

## KingBase(金仓) **未测试验证**

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


## 参考资料

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
