<?php
namespace bingher\db\connector;

use PDO;
use think\db\BaseQuery;
use think\db\PDOConnection;

/**
 * 达梦数据库驱动
 */
class DM extends PDOConnection
{
    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn(array $config): string
    {
        $dsn = 'dm:host=';
        if (!empty($config['hostname'])) {
            $dsn .= $config['hostname'] . ($config['hostport'] ? ':' . $config['hostport'] : '');
        }

        if (!empty($config['database'])) {
            $dsn .= ';dbname=' . $config['database'];
        }

        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }

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

        $tableName = strtoupper($tableName);
        $schema    = strtoupper($this->config['database']);

        $logSql = $this->config['trigger_sql'];

        $this->config['trigger_sql'] = false;

        $sql = <<<EOF
            SELECT
                a.column_name,
                data_type,
                DECODE (nullable, 'Y', 0, 1) notnull,
                data_default,
                DECODE (a.column_name,b.column_name,1,0) pk,
                DECODE (a.column_name,d.column_name,1,0) autoinc
            FROM
            all_tab_columns a,
            (
                SELECT column_name
                FROM
                all_constraints c,
                all_cons_columns col
                WHERE
                c.constraint_name = col.constraint_name
                AND c.constraint_type = 'P'
                AND c.table_name = '{$tableName}'
                AND c.owner = '{$schema}'
            ) b,
            (
                SELECT COL.NAME as column_name
                FROM SYSOBJECTS TAB
                    ,SYSCOLUMNS COL
                    ,DBA_OBJECTS OBJ
                where TAB.ID = COL.ID
                AND TAB.SCHID = OBJ.OBJECT_ID
                AND TAB.TYPE$ = 'SCHOBJ'
                AND TAB.SUBTYPE$ = 'UTAB'
                AND COL.INFO2 & 0x01 = 1
                AND OBJ.OWNER = '{$schema}'
                AND TAB.NAME = '{$tableName}'
            ) d
            WHERE table_name = '{$tableName}'
            AND owner = '{$schema}'
            AND a.column_name = d.column_name (+)
            AND a.column_name = b.column_name (+)
        EOF;

        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

        $this->config['trigger_sql'] = $logSql;

        $info = [];

        if ($result) {
            foreach ($result as $val) {
                $val                       = array_change_key_case($val);
                $info[$val['column_name']] = [
                    'name'    => $val['column_name'],
                    'type'    => $val['data_type'],
                    'notnull' => $val['notnull'],
                    'default' => $val['data_default'],
                    'primary' => $val['pk'],
                    'autoinc' => $val['autoinc'],
                ];
            }
        }

        return $this->fieldCase($info);
    }

    /**
     * 取得数据库的表信息（暂时实现取得用户表信息）
     * @access   public
     * @param string $dbName
     * @return array
     */
    public function getTables(string $dbName = ''): array
    {
        $sql    = sprintf("select table_name from all_tables WHERE owner='%s'", strtoupper($dbName));
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

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
        $pdo      = $this->linkID->query("SELECT SCOPE_IDENTITY()");
        $insertId = $pdo->fetchColumn();
        return $this->autoInsIDType($query, $insertId);
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
     * 初始化数据库连接.
     *
     * @param bool $master 是否主服务器
     *
     * @return void
     */
    protected function initConnect(bool $master = true): void
    {
        if (!empty($this->config['deploy'])) {
            // 采用分布式数据库
            if ($master || $this->transTimes) {
                if (!$this->linkWrite) {
                    $this->linkWrite = $this->multiConnect(true);
                }

                $this->linkID = $this->linkWrite;
            } else {
                if (!$this->linkRead) {
                    $this->linkRead = $this->multiConnect(false);
                }

                $this->linkID = $this->linkRead;
            }
        } elseif (!$this->linkID) {
            // 默认单数据库
            $this->linkID = $this->connect();
        }
    }

    /**
     * 设置自增主键支持插入
     *
     * @param  BaseQuery $query 查询对象
     * @param  bool      $on    true: ON; false: OFF
     *
     * @return int              0:无需设置,1:设置成功,-1:设置失败
     */
    protected function setIdentityInsert(BaseQuery $query, bool $on = true): int
    {
        $pk = $query->getPk();
        if (empty($pk)) {
            return 0;
        }
        $data = $query->getOptions('data');
        if (!isset($data[$pk])) {
            return 0;
        }
        $this->initConnect();
        $flag    = $on ? 'ON' : 'OFF';
        $table   = $query->getTable();
        $fields  = $this->getFields($table);
        $pkField = $fields[$pk] ?? [];
        if (empty($pkField) || $pkField['autoinc'] === 0) {
            return 0;
        }
        try {
            $this->linkID->exec("SET IDENTITY_INSERT {$table} {$flag}");
            return 1;
        } catch (\Throwable $th) {
            return -1;
        }
    }

    public function insert(BaseQuery $query, bool $getLastInsID = false)
    {
        $this->setIdentityInsert($query);
        $result = parent::insert($query, $getLastInsID);
        return $result;
    }

    public function insertAll(BaseQuery $query, array $dataSet = []): int
    {
        try {
            $this->initConnect();
            //过滤无关字段
            $table  = $query->getTable();
            $fields = $this->getFields($table);
            $keys   = array_flip(array_keys($fields));
            foreach ($dataSet as &$data) {
                $data = array_intersect_key($data, $keys);
            }
            $this->linkID->exec("SET IDENTITY_INSERT {$table} ON");
        } catch (\Throwable $th) {
            //throw $th;
        }
        $result = parent::insertAll($query, $dataSet);
        return $result;
    }
}
