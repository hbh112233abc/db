# 南大通用数据库(GBase8s)

## 安装数据库

### 1. 下载安装包

- 登录官网下载页面 [安装包下载页](https://www.gbase.cn/download/gbase-8s-1?category=INSTALL_PACKAGE)
- 根据系统CPU架构下载对应的安装包
    - [x86_64安装包](https://www.gbase.cn/download/gbase-8s-1?category=INSTALL_PACKAGE)
    - [arm安装包](https://www.gbase.cn/download/gbase-8s-1?category=INSTALL_PACKAGE)
- 上传到系统
- 解压安装包 `tar -xvf <安装包>`
- 如果安装包内没有 `AutoInit_GBase8s.sh`,上传[AutoInit_GBase8s.sh](./AutoInit_GBase8s.sh)到安装包解压目录

### 2. 安装数据库

- 安装依赖包 unzip、libaio、libgcc、libstdc、ncurses、pam
    ```shell
    yum install unzip libaio libgcc libstdc ncurses pam -y
    ```

- root用户执行`AutoInit_GBase8s.sh`
    ```shell
    脚本参数说明
    Usage:
        AutoInit_GBase8s.sh [-d path] [-i path] [-p path] [-s y|n] [-l locale] [-u user] [-o y|n]
                            [-n servername] [-c num_of_cpu] [-m num_of_memory] [-t type_of_instance]

            -d path    The path of dbspace.
            -i path    The path of install software.
            -p path    The path of home path.
            -s y|n     Value of dbspace is 1GB? Yes/No, default is N.
            -u user    The user name for SYSDBA, gbasedbt, default is gbasedbt
            -l locale  DB_LOCALE/CLIENT_LOCALE/SERVER_LOCALE value.
            -o y|n     Only install software? Yes/No, default is N.
            -n NAME    Servername, default is gbase01.
            -c NUM     Number of CPU use.
            -m NUM     Number of MB Memory use.
            -t TYPE    Type of instance will install, [small], if use this, ignore -c and -m.
            -a y|n     Security need, default N.

    -d  指定数据库空间目录，默认为/data/gbase（若该目录非空，则使用INSTALL_DIR/data）
    -i  指定数据库软件安装目录INSTALL_DIR，默认为/opt/gbase
    -p  指定数据库用户gbasedbt的HOME目录，默认为/home/gbase
    -s  数据库空间是否均使用1GB，默认是n（所有数据库空间均使用1GB大小）
    -u  指定数据库系统管理员的名称，仅限gbasedbt,可以不指定该参数
    -l  指定数据库的DB_LOCALE/CLIENT_LOCALE参数值，默认为zh_CN.utf8
    -o  指定仅安装数据库，而不进行初始化操作，默认是n（安装并初始化数据库）
    -n  指定数据库服务名称
    -c  指定使用的CPU数量
    -m  指定使用的内存数量，单位为MB
    -t  指定安装的实例类型，当前可接受small
    -a  指定是否开启三权分立，默认是n
    ```

    > 执行安装命令

    ```shell
    bash AutoInit_GBase8s.sh -i /opt/GBASE/gbase
    ```

    > 安装后结果如下表示安装成功

    ```shell
    --== GBase 8s Information for this install ==--
    $GBASEDBTSERVER : gbase01
    $GBASEDBTDIR    : /opt/GBASE/gbase
    USER HOME       : /home/gbase
    DBSPACE DIR     : /opt/GBASE/gbase/data
    IP ADDRESS      : 0.0.0.0
    PORT NUMBER     : 9088
    $DB_LOCALE      : zh_CN.utf8
    $CLIENT_LOCALE  : zh_CN.utf8
    JDBC URL        : jdbc:gbasedbt-sqli://IPADDR:9088/testdb:GBASEDBTSERVER=gbase01;DB_LOCALE=zh_CN.utf8;CLIENT_LOCALE=zh_CN.utf8;IFX_LOCK_MODE_WAIT=10
    JDBC USERNAME   : gbasedbt
    JDBC PASSWORD   : GBase123$%
    INNER USERNAME  : dbtuser
    INNER PASSWORD  : GBase123$%
    ```

### 3. 安装客户端

- 安装依赖
    ```shell
    yum install unixODBC unixODBC-devel -y
    ```

- 安装包目录,解压客户端安装包
    ```shell
    tar -xvf clientsdk_3.5.1_3X2_1_x86_64.tar
    ```

- 执行安装脚本
    ```shell
    ./installclientsdk
    ```

### 4. 创建数据库

- 切换gbasedbt用户
    ```shell
    su - gbasedbt
    ```

- 常用操作

    - 查看数据库状态
    ```shell
    onstat -
    # 或查看进程
    ps -ef | grep oninit | grep -v grep
    ```
    - 停止服务
    ```shell
    onmode -ky
    ```
    - 启动服务
    ```shell
    oninit -vy
    ```
    - 查看数据库配置
    ```shell
    onstat -g cfg
    ```

- 操作数据库
    - 进入数据库交互终端
    ```shell
    dbaccess - -
    ```

    - 创建数据库
    ```shell
    create database efile_archive;
    ```

    - 设置日志模式以支持事务
    ```shell
    ontape -s -L 0 -t /dev/null -U efile_archive
    ```

    - 查看数据库用户
    ```shell
    database efile_archive;
    select * from sysusers;
    ```
    如果要设置用户权限,参考[GBase8s数据库中用户信息](https://blog.csdn.net/qq_38821806/article/details/122617772)

## 数据迁移

- 下载迁移工具,访问[官网](https://www.gbase.cn/download/gbase-8s-1?category=TOOLKIT),下载GBase8sMTK_V2.1.6_2_WIN10_x86_64.zip
- 解压安装迁移工具
- 迁移遇到的问题
    - 索引重名问题
        迁移工具会自动将主键索引名重命名,其他自定义索引保持不变,可能遇到部分表的索引名是一样的,需要先处理源数据库重名的索引名
    - 数据插入报错不允许null插入NOT NULL字段
        源数据是空字符串,gbase默认会转为null,需要关闭转换功能
        - 查看配置
        ```shell
        onstat -g cfg | grep NULL
        # 发现ENABLE_NULL_STRING 1
        ````
        - 修改配置
        ```shell
        echo "ENABLE_NULL_STRING 0" >> $GBASEDBTDIR/etc/$ONCONFIG
        ```
        - 重启服务
        ```shell
        onmode -ky
        oninit -vy
        ```
        - 查看配置
        ```shell
        onstat -g cfg | grep NULL
        # 发现ENABLE_NULL_STRING 0
        ```
## PHP适配

- 参考资料
    - [GBase 8s数据库连接 - PHP ODBC](https://www.gbase.cn/community/post/155)
    - [GBase 8s数据库连接 - PHP PDO_GBASEDBT](https://www.gbase.cn/community/post/156)
    - [Nginx下PHP连接到GBase 8s数据库 - PDO_GBASEDBT方式](https://blog.csdn.net/liaosnet/article/details/138073622)

- 安装php_odbc
    ```shell
    cd /www/server/php/80/ext/php_odbc
    phpize
    ./configure --with-php-config=/www/server/php/80/bin/php-config --with-pdo-odbc=unixODBC,/usr/
    make && make install
    ```
- 安装pdo_gbasedbt
    - 用户环境变量增加GBASEDBTDIR,LD_LIBRARY_PATH
    ```shell
    vim ~/.bashrc
    # 添加
    export GBASEDBTDIR=/opt/GBASE/gbase
    export LD_LIBRARY_PATH=$GBASEDBTDIR/lib:$GBASEDBTDIR/lib/cli:$GBASEDBTDIR/lib/esql:$GBASEDBTDIR/incl/cli:$GBASEDBTDIR/incl/esql:$LD_LIBRARY_PATH
    # 保存文件后,使其生效
    source ~/.bashrc
    ```
    - 执行php-config获取php-config路径，extension-dir目录
    ```shell
    /www/server/php/80/bin/php-config
    # php-config路径:  /www/server/php/80/bin/php-config
    # extension-dir目录: /www/server/php/80/lib/php/extensions/no-debug-non-zts-20200930
    ```
    - 上传PDO_GBASEDBT-1.3.6.tgz
    - 安装
    ```shell
    tar -xvf PDO_GBASEDBT-1.3.6.tgz
    cd PDO_GBASEDBT-1.3.6
    phpize
    ./configure --prefix=/www/server/php/80/lib/php/extensions/no-debug-non-zts-20200930 --with-php-config=/www/server/php/80/bin/php-config --with-pdo-gbasedbt=/opt/GBASE/gbase
    make && make install
    ```
- LIB库加入ld.so.conf配置
    ```shell
    cat > /etc/ld.so.conf.d/gbasedbt-x86_64.conf <<!
    /opt/GBASE/gbase/lib
    /opt/GBASE/gbase/lib/cli
    /opt/GBASE/gbase/lib/esql
    !
    ldconfig
    ```
- 修改php-fpm配置
    ```shell
    vim /www/server/php/80/etc/php-fpm.d/www.conf.default
    ; 打开环境变量配置参数
    clear_env = no
    ; 使用PDO_GBASEDBT连接时需要配置以下环境变量
    env[GBASEDBTDIR] = /opt/GBASE/gbase
    ```
- 重启php-fpm
- 查看PDO_GBASEDBT是否已加载
    ```shell
    php -m | grep -i pdo_gbasedbt
    ```
- 测试数据库连接

```php
<?php
header('Content-type:text/html;charset=utf-8');
$dbh = new PDO("gbasedbt:HOST=192.168.80.70;SERV=9088;PROT=onsoctcp;SRVR=gbase01;DB=testdb;DLOC=zh_CN.utf8;CLOC=zh_CN.utf8","gbasedbt","GBase123$%");
$dbh->setAttribute(PDO::ATTR_CASE,PDO::CASE_NATURE);//保持字段大小写
# 指定数据库连接指令

echo "初始化表 tabpdogbasedbt<br>";
echo "drop table tabpdogbasedbt<br>";
$sql="drop table if exists tabpdogbasedbt";
$dbh->exec($sql);

echo "create table tabpdogbasedbt<br>";
$sql="create table tabpdogbasedbt(col1 int, col2 varchar(255), primary key(col1))";
$dbh->exec($sql);

echo "insert into tabpdogbasedbt<br>";
$sql="insert into tabpdogbasedbt values(?,?)";
$stmt = $dbh->prepare($sql);
$stmt->execute([1,'南大通用']);
$stmt = $dbh->prepare($sql)->execute([2,'南大通用北京分公司']);

echo "select from tabpdogbasedbt<br>";
$sql="select * from tabpdogbasedbt";
$stmt = $dbh->query($sql);
$rows = $stmt->fetchAll();

echo "<table><tr>";
echo "<th>col1</th>";
echo "<th>col2</th></tr>";

foreach($rows as $row) {
    echo "<tr><td>$row['col1']</td>";
    echo "<td>$row['col2']</td></tr>";
}

echo "</table>";
```

## dbaccess增强

> 安装rlwrap,支持dbaccess中使用方向键,回溯历史命令,修改当前命令.

- 参考资料
    - [Linux 环境下安装rlwrap工具](https://www.cnblogs.com/laonicc/p/11978132.html)
    - [在麒麟V10中安装rlwrap简化达梦数据库disql,drman命令行操作](https://blog.csdn.net/back003/article/details/123102301)
- 下载[rlwrap](https://pkgs.org/download/rlwrap)
- 安装rlwrap
    ```shell
    rpm -ivh rlwrap-0.46.1-1.el8.aarch64.rpm
    ```
    提示需要 `/usr/bin/python3.6`,` perl(File::Slurp)`
    - 安装python3.6
    - perl默认已经安装了,需要File::Slurp模块,安装`cpan`（Perl的包管理器）,再安装File::Slurp模块
    ```shell
    yum install cpan
    cpan File::Slurp
    ```
    依赖安装后还是报错,放弃安装包安装
- 源码安装rlwrap
    - 下载rlwrap源码包
    - 上传到服务器
    - 安装
    ```shell
    tar -zxvf rlwrap-0.46.1.tar.gz
    cd rlwrap-0.46.1/
    ./configure
    make && make install
    rlwrap --version
    ```
- 设置gbasedbt启用rlwrap
    ```shell
    su - gbasedbt
    vim .bash_profile
    # 尾部添加
    alias dbaccess="rlwrap dbaccess"
    # 保存文件
    source .bash_profile
    ```
> 启用sqlmode=oracle模式

- 进入dbaccess,默认没开启oracle模式
    ```shell
    set environment sqlmode 'oracle';
    ```
    以上设置是会话级,每次进入都得设置才能生效
- 自动设置oracle模式,设置连接默认设置
    ```shell
    create procedure public.sysdbopen()
    set environment sqlmode 'oracle';
    end procedure;
    ```

## 卸载数据库

当用户需要卸载 GBase 8s 数据库系统时，需要先停止数据库服务，

1. 切换到 root 用户，进入安装目录下的 uninstall/uninstall_ids;
2. 执行该目录下的 uninstallids 命令进行卸载;
3. 卸载程序开始后，选择 2 并回车，删除所有相关联的数据库文件;

```shell
 cd /opt/GBASE/gbase/uninstall/uninstall_ids
./uninstallids
```
