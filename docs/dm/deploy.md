> Linux kylinv10 4.19.90-25.24.v2101.ky10.aarch64 #1 SMP Mon Feb 6 18:04:57 CST 2023 aarch64 aarch64 aarch64 GNU/Linux

- PHP 8.0.26 (cli) (built: Mar 27 2024 17:03:13) ( NTS )

设置达梦扩展,修改 php.ini

```
[DM]
extension=/dm8/drivers/php_pdo/libphp80_dm.so
extension=/dm8/drivers/php_pdo/php80_pdo_dm.so
```

> PHP Fatal error:Unable to start DM module in Unknown online 0 报错 [解决办法](https://blog.51cto.com/u_13229/8791287)
> SSLv3_client_method version OPENSSL_1_1_0 not define [解决办法](https://www.jianshu.com/p/2aaf22a780de)

以上两个问题解决办法:

```shell
vi /etc/ld.so.conf.d/dm.conf

# 添加以下内容
/usr/lib/
/usr/lib64/
/usr/local/openssl/lib

/dm8/bin

ldconfig -v
```

> 中文乱码问题

```shell
echo "CHAR_CODE=(PG_UTF8)" >> /etc/dm_svc.conf
```
