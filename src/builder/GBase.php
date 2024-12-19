<?php
declare(strict_types=1);

namespace bingher\db\builder;

use Exception;
use PDO;
use think\db\Builder;
use think\db\BaseQuery as Query;
use think\db\Raw;

/**
 * 南大通用数据库驱动
 */
class GBase extends Builder
{
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER% %LIMIT%%COMMENT%';

    /**
     * INSERT ALL SQL表达式.
     *
     * @var string
     */
    protected $insertAllSql = '%INSERT%%EXTRA% INTO %TABLE%%PARTITION% (%FIELD%) VALUES %DATA% %DUPLICATE%%COMMENT%';


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
            $key            = $field . '.' . $name;
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


        if (isset($table)) {
            $key = $table . '.' . $key;
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
        return 'DBMS_RANDOM.value()';
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

        return ' PARTITION BY ' . implode(' , ', $partition);
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
     * 生成Insert SQL.
     *
     * @param Query $query 查询对象
     *
     * @return string
     */
    public function insert(Query $query): string
    {
        $options = $query->getOptions();

        if (!empty($options['replace'])) {
            return $this->replaceSql($query);
        }

        // 分析并处理数据
        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return '';
        }

        $fields = array_keys($data);
        $values = array_values($data);

        return str_replace(
            ['%INSERT%', '%TABLE%', '%EXTRA%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                'INSERT',
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertSql
        );
    }

    /**
     * 生成replace into操作语句
     * @param \think\db\BaseQuery $query
     * @throws \DateException
     * @return string
     */
    public function replaceSql(Query $query)
    {
        $options = $query->getOptions();

        // 分析并处理数据
        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return '';
        }

        $fields = array_keys($data);
        $values = array_values($data);

        //获取主键字段
        $pk = $query->getConnection()->getPk($this->parseTable($query, $options['table']));
        if (!is_string($pk)) {
            throw new Exception(
                sprintf('Replace into operate require table [%s] must has a primary key', $options['table'])
            );
        }
        if (!in_array($pk, $fields)) {
            throw new Exception(
                sprintf('Replace into operate require data with primary key [%s]', $pk)
            );
        }
        $on  = sprintf('(t1.%s = t2.%s)', $pk, $pk);
        $set = [];
        $as  = [];
        foreach ($data as $key => $val) {
            $as[] = $val . ' AS ' . $key;
            if ($key == $pk) {
                continue;
            }
            $set[] = $key . ' = ' . $val;
        }
        $t2     = sprintf("(SELECT %s from dual)", implode(',', $as));
        $insert = sprintf("INSERT (%s) VALUES (%s)", implode(',', $fields), implode(',', $values));
        $update = sprintf("UPDATE SET %s", implode(',', $set));

        $tplSql = "MERGE INTO %TABLE% t1 USING %TEMP% t2 ON %ON% WHEN MATCHED THEN %UPDATE% WHEN NOT MATCHED THEN %INSERT%";

        $finalSql = str_replace(
            ['%TABLE%', '%TEMP%', '%ON%', '%UPDATE%', '%INSERT%'],
            [
                $this->parseTable($query, $options['table']),
                $t2,
                $on,
                $update,
                $insert,
            ],
            $tplSql
        );
        return $finalSql;
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
                'INSERT',
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
