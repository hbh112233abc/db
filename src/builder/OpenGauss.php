<?php
declare(strict_types=1);

namespace bingher\db\builder;

use think\db\Builder;
use think\db\BaseQuery as Query;
use think\db\Raw;

/**
 * OpenGauss数据库驱动.
 */
class OpenGauss extends Builder
{
    /**
     * INSERT SQL表达式.
     *
     * @var string
     */
    protected $insertSql = 'INSERT INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT% RETURNING %PK%';

    /**
     * INSERT ALL SQL表达式.
     *
     * @var string
     */
    protected $insertAllSql = 'INSERT INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';

    /**
     * UPDATE SQL表达式.
     *
     * @var string
     */
    protected $updateSql = 'UPDATE%EXTRA% %TABLE% %JOIN% SET %SET% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * DELETE SQL表达式.
     *
     * @var string
     */
    protected $deleteSql = 'DELETE%EXTRA% FROM %TABLE%%USING%%JOIN%%WHERE%%ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * limit分析.
     *
     * @param Query $query 查询对象
     * @param mixed $limit
     *
     * @return string
     */
    public function parseLimit(Query $query, string $limit): string
    {
        $limitStr = '';

        if (!empty($limit)) {
            $limit = explode(',', $limit);
            if (count($limit) > 1) {
                $limitStr .= ' LIMIT ' . $limit[1] . ' OFFSET ' . $limit[0] . ' ';
            } else {
                $limitStr .= ' LIMIT ' . $limit[0] . ' ';
            }
        }

        return $limitStr;
    }

    /**
     * 字段和表名处理.
     *
     * @param Query $query  查询对象
     * @param string|int|Raw $key    字段名
     * @param bool  $strict 严格检测
     *
     * @return string
     */
    public function parseKey(Query $query, string|int|Raw $key, bool $strict = false): string
    {
        if (is_int($key)) {
            return (string) $key;
        } elseif ($key instanceof Raw) {
            return $this->parseRaw($query, $key);
        }

        $key = trim($key);

        if (str_contains($key, '->') && !str_contains($key, '(')) {
            // JSON字段支持
            [$field, $name] = explode('->', $key);
            $key            = '"' . $field . '"' . '->>\'' . $name . '\'';
        } elseif (str_contains($key, '.')) {
            [$table, $key] = explode('.', $key, 2);

            $alias = $query->getOptions('alias');

            if ('__TABLE__' == $table) {
                $table = $query->getOptions('table');
                $table = is_array($table) ? array_shift($table) : $table;
            }

            if (isset($alias[$table])) {
                $table = $alias[$table];
            }

            if ('*' != $key && !preg_match('/[,\"\*\(\).\s]/', $key)) {
                $key = '"' . $key . '"';
            }
        }

        if (isset($table)) {
            $key = $table . '.' . $key;
        }

        return $key;
    }

    /**
     * 随机排序.
     *
     * @param Query $query 查询对象
     *
     * @return string
     */
    protected function parseRand(Query $query): string
    {
        return 'RANDOM()';
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

        $options = $query->getOptions();

        // 分析并处理数据
        $data = $this->parseData($query, $options['data']);
        if (empty($data)) {
            return '';
        }

        $fields = array_keys($data);
        foreach ($fields as &$field) {
            $field = $this->parseKey($query, $field);
        }
        $values = array_values($data);

        return str_replace(
            ['%INSERT%', '%TABLE%', '%EXTRA%', '%FIELD%', '%DATA%', '%COMMENT%', '%PK%'],
            [
                'INSERT',
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
                $query->getPk(),
            ],
            $this->insertSql
        );
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
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                'INSERT',
                $this->parseTable($query, $options['table']),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($query, $options['comment']),
            ],
            $this->insertAllSql
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
        $data1 = $this->parseData($query, $options['data']);
        if (empty($data1)) {
            return '';
        }

        $fields1 = array_keys($data1);
        foreach ($fields1 as &$field) {
            $field = $this->parseKey($query, $field);
        }
        $values1 = array_values($data1);

        //获取主键字段
        $pk = $query->getConnection()->getPk($this->parseTable($query, $options['table']));
        if (!is_string($pk)) {
            throw new \Exception(
                sprintf('Replace into operate require table [%s] must has a primary key', $options['table'])
            );
        }
        if (!in_array($pk, $fields1)) {
            throw new \Exception(
                sprintf('Replace into operate require data with primary key [%s]', $pk)
            );
        }

        $optData = $options['data'];
        unset($optData[$pk]);
        $data2 = $this->parseData($query, $optData);
        $set   = [];
        foreach ($data2 as $key => $val) {
            if ($key == $pk) {
                continue;
            }
            $set[] = $this->parseKey($query, $key) . ' = ' . $val;
        }

        $update = sprintf("UPDATE SET %s", implode(',', $set));

        $tplSql = "INSERT INTO %TABLE% (%FIELDS%) VALUES (%VALUES%) ON CONFLICT (%PK%) DO %UPDATE%";

        $finalSql = str_replace(
            ['%TABLE%', '%FIELDS%', '%VALUES%', '%PK%', '%UPDATE%'],
            [
                $this->parseTable($query, $options['table']),
                implode(',', $fields1),
                implode(',', $values1),
                $pk,
                $update,
            ],
            $tplSql
        );
        return $finalSql;
    }

    /**
     * 生成update SQL.
     *
     * @param Query $query 查询对象
     *
     * @return string
     */
    public function update(Query $query): string
    {
        $options = $query->getOptions();
        $data    = $this->parseData($query, $options['data']);

        if (empty($data)) {
            return '';
        }

        $set = [];
        foreach ($data as $key => $val) {
            $set[] = (str_contains($key, '->') ? strstr($key, '->', true) : $key) . ' = ' . $val;
        }

        return str_replace(
            ['%TABLE%', '%EXTRA%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                implode(' , ', $set),
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->updateSql
        );
    }

    /**
     * 生成delete SQL.
     *
     * @param Query $query 查询对象
     *
     * @return string
     */
    public function delete(Query $query): string
    {
        $options = $query->getOptions();

        return str_replace(
            ['%TABLE%', '%PARTITION%', '%EXTRA%', '%USING%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($query, $options['table']),
                $this->parseExtra($query, $options['extra']),
                !empty($options['using']) ? ' USING ' . $this->parseTable($query, $options['using']) . ' ' : '',
                $this->parseJoin($query, $options['join']),
                $this->parseWhere($query, $options['where']),
                $this->parseOrder($query, $options['order']),
                $this->parseLimit($query, $options['limit']),
                $this->parseLock($query, $options['lock']),
                $this->parseComment($query, $options['comment']),
            ],
            $this->deleteSql
        );
    }

}
