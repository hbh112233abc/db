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

        // if (!empty($config['charset'])) {
        //     $dsn .= ';charset=' . $config['charset'];
        // }

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
        $sql             = "select a.column_name,data_type,DECODE (nullable, 'Y', 0, 1) notnull,data_default, DECODE (A .column_name,b.column_name,1,0) pk from all_tab_columns a,(select column_name from all_constraints c, all_cons_columns col where c.constraint_name = col.constraint_name and c.constraint_type = 'P' and c.table_name = '" . strtoupper($tableName) . "' ) b where table_name = '" . strtoupper($tableName) . "' and a.column_name = b.column_name (+)";

        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);

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
                    'autoinc' => $val['pk'],
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
        $sql    = 'select table_name from all_tables';
        $pdo    = $this->getPDOStatement($sql);
        $result = $pdo->fetchAll(PDO::FETCH_ASSOC);
        $info   = [];

        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }

        return $info;
    }

    /**
     * 获取最近插入的ID（如果使用自增列，需去掉此方法）
     * @access public
     * @param BaseQuery $query    查询对象
     * @param string    $sequence 自增序列名
     * @return mixed
     */
    // public function getLastInsID($sequence = null)
    public function getLastInsID(BaseQuery $query, string $sequence = null)
    {
        // $pdo      = $this->linkID->query("select {$sequence}.currval as id from dual");
        $pdo      = $this->linkID->query("SELECT LAST_INSERT_ID();");
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

    public function insert(BaseQuery $query, bool $getLastInsID = false)
    {
        $this->initConnect();
        $this->linkID->exec("SET IDENTITY_INSERT {$query->getTable()} ON");
        $result = parent::insert($query, $getLastInsID);
        // $this->linkID->exec("SET IDENTITY_INSERT {$query->getTable()} OFF");
        return $result;
    }

    public function insertAll(BaseQuery $query, array $dataSet = []): int
    {
        $this->initConnect();
        $this->linkID->exec("SET IDENTITY_INSERT {$query->getTable()} ON");
        $result = parent::insertAll($query, $dataSet);
        // $this->linkID->exec("SET IDENTITY_INSERT {$query->getTable()} OFF");
        return $result;
    }
}
