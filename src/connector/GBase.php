<?php
namespace bingher\db\connector;

use PDO;
use think\db\BaseQuery;
use think\db\PDOConnection;

/**
 * 南大通用数据库驱动
 */
class GBase extends PDOConnection
{
    /**
     * 数据库连接参数配置.
     *
     * @var array
     */
    protected $config = [
        // 数据库类型
        'type'            => '',
        // 驱动类型: pdo_gbasedbt,pdo_odbc
        'driver'          => '',
        // 数据库实例
        'server'          => '',
        // 服务器地址
        'hostname'        => '',
        // 数据库名
        'database'        => '',
        // 用户名
        'username'        => '',
        // 密码
        'password'        => '',
        // 端口
        'hostport'        => '',
        // 连接dsn
        'dsn'             => '',
        // 数据库连接参数
        'params'          => [],
        // 数据库编码默认采用utf8
        'charset'         => 'zh_CN.utf8',
        // 客户端字符编码
        'client_charset'  => 'zh_CN.utf8',
        // 数据库表前缀
        'prefix'          => '',
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy'          => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate'     => false,
        // 读写分离后 主服务器数量
        'master_num'      => 1,
        // 指定从服务器序号
        'slave_no'        => '',
        // 模型写入后自动读取主服务器
        'read_master'     => false,
        // 是否严格检查字段是否存在
        'fields_strict'   => true,
        // 开启字段缓存
        'fields_cache'    => false,
        // 监听SQL
        'trigger_sql'     => true,
        // Builder类
        'builder'         => '',
        // Query类
        'query'           => '',
        // 是否需要断线重连
        'break_reconnect' => false,
        // 断线标识字符串
        'break_match_str' => [],
        // 自动参数绑定
        'auto_param_bind' => true,
    ];

    /**
     * PDO连接参数.
     *
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE                    => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE                 => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS            => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES       => true,//表示提取的时候将数值转换为字符串
        PDO::ATTR_EMULATE_PREPARES        => true,//prepare不发送，execute时发送完整的sql
        PDO::ODBC_ATTR_USE_CURSOR_LIBRARY => 2,//通过设置PDO::ODBC_ATTR_USE_CURSOR_LIBRARY属性为2，我们可以指示PDO_ODBC不使用UnixODBC的游标库，而是直接依赖于GBase8S的SQLFetchScroll函数
    ];

    /**
     * 参数绑定类型映射.
     *
     * @var array
     */
    protected $bindType = [
        'string'    => self::PARAM_STR,
        'str'       => self::PARAM_STR,
        'integer'   => self::PARAM_INT,
        'int'       => self::PARAM_INT,
        'boolean'   => self::PARAM_BOOL,
        'bool'      => self::PARAM_BOOL,
        'float'     => self::PARAM_FLOAT,
        'datetime'  => self::PARAM_STR,
        'timestamp' => self::PARAM_STR,
        //增加blob,clob
        'blob'      => PDO::PARAM_LOB,
        'clob'      => self::PARAM_STR,
    ];

    /**
     * 字段信息
     * @var array
     */
    protected $fields = [];

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        $driver = strtolower($config['driver']);
        if (!in_array($driver, ['pdo_odbc', 'pdo_gbasedbt'])) {
            throw new \InvalidArgumentException("`driver` only support 'pdo_gbasedbt','pdo_odbc'");
        }
        if ($driver == 'pdo_odbc') {
            if (stristr(PHP_OS, 'WIN')) {
                if (PHP_INT_SIZE == 4) {
                    $driver = 'odbc:Driver={GBase ODBC DRIVER};';
                } else {
                    $driver = 'odbc:Driver={GBase ODBC DRIVER (64-bit)};';
                }
            } else {
                $dirver = 'odbc:Driver=/opt/GBASE/gbase/lib/cli/iclis09b.so;';
            }
        } else {
            $driver = 'gbasedbt:';
        }

        $dsn = sprintf(
            "%sHOST=%s;SERV=%s;PROT=onsoctcp;SRVR=%s;DB=%s;DLOC=%s;CLOC=%s;UID=%s;PWD=%s;sqlmode=oracle;",
            $driver,
            $config['hostname'],
            $config['hostport'],
            $config['server'],
            $config['database'],
            $config['charset'],
            $config['client_charset'],
            $config['username'],
            $config['password'],
        );

        return $dsn;
    }

    /**
     * 取得数据表的字段信息
     * @access public
     * @param string $tableName
     * @return array
     */
    public function getFields(string $tableName): array
    {
        list($tableName) = explode(' ', $tableName);

        if (!empty($this->fields[$tableName])) {
            return $this->fields[$tableName];
        }

        $logSql = $this->config['trigger_sql'];

        $this->config['trigger_sql'] = false;

        $sql = <<<EOF
            SELECT
                t.tabid,
                c.colno,
                c.coltype,
                c.colname AS column_name,
                cs.constrtype AS not_null,
                ce.coltypename AS data_type,
                p.constrtype AS pk,
                c.collength AS length,
                cc.comments AS comment,
                get_default_value(c.coltype, c.extended_id, c.collength, d.default) AS default
            FROM systables AS t
            LEFT JOIN syscolumns AS c ON c.tabid = t.tabid
            LEFT JOIN syscolcomms AS cc ON (t.tabid = cc.tabid AND c.colno = cc.colno)
            LEFT JOIN sysdefaults AS d ON (t.tabid = d.tabid AND c.colno = d.colno)
            LEFT JOIN syscoldepend AS cd ON (c.tabid = cd.tabid AND c.colno = cd.colno)
            LEFT JOIN sysconstraints AS cs ON (cs.tabid = c.tabid AND cs.constrid = cd.constrid)
            LEFT JOIN syscolumnsext AS ce ON (ce.tabid = c.tabid AND ce.colno = c.colno)
            LEFT JOIN
            (
                SELECT
                    cs.constrtype,
                    cs.tabid,
                    scs.colno
                FROM sysconstraints AS cs
                LEFT JOIN sysindexes AS si ON (si.tabid = cs.tabid AND si.idxname = cs.idxname)
                LEFT JOIN syscolumnsext AS scs ON (scs.tabid = cs.tabid AND scs.colno = si.part1)
                WHERE cs.constrtype = 'P'
            ) AS p ON (p.tabid = t.tabid AND p.colno = c.colno)
            WHERE
                t.tabname = '{$tableName}';
        EOF;

        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        $this->config['trigger_sql'] = $logSql;

        $info = [];
        if ($result) {
            foreach ($result as $val) {
                $info[$val['column_name']] = [
                    'name'    => $val['column_name'],
                    'type'    => strtolower($val['data_type']),
                    'notnull' => $val['not_null'] == 'N',
                    'default' => $val['default'],
                    'primary' => $val['pk'] == 'P',
                    'autoinc' => stripos($val['data_type'], 'serial') !== false,
                ];
            }
        }

        $this->fields[$tableName] = $this->fieldCase($info);
        return $this->fields[$tableName];
    }

    /**
     * 取得数据库的表信息（暂时实现取得用户表信息）
     * @access   public
     * @param string $dbName
     * @return array
     */
    public function getTables(string $dbName = ''): array
    {
        $sql  = "select tabname from systables WHERE statlevel = 'A'";
        $pdo  = $this->getPDOStatement($sql);
        $info = $pdo->fetchAll(PDO::FETCH_COLUMN);
        return $info;
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @param BaseQuery $query    查询对象
     * @param string    $sequence 自增序列名
     * @return mixed
     */
    public function getLastInsID(BaseQuery $query, string $sequence = null)
    {
        $pdo      = $this->linkID->query("select dbinfo('sqlca.sqlerrd1') from dual;");
        $insertId = $pdo->fetchColumn();
        return $this->autoInsIDType($query, $insertId ?: "");
    }

    /**
     * SQL性能分析
     * @access protected
     * @param string $sql
     * @return array
     */
    protected function getExplain(string $sql)
    {
        return [];
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }

    /**
     * 获取字段类型.
     *
     * @param string $type 字段类型
     *
     * @return string
     */
    protected function getFieldType(string $type): string
    {
        if (0 === stripos($type, 'set') || 0 === stripos($type, 'enum')) {
            $result = 'string';
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $result = 'float';
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $result = 'int';
        } elseif (preg_match('/bool/is', $type)) {
            $result = 'bool';
        } elseif (0 === stripos($type, 'timestamp')) {
            $result = 'timestamp';
        } elseif (0 === stripos($type, 'datetime')) {
            $result = 'datetime';
        } elseif (0 === stripos($type, 'date')) {
            $result = 'date';
        } elseif (0 === stripos($type, 'blob')) { //增加支持blob
            $result = 'blob';
        } else {
            $result = 'string';
        }

        return $result;
    }

    /**
     * 获取字段绑定类型.
     *
     * @param string $type 字段类型
     *
     * @return int
     */
    public function getFieldBindType(string $type): int
    {

        if (in_array($type, ['integer', 'string', 'float', 'boolean', 'bool', 'int', 'str', 'blob'])) {
            $bind = $this->bindType[$type];
        } elseif (str_starts_with($type, 'set') || str_starts_with($type, 'enum')) {
            $bind = self::PARAM_STR;
        } elseif (preg_match('/(double|float|decimal|real|numeric)/is', $type)) {
            $bind = self::PARAM_FLOAT;
        } elseif (preg_match('/(int|serial|bit)/is', $type)) {
            $bind = self::PARAM_INT;
        } elseif (preg_match('/bool/is', $type)) {
            $bind = self::PARAM_BOOL;
        } else {
            $bind = self::PARAM_STR;
        }

        return $bind;
    }

    /**
     * 批量插入记录.
     *
     * @param BaseQuery $query   查询对象
     * @param array     $dataSet 数据集
     *
     * @throws \Exception
     * @throws \Throwable
     *
     * @return int
     */
    public function insertAll(BaseQuery $query, array $dataSet = []): int
    {
        if (!is_array(reset($dataSet))) {
            return 0;
        }

        $options = $query->parseOptions();

        if (!empty($options['limit']) && is_numeric($options['limit'])) {
            $limit = (int) $options['limit'];
        } else {
            $limit = 0;
        }

        if (0 === $limit && count($dataSet) >= 5000) {
            $limit = 1000;
        }

        //判断字段有没有text类型的字段,若有则使用单条插入
        $fields      = $this->getFields($query->getTable());
        $fieldsTypes = array_column(array_values($fields), 'type');
        if (in_array('text', $fieldsTypes)) {
            $limit = 1;
        }

        if ($limit) {
            // 分批写入 自动启动事务支持
            $this->startTrans();

            try {
                $array = array_chunk($dataSet, $limit, true);
                $count = 0;

                foreach ($array as $item) {
                    $sql   = $this->builder->insertAll($query, $item);
                    $count += $this->pdoExecute($query, $sql);
                }

                // 提交事务
                $this->commit();
            } catch (\Exception | \Throwable $e) {
                $this->rollback();

                throw $e;
            }

            return $count;
        }

        $sql = $this->builder->insertAll($query, $dataSet);

        return $this->pdoExecute($query, $sql);
    }
}
