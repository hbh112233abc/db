<?php
declare(strict_types=1);

namespace bingher\db\builder;

use Exception;
use PDO;
use think\db\Builder;
use think\db\BaseQuery as Query;
use think\db\Raw;

/**
 * 达梦数据库驱动
 */
class DM extends Builder
{
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER% %LIMIT%%COMMENT%';

    /**
     * INSERT ALL SQL表达式.
     *
     * @var string
     */
    protected $insertAllSql = '%INSERT%%EXTRA% INTO %TABLE%%PARTITION% (%FIELD%) VALUES %DATA% %DUPLICATE%%COMMENT%';

    /**
     * 设置锁机制
     * @access protected
     * @param  Query      $query 查询对象
     * @param  bool|false $lock
     * @return string
     */
    protected function parseLock(Query $query, $lock = false): string
    {
        if (!$lock) {
            return '';
        }

        return ' FOR UPDATE NOWAIT ';
    }

    /**
     * table分析.
     *
     * @param Query $query  查询对象
     * @param array|string $tables 表名
     *
     * @return string
     */
    protected function parseTable(Query $query, array | string $tables): string
    {
        $item    = [];
        $options = $query->getOptions();

        foreach ((array) $tables as $key => $table) {
            if ($table instanceof Raw) {
                $item[] = $this->parseRaw($query, $table);
            } elseif (!is_numeric($key)) {
                $key = $query->fitTable($key);
                $item[] = $this->parseKey($query, $key) . ' ' . $this->parseKey($query, $table);
            } elseif (isset($options['alias'][$table])) {
                $table = $query->fitTable($table);
                $item[] = $this->parseKey($query, $table) . ' ' . $this->parseKey($query, $options['alias'][$table]);
            } else {
                $table = $query->fitTable($table);
                $item[] = $this->parseKey($query, $table);
            }
        }

        return implode(',', $item);
    }



    /**
     * 字段和表名处理
     * @access public
     *
     * @param Query $query  查询对象
     * @param mixed $key    字段名
     * @param bool  $strict 严格检测
     *
     * @return string
     */
    public function parseKey(Query $query, mixed $key, bool $strict = false): string
    {
        if (is_int($key)) {
            return (string) $key;
        } elseif ($key instanceof Raw) {
            return $this->parseRaw($query, $key);
        }

        $key = trim($key);

        if (str_contains($key, '->') && !str_contains($key, '(')) {
            // JSON字段支持
            [$field, $name] = explode($key, '->');
            $key            = $field . '."' . $name . '"';
        } elseif (str_contains($key, '.') && !preg_match('/[,\'\"\(\)\[\s]/', $key)) {
            [$table, $key] = explode('.', $key, 2);

            $alias = $query->getOptions('alias');

            if ('__TABLE__' == $table) {
                $table = $query->getOptions('table');
                $table = is_array($table) ? array_shift($table) : $table;
            }

            if (isset($alias[$table])) {
                $table = $alias[$table];
            }
        }

        if ($strict && !preg_match('/^[\w\.\*]+$/', $key)) {
            throw new Exception('not support data:' . $key);
        }

        if ('*' != $key && !preg_match('/[,\'\"\*\(\)\[.\s]/', $key)) {
            $key = '"' . $key . '"';
        }

        if (isset($table)) {
            $key = '"' . $table . '".' . $key;
        }

        return $key;
    }

    /**
     * 随机排序
     * @access protected
     * @param  Query $query 查询对象
     * @return string
     */
    protected function parseRand(Query $query): string
    {
        return 'DBMS_RANDOM.value';
    }


    /**
     * 设置是否REPLACE.
     *
     * @param bool $replace 是否使用REPLACE写入数据
     *
     * @return $this
     */
    public function replace(bool $replace = true)
    {
        return $this;
    }

    /**
     * Partition 分析.
     *
     * @param Query        $query     查询对象
     * @param string|array $partition 分区
     *
     * @return string
     */
    protected function parsePartition(Query $query, $partition): string
    {
        if ('' == $partition) {
            return '';
        }

        if (is_string($partition)) {
            $partition = explode(',', $partition);
        }

        return ' PARTITION (' . implode(' , ', $partition) . ') ';
    }

    /**
     * ON DUPLICATE KEY UPDATE 分析.
     *
     * @param Query $query     查询对象
     * @param mixed $duplicate
     *
     * @return string
     */
    protected function parseDuplicate(Query $query, $duplicate): string
    {
        if ('' == $duplicate) {
            return '';
        }

        if ($duplicate instanceof Raw) {
            return ' ON DUPLICATE KEY UPDATE ' . $this->parseRaw($query, $duplicate) . ' ';
        }

        if (is_string($duplicate)) {
            $duplicate = explode(',', $duplicate);
        }

        $updates = [];
        foreach ($duplicate as $key => $val) {
            if (is_numeric($key)) {
                $val       = $this->parseKey($query, $val);
                $updates[] = $val . ' = VALUES(' . $val . ')';
            } elseif ($val instanceof Raw) {
                $updates[] = $this->parseKey($query, $key) . ' = ' . $this->parseRaw($query, $val);
            } else {
                $name      = $query->bindValue($val, $query->getConnection()->getFieldBindType($key));
                $updates[] = $this->parseKey($query, $key) . ' = :' . $name;
            }
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(' , ', $updates) . ' ';
    }

    /**
     * 生成insertall SQL.
     *
     * @param Query $query   查询对象
     * @param array $dataSet 数据集
     *
     * @return string
     */
    public function insertAll(Query $query, array $dataSet): string
    {
        $options = $query->getOptions();
        $bind    = $query->getFieldsBindType();

        // 获取合法的字段
        if (empty($options['field']) || '*' == $options['field']) {
            $allowFields = array_keys($bind);
        } else {
            $allowFields = $options['field'];
        }

        $fields = [];
        $values = [];

        foreach ($dataSet as $data) {
            $data = $this->parseData($query, $data, $allowFields, $bind);

            $values[] = '( ' . implode(',', array_values($data)) . ' )';

            if (!isset($insertFields)) {
                $insertFields = array_keys($data);
            }
        }

        foreach ($insertFields as $field) {
            $fields[] = $this->parseKey($query, $field);
        }

        return str_replace(
            ['%INSERT%', '%EXTRA%', '%TABLE%', '%PARTITION%', '%FIELD%', '%DATA%', '%DUPLICATE%', '%COMMENT%'],
            [
                !empty($options['replace']) ? 'REPLACE' : 'INSERT',
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                $this->parsePartition($query, $options['partition']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseDuplicate($query, $options['duplicate']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

    /**
     * 生成insertall SQL
     * @access public
     * @param  Query    $query   查询对象
     * @param  array    $keys 键值
     * @param  array    $values 数据
     * @return string
     */
    public function insertAllByKeys(Query $query, array $keys, array $datas): string
    {
        $options = $query->getOptions();
        $bind    = $query->getFieldsBindType();
        $fields  = [];
        $values  = [];

        foreach ($keys as $field) {
            $fields[] = $this->parseKey($query, $field);
        }

        foreach ($datas as $data) {
            foreach ($data as $key => &$val) {
                if (!$query->isAutoBind()) {
                    $val = PDO::PARAM_STR == $bind[$keys[$key]] ? '\'' . $val . '\'' : $val;
                } else {
                    $val = $this->parseDataBind($query, $keys[$key], $val, $bind);
                }
            }
            $values[] = '( ' . implode(',', $data) . ' )';
        }

        return str_replace(
            ['%INSERT%', '%EXTRA%', '%TABLE%', '%PARTITION%', '%FIELD%', '%DATA%', '%DUPLICATE%', '%COMMENT%'],
            [
                !empty($options['replace']) ? 'REPLACE' : 'INSERT',
                $this->parseExtra($query, $options['extra']),
                $this->parseTable($query, $options['table']),
                $this->parsePartition($query, $options['partition']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseDuplicate($query, $options['duplicate']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
        );
    }

}
