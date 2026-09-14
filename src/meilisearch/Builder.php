<?php
namespace bingher\db\meilisearch;

use bingher\db\meilisearch\exception\Exception;

/**
 * Meilisearch 参数构建器.
 *
 * 负责把 Query 的链式条件编译为 Meilisearch 的 filter 表达式与请求参数，
 * 角色等价于 ThinkORM 各数据库的 Builder。
 */
class Builder
{
    /**
     * 查询对象.
     *
     * @var Query
     */
    protected $query;

    /**
     * 架构函数.
     *
     * @param Query $query 查询对象
     */
    public function __construct(Query $query)
    {
        $this->query = $query;
    }

    /**
     * 获取查询对象.
     *
     * @return Query
     */
    public function getQuery(): Query
    {
        return $this->query;
    }

    /**
     * 编译完整的 filter 表达式（含软删除条件）.
     *
     * @param array $options 查询选项
     *
     * @return string
     */
    public function buildFullFilter(array $options): string
    {
        $filter     = $this->buildFilter($options['where'] ?? []);
        $softDelete = $this->parseSoftDelete($options);

        if ('' === $softDelete) {
            return $filter;
        }

        return '' === $filter ? $softDelete : '( ' . $filter . ' ) AND ' . $softDelete;
    }

    /**
     * 编译查询请求参数.
     *
     * @param array $options 查询选项
     *
     * @return array
     */
    public function parseSearchParams(array $options): array
    {
        $params = isset($options['params']) && is_array($options['params']) ? $options['params'] : [];

        if (!empty($options['keyword'])) {
            $params['q'] = $options['keyword'];
        }

        $filter = $this->buildFullFilter($options);
        if ('' !== $filter) {
            $params['filter'] = $filter;
        }

        if (!empty($options['search_on'])) {
            $params['attributesToSearchOn'] = array_values(array_unique($options['search_on']));
        }

        if (!empty($options['order'])) {
            $params['sort'] = array_values(array_unique($options['order']));
        }

        if (!empty($options['field'])) {
            $params['attributesToRetrieve'] = array_values(array_unique($options['field']));
        }

        if (isset($options['limit']) && null !== $options['limit']) {
            $params['limit'] = (int) $options['limit'];
        }

        if (!empty($options['offset'])) {
            $params['offset'] = (int) $options['offset'];
        }

        return $params;
    }

    /**
     * 编译 where 条件为 filter 表达式.
     *
     * 与 ThinkORM 一致：按逻辑分组拼接，同组内使用相同逻辑连接，
     * 组与组之间使用后一组的逻辑连接。
     *
     * @param array $where where 条件
     *
     * @return string
     */
    public function buildFilter(array $where): string
    {
        $filter = '';

        foreach ($where as $logic => $items) {
            $logic = 'OR' === strtoupper((string) $logic) ? 'OR' : 'AND';

            $parts = [];
            foreach ($items as $item) {
                $segment = $this->buildWhereItem($item);
                if ('' === $segment) {
                    continue;
                }
                $parts[] = $segment;
            }

            if (empty($parts)) {
                continue;
            }

            $segment = implode(' ' . $logic . ' ', $parts);
            $filter  = '' === $filter ? $segment : $filter . ' ' . $logic . ' ' . $segment;
        }

        return $filter;
    }

    /**
     * 编译单个 where 元素.
     *
     * @param array $item where 元素
     *
     * @return string
     */
    protected function buildWhereItem(array $item): string
    {
        $type = $item['type'] ?? 'item';

        if ('raw' === $type) {
            $value = trim((string) ($item['value'] ?? ''));

            return '' === $value ? '' : '( ' . $value . ' )';
        }

        if ('group' === $type) {
            $filter = $this->buildFilter($item['items'] ?? []);

            return '' === $filter ? '' : '( ' . $filter . ' )';
        }

        return $this->parseWhereItem((string) ($item['field'] ?? ''), $item['op'] ?? '=', $item['value'] ?? null);
    }

    /**
     * 编译单个查询条件.
     *
     * @param string $field 字段名
     * @param mixed  $op    操作符
     * @param mixed  $value 条件值
     *
     * @return string
     */
    public function parseWhereItem(string $field, $op, $value): string
    {
        $field = trim($field);
        $op    = strtoupper(trim((string) $op));

        if ('' === $op) {
            $op = '=';
        }

        if ('' === $field) {
            throw new Exception('Meilisearch 查询条件缺少字段名');
        }

        switch ($op) {
            case '=':
            case 'EQ':
                return $field . (is_null($value) ? ' IS NULL' : ' = ' . $this->parseValue($value));
            case '<>':
            case '!=':
            case 'NEQ':
                return $field . (is_null($value) ? ' IS NOT NULL' : ' != ' . $this->parseValue($value));
            case '>':
            case '>=':
            case '<':
            case '<=':
                if (is_null($value)) {
                    throw new Exception('Meilisearch 比较查询[' . $op . ']不支持 null 值:' . $field);
                }

                return $field . ' ' . $op . ' ' . $this->parseValue($value);
            case 'IN':
            case 'NOT IN':
                $values = [];
                foreach ((array) $value as $item) {
                    if (is_null($item) || is_bool($item)) {
                        continue;
                    }
                    $values[] = $this->parseValue($item);
                }

                if (empty($values)) {
                    throw new Exception('Meilisearch 的 IN 查询不支持空集合:' . $field);
                }

                return $field . ' ' . $op . ' [' . implode(', ', $values) . ']';
            case 'BETWEEN':
            case 'NOT BETWEEN':
                $range = array_values((array) $value);
                if (2 !== count($range) || is_null($range[0]) || is_null($range[1])) {
                    throw new Exception('Meilisearch 的 BETWEEN 查询需要两个非 null 的边界值:' . $field);
                }

                $start = $this->parseValue($range[0]);
                $end   = $this->parseValue($range[1]);
                if ('BETWEEN' === $op) {
                    return '( ' . $field . ' >= ' . $start . ' AND ' . $field . ' <= ' . $end . ' )';
                }

                return '( ' . $field . ' < ' . $start . ' OR ' . $field . ' > ' . $end . ' )';
            case 'NULL':
                return $this->buildIsNull($field);
            case 'NOTNULL':
            case 'NOT NULL':
                return $this->buildIsNotNull($field);
            case 'EXISTS':
                return $field . ' EXISTS';
            case 'NOT EXISTS':
            case 'NOTEXISTS':
                return $field . ' NOT EXISTS';
            default:
                if ('LIKE' === $op || 'NOT LIKE' === $op) {
                    throw new Exception('Meilisearch 的 LIKE 查询由查询器处理为全文检索关键词，不支持在 Builder 中编译');
                }

                throw new Exception('不支持的查询操作符:' . $op);
        }
    }

    /**
     * 编译查询值.
     *
     * @param mixed $value 条件值
     *
     * @return string
     */
    public function parseValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_null($value)) {
            return 'null';
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value) . '"';
    }

    /**
     * 编译排序表达式.
     *
     * @param string $field     排序字段
     * @param string $direction 排序方向
     *
     * @return string
     */
    public function parseOrder(string $field, string $direction = 'asc'): string
    {
        $field = trim($field);
        if ('' === $field) {
            throw new Exception('Meilisearch 排序缺少字段名');
        }

        return $field . ':' . ('desc' === strtolower($direction) ? 'desc' : 'asc');
    }

    /**
     * 编译"字段为空"表达式.
     *
     * Meilisearch 中缺失字段会被视为非空,因此需要同时匹配 IS NULL 与 NOT EXISTS。
     *
     * @param string $field 字段名
     *
     * @return string
     */
    public function buildIsNull(string $field): string
    {
        return '( ' . $field . ' IS NULL OR ' . $field . ' NOT EXISTS )';
    }

    /**
     * 编译"字段不为空"表达式.
     *
     * @param string $field 字段名
     *
     * @return string
     */
    public function buildIsNotNull(string $field): string
    {
        return '( ' . $field . ' IS NOT NULL AND ' . $field . ' EXISTS )';
    }

    /**
     * 编译软删除条件.
     *
     * @param array $options 查询选项
     *
     * @return string
     */
    protected function parseSoftDelete(array $options): string
    {
        $soft = $options['soft_delete'] ?? null;
        if (empty($soft) || empty($soft['field'])) {
            return '';
        }

        $field = $soft['field'];
        $type  = $soft['type'] ?? 'default';

        if ('with' === $type) {
            return '';
        }

        if ('only' === $type) {
            return $this->buildIsNotNull($field);
        }

        $value = $soft['value'] ?? null;

        if (is_null($value)) {
            return $this->buildIsNull($field);
        }

        return '( ' . $field . ' = ' . $this->parseValue($value) . ' OR ' . $field . ' NOT EXISTS )';
    }
}
