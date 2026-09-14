<?php
namespace bingher\db\meilisearch;

use bingher\db\meilisearch\exception\Exception;
use bingher\db\meilisearch\exception\ModelNotFoundException;
use Closure;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Search\SearchResult;
use Throwable;

/**
 * Meilisearch 查询器.
 *
 * 提供与 ThinkORM 一致的链式调用体验：
 *      ->table('books')->where('status', 1)->order('sale', 'desc')->limit(10)->select();
 */
class Query
{
    /**
     * 连接对象.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * 索引名称（不含前缀）.
     *
     * @var string
     */
    protected $name = '';

    /**
     * 索引前缀.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * 构建器.
     *
     * @var Builder
     */
    protected $builder;

    /**
     * 模型类名.
     *
     * @var string
     */
    protected $model = '';

    /**
     * 查询选项.
     *
     * @var array
     */
    protected $options = [
        'where'       => [],
        'order'       => [],
        'limit'       => null,
        'offset'      => 0,
        'page'        => null,
        'list_rows'   => null,
        'field'       => [],
        'without'     => [],
        'keyword'     => null,
        'search_on'   => [],
        'params'      => [],
        'key'         => null,
        'pk'          => null,
        'soft_delete' => null,
        'empty'       => false,
    ];

    /**
     * 最后一次任务信息.
     *
     * @var array
     */
    protected $lastTask = [];

    /**
     * 最后一次搜索结果.
     *
     * @var SearchResult|null
     */
    protected $lastResult;

    /**
     * 架构函数.
     *
     * @param Connection $connection 连接对象
     * @param string|null $name      索引名称
     */
    public function __construct(Connection $connection, ?string $name = null)
    {
        $this->connection = $connection;
        $this->name       = (string) $name;
        $this->prefix     = $connection->getPrefix();
        $this->builder    = new Builder($this);
    }

    /* ------------------------------------------------------------------ */
    /* 基础信息                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 获取连接对象.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * 获取配置参数.
     *
     * @param string $name    配置名称
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public function getConfig(string $name = '', $default = null)
    {
        return $this->connection->getConfig($name, $default);
    }

    /**
     * 获取构建器.
     *
     * @return Builder
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    /**
     * 设置构建器.
     *
     * @param Builder $builder 构建器
     *
     * @return $this
     */
    public function setBuilder(Builder $builder)
    {
        $this->builder = $builder;

        return $this;
    }

    /**
     * 指定索引名称.
     *
     * @param string $name 索引名称
     *
     * @return $this
     */
    public function table(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * 指定索引名称（table 别名）.
     *
     * @param string $name 索引名称
     *
     * @return $this
     */
    public function index(string $name)
    {
        return $this->table($name);
    }

    /**
     * 指定索引名称（table 别名）.
     *
     * @param string $name 索引名称
     *
     * @return $this
     */
    public function name(string $name)
    {
        return $this->table($name);
    }

    /**
     * 设置索引前缀.
     *
     * @param string $prefix 前缀
     *
     * @return $this
     */
    public function prefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * 获取索引名称（不含前缀）.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取完整索引名称（含前缀）.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        if ('' === $this->prefix || str_starts_with($this->name, $this->prefix)) {
            return $this->name;
        }

        return $this->prefix . $this->name;
    }

    /**
     * 获取索引对象.
     *
     * @return Indexes
     */
    public function getIndex(): Indexes
    {
        return $this->connection->getIndex($this->name);
    }

    /**
     * 设置主键名.
     *
     * @param string $pk 主键名
     *
     * @return $this
     */
    public function pk(string $pk)
    {
        $this->options['pk'] = $pk;

        return $this;
    }

    /**
     * 获取主键名.
     *
     * @return string
     */
    public function getPk(): string
    {
        if (!empty($this->options['pk'])) {
            return (string) $this->options['pk'];
        }

        if (!empty($this->model)) {
            return (new $this->model)->getPk();
        }

        return 'id';
    }

    /**
     * 设置模型类名.
     *
     * @param string $model 模型类名
     *
     * @return $this
     */
    public function setModel(string $model)
    {
        $this->model = $model;

        if (method_exists($model, 'getPk')) {
            $this->options['pk'] = (new $model)->getPk();
        }

        $deleteTime = method_exists($model, 'getDeleteTime') ? (new $model)->getDeleteTime() : null;
        if (!empty($deleteTime) && empty($this->options['soft_delete'])) {
            $this->useSoftDelete($deleteTime);
        }

        return $this;
    }

    /**
     * 获取模型类名.
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * 获取查询选项.
     *
     * @param string|null $name   选项名称
     * @param mixed       $default 默认值
     *
     * @return mixed
     */
    public function getOptions(?string $name = null, $default = null)
    {
        if (is_null($name)) {
            return $this->options;
        }

        return $this->options[$name] ?? $default;
    }

    /**
     * 设置查询选项.
     *
     * @param string $name  选项名称
     * @param mixed  $value 选项值
     *
     * @return $this
     */
    public function setOption(string $name, $value)
    {
        $this->options[$name] = $value;

        return $this;
    }

    /**
     * 创建一个新的查询对象（保留当前条件）.
     *
     * @return static
     */
    public function newQuery()
    {
        return clone $this;
    }

    /**
     * 获取最后一次任务信息.
     *
     * @return array
     */
    public function getLastTask(): array
    {
        return $this->lastTask;
    }

    /**
     * 获取最后一次搜索结果.
     *
     * @return SearchResult|null
     */
    public function getLastResult(): ?SearchResult
    {
        return $this->lastResult;
    }

    /* ------------------------------------------------------------------ */
    /* 原生参数                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 设置原生 Meilisearch 搜索参数.
     *
     * @param string|array $key   参数名或参数数组
     * @param mixed        $value 参数值
     *
     * @return $this
     */
    public function option($key, $value = null)
    {
        if (is_array($key)) {
            $this->options['params'] = array_merge($this->options['params'], $key);

            return $this;
        }

        $this->options['params'][$key] = $value;

        return $this;
    }

    /**
     * 批量设置原生参数.
     *
     * @param array $options 参数数组
     *
     * @return $this
     */
    public function options(array $options)
    {
        return $this->option($options);
    }

    /* ------------------------------------------------------------------ */
    /* 查询条件                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 指定 AND 查询条件.
     *
     * @param mixed $field     字段名
     * @param mixed $op        操作符或条件值
     * @param mixed $condition 条件值
     *
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        return $this->parseWhereExp('AND', $field, $op, $condition);
    }

    /**
     * 指定 OR 查询条件.
     *
     * @param mixed $field     字段名
     * @param mixed $op        操作符或条件值
     * @param mixed $condition 条件值
     *
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        return $this->parseWhereExp('OR', $field, $op, $condition);
    }

    /**
     * 解析查询表达式.
     *
     * @param string $logic     逻辑
     * @param mixed  $field     字段名
     * @param mixed  $op        操作符
     * @param mixed  $condition 条件值
     *
     * @return $this
     */
    protected function parseWhereExp(string $logic, $field, $op, $condition)
    {
        $logic = 'OR' === strtoupper($logic) ? 'OR' : 'AND';

        if ($field instanceof Closure) {
            $query = $this->newQuery();
            $query->options['where'] = [];
            $field($query);

            $items = $query->getOptions('where');
            if (!empty($items)) {
                $this->options['where'][$logic][] = ['type' => 'group', 'items' => $items];
            }

            return $this;
        }

        if ($field instanceof self) {
            $items = $field->getOptions('where');
            if (!empty($items)) {
                $this->options['where'][$logic][] = ['type' => 'group', 'items' => $items];
            }

            return $this;
        }

        if (is_array($field)) {
            return $this->parseArrayWhereItems($field, $logic);
        }

        if (!is_string($field) || '' === trim($field)) {
            return $this;
        }

        // 原生 filter 表达式
        if (preg_match('/[=<>!\(\)\s]/', $field)) {
            $this->addWhere($logic, 'raw', '', '', $field);

            return $this;
        }

        return $this->parseWhereItem($field, $op, $condition, $logic);
    }

    /**
     * 解析单个查询条件.
     *
     * @param string $field     字段名
     * @param mixed  $op        操作符
     * @param mixed  $condition 条件值
     * @param string $logic     逻辑
     *
     * @return $this
     */
    protected function parseWhereItem(string $field, $op, $condition, string $logic)
    {
        if (is_null($condition)) {
            $operator = is_string($op) ? strtoupper($op) : '';

            if (in_array($operator, ['NULL', 'NOTNULL', 'NOT NULL'], true)) {
                $this->addWhere($logic, 'item', $field, $operator, null);

                return $this;
            }

            if ('<>' === $op || '!=' === $op) {
                $this->addWhere($logic, 'item', $field, 'NOTNULL', null);

                return $this;
            }

            if (is_array($op) || $op instanceof Closure) {
                $this->addWhere($logic, 'item', $field, 'IN', $op);

                return $this;
            }

            $this->addWhere($logic, 'item', $field, '=', $op);
            if ($field === $this->getPk()) {
                $this->options['key'] = $op;
            }

            return $this;
        }

        $operator = is_string($op) ? strtoupper(trim($op)) : '=';

        if ('LIKE' === $operator || 'NOT LIKE' === $operator) {
            $this->parseLike($field, $condition, 'NOT LIKE' === $operator);

            return $this;
        }

        $this->addWhere($logic, 'item', $field, $operator, $condition);

        return $this;
    }

    /**
     * 数组批量查询.
     *
     * @param array  $field 查询条件
     * @param string $logic 逻辑
     *
     * @return $this
     */
    protected function parseArrayWhereItems(array $field, string $logic)
    {
        foreach ($field as $key => $val) {
            if (is_int($key)) {
                if (is_array($val)) {
                    $this->parseWhereItem((string) ($val[0] ?? ''), $val[1] ?? '=', $val[2] ?? null, $logic);
                } else {
                    $this->where($val, null, null);
                }

                continue;
            }

            $this->parseWhereItem((string) $key, is_array($val) ? 'IN' : '=', $val, $logic);
        }

        return $this;
    }

    /**
     * 添加查询条件.
     *
     * @param string $logic 逻辑
     * @param string $type  类型:item|raw|group
     * @param string $field 字段名
     * @param mixed  $op    操作符
     * @param mixed  $value 条件值
     *
     * @return void
     */
    protected function addWhere(string $logic, string $type, string $field, $op, $value): void
    {
        if ('item' === $type && in_array(strtoupper((string) $op), ['IN', 'NOT IN'], true)) {
            $values = is_array($value) ? $value : [$value];
            if (empty($values)) {
                // 空集合查询直接短路为空结果
                $this->options['empty'] = true;

                return;
            }
        }

        $item = ['type' => $type];
        if ('raw' === $type) {
            $item['value'] = $value;
        } else {
            $item['field'] = $field;
            $item['op']    = $op;
            $item['value'] = $value;
        }

        $this->options['where'][$logic][] = $item;
    }

    /**
     * 处理 LIKE 查询（转换为 Meilisearch 全文检索关键词）.
     *
     * @param string $field     字段名
     * @param mixed  $condition 条件值
     * @param bool   $not       是否取反
     *
     * @return void
     */
    protected function parseLike(string $field, $condition, bool $not): void
    {
        if ($not) {
            throw new Exception('Meilisearch 不支持 NOT LIKE 查询,请使用 whereNotIn/where 等过滤条件替代');
        }

        $keyword = str_replace(['%', '_'], ' ', (string) $condition);
        $keyword = trim((string) preg_replace('/\s+/', ' ', $keyword));

        $this->options['keyword'] = trim(($this->options['keyword'] ?? '') . ' ' . $keyword);

        if ('' !== $field) {
            $this->options['search_on'][] = $field;
        }
    }

    /**
     * 指定 IN 查询.
     *
     * @param string $field     字段名
     * @param mixed  $condition 条件值
     * @param string $logic     逻辑
     *
     * @return $this
     */
    public function whereIn(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'IN', $condition);
    }

    /**
     * 指定 NOT IN 查询.
     *
     * @param string $field     字段名
     * @param mixed  $condition 条件值
     * @param string $logic     逻辑
     *
     * @return $this
     */
    public function whereNotIn(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT IN', $condition);
    }

    /**
     * 指定 NULL 查询.
     *
     * @param string $field 字段名
     * @param string $logic 逻辑
     *
     * @return $this
     */
    public function whereNull(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NULL', null);
    }

    /**
     * 指定 NOT NULL 查询.
     *
     * @param string $field 字段名
     * @param string $logic 逻辑
     *
     * @return $this
     */
    public function whereNotNull(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT NULL', null);
    }

    /**
     * 指定 BETWEEN 查询.
     *
     * @param string $field     字段名
     * @param mixed  $condition 条件值
     * @param string $logic     逻辑
     *
     * @return $this
     */
    public function whereBetween(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'BETWEEN', $condition);
    }

    /**
     * 指定 NOT BETWEEN 查询.
     *
     * @param string $field     字段名
     * @param mixed  $condition 条件值
     * @param string $logic     逻辑
     *
     * @return $this
     */
    public function whereNotBetween(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT BETWEEN', $condition);
    }

    /**
     * 指定 LIKE 查询（转换为全文检索关键词）.
     *
     * @param string $field     字段名
     * @param mixed  $condition 条件值
     * @param string $logic     逻辑
     *
     * @return $this
     */
    public function whereLike(string $field, $condition, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'LIKE', $condition);
    }

    /**
     * 指定字段存在查询.
     *
     * @param string $field 字段名
     * @param string $logic 逻辑
     *
     * @return $this
     */
    public function whereExists(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'EXISTS', null);
    }

    /**
     * 指定字段不存在查询.
     *
     * @param string $field 字段名
     * @param string $logic 逻辑
     *
     * @return $this
     */
    public function whereNotExists(string $field, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $field, 'NOT EXISTS', null);
    }

    /**
     * 指定原生 filter 表达式.
     *
     * @param string $filter filter 表达式
     * @param string $logic  逻辑
     *
     * @return $this
     */
    public function whereRaw(string $filter, string $logic = 'AND')
    {
        return $this->parseWhereExp($logic, $filter, null, null);
    }

    /**
     * 指定原生 filter 表达式（OR）.
     *
     * @param string $filter filter 表达式
     *
     * @return $this
     */
    public function whereOrRaw(string $filter)
    {
        return $this->whereRaw($filter, 'OR');
    }

    /**
     * 去除某个查询条件.
     *
     * @param string $field 字段名
     * @param string $logic 逻辑
     *
     * @return $this
     */
    public function removeWhereField(string $field, string $logic = 'AND')
    {
        $logic = 'OR' === strtoupper($logic) ? 'OR' : 'AND';

        if (isset($this->options['where'][$logic])) {
            foreach ($this->options['where'][$logic] as $key => $val) {
                if (isset($val['field']) && $val['field'] === $field) {
                    unset($this->options['where'][$logic][$key]);
                }
            }
        }

        return $this;
    }

    /**
     * 条件查询.
     *
     * @param mixed       $condition 条件
     * @param Closure|array $query   满足条件的查询
     * @param Closure|array|null $otherwise 不满足条件的查询
     *
     * @return $this
     */
    public function when($condition, $query, $otherwise = null)
    {
        if ($condition instanceof Closure) {
            $condition = $condition($this);
        }

        if ($condition) {
            $this->executeQuery($query, $condition);
        } elseif ($otherwise) {
            $this->executeQuery($otherwise, $condition);
        }

        return $this;
    }

    /**
     * 执行条件查询.
     *
     * @param Closure|array $query     查询
     * @param mixed         $condition 条件
     *
     * @return void
     */
    protected function executeQuery($query, $condition): void
    {
        if ($query instanceof Closure) {
            $query($this, $condition);
        } elseif (is_array($query)) {
            $this->where($query);
        }
    }

    /* ------------------------------------------------------------------ */
    /* 搜索与字段                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * 指定全文检索关键词.
     *
     * @param string $keyword    关键词
     * @param array  $attributes 限定搜索字段
     *
     * @return $this
     */
    public function search(string $keyword, array $attributes = [])
    {
        $this->options['keyword'] = trim($keyword);

        if (!empty($attributes)) {
            $this->options['search_on'] = array_merge($this->options['search_on'], $attributes);
        }

        return $this;
    }

    /**
     * 指定全文检索关键词（search 别名）.
     *
     * @param string $keyword    关键词
     * @param array  $attributes 限定搜索字段
     *
     * @return $this
     */
    public function keyword(string $keyword, array $attributes = [])
    {
        return $this->search($keyword, $attributes);
    }

    /**
     * 指定返回字段.
     *
     * @param string|array $field  字段名
     * @param bool         $except 是否排除
     *
     * @return $this
     */
    public function field($field, bool $except = false)
    {
        $fields = is_array($field) ? $field : explode(',', (string) $field);
        $fields = array_values(array_filter(array_map('trim', $fields)));

        if ($except) {
            return $this->withoutField($fields);
        }

        $this->options['field'] = array_merge($this->options['field'], $fields);

        return $this;
    }

    /**
     * 排除返回字段.
     *
     * @param string|array $field 字段名
     *
     * @return $this
     */
    public function withoutField($field)
    {
        $fields = is_array($field) ? $field : explode(',', (string) $field);
        $fields = array_values(array_filter(array_map('trim', $fields)));

        $this->options['without'] = array_merge($this->options['without'], $fields);

        return $this;
    }

    /**
     * 指定排序.
     *
     * @param string|array $field     排序字段
     * @param string       $direction 排序方向
     *
     * @return $this
     */
    public function order($field, string $direction = 'asc')
    {
        if (is_array($field)) {
            foreach ($field as $key => $value) {
                if (is_int($key)) {
                    $this->orderRaw((string) $value);
                } else {
                    $this->order((string) $key, (string) $value);
                }
            }

            return $this;
        }

        $field = trim((string) $field);
        if (str_contains($field, ':')) {
            return $this->orderRaw($field);
        }

        $this->options['order'][] = $this->builder->parseOrder($field, $direction);

        return $this;
    }

    /**
     * 指定原生排序表达式.
     *
     * @param string $sort 排序表达式,如 price:desc
     *
     * @return $this
     */
    public function orderRaw(string $sort)
    {
        $sort = trim($sort);
        if ('' !== $sort) {
            $this->options['order'][] = $sort;
        }

        return $this;
    }

    /**
     * 指定查询数量.
     *
     * @param int      $offset 起始位置或数量
     * @param int|null $length 数量
     *
     * @return $this
     */
    public function limit(int $offset, ?int $length = null)
    {
        if (is_null($length)) {
            $length = $offset;
            $offset = 0;
        }

        $this->options['offset'] = max(0, $offset);
        $this->options['limit']  = max(1, $length);

        return $this;
    }

    /**
     * 指定分页.
     *
     * @param int      $page     页码
     * @param int|null $listRows 每页数量
     *
     * @return $this
     */
    public function page(int $page, ?int $listRows = null)
    {
        $page = max(1, $page);
        $this->options['page'] = $page;

        if (!is_null($listRows)) {
            $this->options['list_rows'] = $listRows;
        }

        $listRows = $listRows ?? $this->options['list_rows'] ?? (int) $this->getConfig('list_rows', 15);

        return $this->limit(($page - 1) * $listRows, $listRows);
    }

    /**
     * 指定分面统计字段.
     *
     * @param array $fields 字段列表
     *
     * @return $this
     */
    public function facets(array $fields)
    {
        return $this->option('facets', array_values(array_filter($fields)));
    }

    /**
     * 指定高亮字段.
     *
     * @param array  $fields     字段列表
     * @param string $preTag     前置标签
     * @param string $postTag    后置标签
     *
     * @return $this
     */
    public function highlight(array $fields, string $preTag = '<em>', string $postTag = '</em>')
    {
        return $this->option([
            'attributesToHighlight' => array_values(array_filter($fields)),
            'highlightPreTag'       => $preTag,
            'highlightPostTag'      => $postTag,
        ]);
    }

    /**
     * 指定匹配策略.
     *
     * @param string $strategy last|all
     *
     * @return $this
     */
    public function matchingStrategy(string $strategy)
    {
        return $this->option('matchingStrategy', $strategy);
    }

    /**
     * 是否返回相关性评分.
     *
     * @param bool $show 是否返回
     *
     * @return $this
     */
    public function showRankingScore(bool $show = true)
    {
        return $this->option('showRankingScore', $show);
    }

    /* ------------------------------------------------------------------ */
    /* 软删除                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * 设置软删除字段及条件.
     *
     * @param string $field  字段名
     * @param mixed  $value  未删除时的值(null 表示 IS NULL)
     *
     * @return $this
     */
    public function useSoftDelete(string $field, $value = null)
    {
        $this->options['soft_delete'] = [
            'field' => $field,
            'value' => $value,
            'type'  => 'default',
            'force' => false,
        ];

        return $this;
    }

    /**
     * 包含软删除数据.
     *
     * @return $this
     */
    public function withTrashed()
    {
        if (isset($this->options['soft_delete'])) {
            $this->options['soft_delete']['type'] = 'with';
        }

        return $this;
    }

    /**
     * 只查询软删除数据.
     *
     * @return $this
     */
    public function onlyTrashed()
    {
        if (isset($this->options['soft_delete'])) {
            $this->options['soft_delete']['type'] = 'only';
        }

        return $this;
    }

    /**
     * 强制物理删除.
     *
     * @param bool $force 是否强制
     *
     * @return $this
     */
    public function force(bool $force = true)
    {
        if (isset($this->options['soft_delete'])) {
            $this->options['soft_delete']['force'] = $force;
        }

        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* 参数编译                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 获取最终的 filter 表达式.
     *
     * @return string
     */
    public function getFilter(): string
    {
        return $this->builder->buildFullFilter($this->options);
    }

    /**
     * 获取搜索请求参数.
     *
     * @return array
     */
    public function getSearchParams(): array
    {
        if (is_null($this->options['limit'])) {
            $this->options['limit'] = (int) $this->getConfig('limit', 1000);
        }

        return $this->builder->parseSearchParams($this->options);
    }

    /* ------------------------------------------------------------------ */
    /* 查询执行                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 执行搜索.
     *
     * @param array|null $params 搜索参数(null 则使用查询器参数)
     *
     * @return SearchResult
     */
    public function doSearch(?array $params = null): SearchResult
    {
        if (is_null($params)) {
            $params = $this->getSearchParams();
        }

        $query = $params['q'] ?? null;
        unset($params['q']);

        $start = microtime(true);

        try {
            $result = $this->getIndex()->search($query, $params);
        } catch (Throwable $e) {
            throw $this->connection->convertException($e);
        }

        $this->lastResult = $result;

        $this->connection->log([
            'type'  => 'search',
            'index' => $this->getIndexName(),
            'q'     => $query,
            'params' => $params,
            'hits'  => $result->getHitsCount(),
            'time'  => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $result;
    }

    /**
     * 处理命中结果.
     *
     * @param array $hits 命中数据
     *
     * @return array
     */
    protected function processHits(array $hits): array
    {
        $without = $this->options['without'] ?? [];

        foreach ($hits as $key => $hit) {
            foreach ($without as $field) {
                unset($hit[$field]);
            }

            $hits[$key] = empty($this->model) ? $hit : $this->hydrate($hit);
        }

        return $hits;
    }

    /**
     * 把文档转换为模型对象.
     *
     * @param array $document 文档数据
     *
     * @return Model|array
     */
    protected function hydrate(array $document)
    {
        if (empty($this->model)) {
            return $document;
        }

        $class = $this->model;
        /** @var Model $model */
        $model = new $class();
        $model->exists(true)->data($document)->syncOrigin();

        return $model;
    }

    /**
     * 查询数据集.
     *
     * @return Collection
     */
    public function select(): Collection
    {
        if ($this->options['empty']) {
            return new Collection([]);
        }

        $result = $this->doSearch();

        return new Collection($this->processHits($result->getHits()));
    }

    /**
     * 查询单条数据.
     *
     * @param mixed $id 主键值
     *
     * @return Model|array|null
     */
    public function find($id = null)
    {
        if (!is_null($id)) {
            $this->where($this->getPk(), $id);
        }

        $key = $this->options['key'] ?? null;

        if (!is_null($key) && 1 === $this->countWhere() && empty($this->options['soft_delete'])) {
            try {
                $document = $this->getIndex()->getDocument($key);
            } catch (ApiException $e) {
                if (404 === $e->httpStatus) {
                    return null;
                }

                throw $this->connection->convertException($e);
            } catch (Throwable $e) {
                throw $this->connection->convertException($e);
            }

            if (empty($document)) {
                return null;
            }

            $result = $this->processHits([$document]);

            return $result[0];
        }

        $query = $this->newQuery()->limit(1);
        $rows  = $query->select();

        return $rows->isEmpty() ? null : $rows->first();
    }

    /**
     * 查询单条数据,不存在则抛异常.
     *
     * @param mixed $id 主键值
     *
     * @return Model|array
     */
    public function findOrFail($id = null)
    {
        $result = $this->find($id);

        if (is_null($result)) {
            throw new ModelNotFoundException($this->model ?: $this->name, $id);
        }

        return $result;
    }

    /**
     * 查询单条数据,不存在则返回空模型或空数组.
     *
     * @param mixed $id 主键值
     *
     * @return Model|array
     */
    public function findOrEmpty($id = null)
    {
        $result = $this->find($id);

        if (is_null($result)) {
            return empty($this->model) ? [] : (new $this->model);
        }

        return $result;
    }

    /**
     * 获取某个字段的值.
     *
     * @param string $field   字段名
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public function value(string $field, $default = null)
    {
        $row = $this->newQuery()->limit(1)->select()->first();

        if (is_null($row)) {
            return $default;
        }

        if ($row instanceof Model) {
            return $row->getData($field) ?? $default;
        }

        return $row[$field] ?? $default;
    }

    /**
     * 获取某一列的值.
     *
     * @param string      $field 字段名
     * @param string|null $key   索引键
     *
     * @return array
     */
    public function column(string $field, ?string $key = null): array
    {
        $rows   = $this->select()->toArray();
        $result = [];

        foreach ($rows as $row) {
            $value = $row[$field] ?? null;
            if (!is_null($key) && isset($row[$key])) {
                $result[$row[$key]] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * 统计数量.
     *
     * @return int
     */
    public function count(): int
    {
        if ($this->options['empty']) {
            return 0;
        }

        $params = $this->getSearchParams();
        $params['limit'] = 0;
        unset($params['offset'], $params['page'], $params['hitsPerPage']);

        $result = $this->doSearch($params);

        $total = $result->getEstimatedTotalHits();

        return (int) (is_null($total) ? ($result->getTotalHits() ?? 0) : $total);
    }

    /**
     * 是否存在数据.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * 分批获取数据.
     *
     * @param int           $count    每批数量
     * @param callable      $callback 回调
     * @param string|null   $order    排序字段
     *
     * @return bool
     */
    public function chunk(int $count, callable $callback, ?string $order = null): bool
    {
        $page = 0;

        while (true) {
            $query = $this->newQuery()->limit($page * $count, $count);
            if (!empty($order)) {
                $query->order($order);
            }

            $rows = $query->select();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $key => $item) {
                if (false === $callback($item, $key)) {
                    return true;
                }
            }

            if ($rows->count() < $count) {
                break;
            }

            $page++;
        }

        return true;
    }

    /**
     * 分页查询.
     *
     * @param int   $listRows 每页数量
     * @param bool  $simple   是否简洁模式
     * @param array $config   分页配置
     *
     * @return Paginator
     */
    public function paginate(int $listRows = 15, bool $simple = false, array $config = []): Paginator
    {
        $config = array_merge([
            'var_page' => 'page',
            'path'     => '/',
            'query'    => [],
            'fragment' => '',
        ], $config);

        $page  = (int) ($config['page'] ?? Paginator::getCurrentPage());
        $page  = max(1, $page);
        $rows  = $simple ? $listRows + 1 : $listRows;

        if (isset($this->options['empty']) && $this->options['empty']) {
            return Paginator::make(new Collection([]), $listRows, $page, 0, false, $config);
        }

        $result = $this->limit(($page - 1) * $listRows, $rows)->doSearch();

        $total = null;
        if (!$simple) {
            $total = $result->getEstimatedTotalHits();
            if (is_null($total)) {
                $total = $result->getTotalHits();
            }
        }

        $items = new Collection($this->processHits($result->getHits()));

        return Paginator::make($items, $listRows, $page, (int) $total, $simple, $config);
    }

    /**
     * 统计 where 条件数量.
     *
     * @return int
     */
    protected function countWhere(): int
    {
        $count = 0;
        foreach ($this->options['where'] as $items) {
            $count += count($items);
        }

        return $count;
    }

    /* ------------------------------------------------------------------ */
    /* 数据写入                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 插入单条数据.
     *
     * @param array $data    文档数据
     * @param bool  $replace 是否替换(全量覆盖)
     *
     * @return int
     */
    public function insert(array $data, bool $replace = false): int
    {
        if (empty($data)) {
            return 0;
        }

        $pk = $this->options['pk'] ?? null;

        try {
            $task = $replace
                ? $this->getIndex()->updateDocuments([$data], $pk)
                : $this->getIndex()->addDocuments([$data], $pk);
        } catch (Throwable $e) {
            throw $this->connection->convertException($e);
        }

        $this->lastTask = $this->connection->waitTask($task);

        return 1;
    }

    /**
     * 批量插入数据.
     *
     * @param array $dataSet   文档数据列表
     * @param bool  $replace   是否替换
     * @param int   $batchSize 每批数量
     *
     * @return int
     */
    public function insertAll(array $dataSet, bool $replace = false, int $batchSize = 1000): int
    {
        if (empty($dataSet)) {
            return 0;
        }

        $dataSet = array_values($dataSet);
        $pk      = $this->options['pk'] ?? null;
        $tasks   = [];
        $count   = 0;

        foreach (array_chunk($dataSet, max(1, $batchSize)) as $batch) {
            try {
                $tasks[] = $replace
                    ? $this->getIndex()->updateDocuments($batch, $pk)
                    : $this->getIndex()->addDocuments($batch, $pk);
            } catch (Throwable $e) {
                throw $this->connection->convertException($e);
            }

            $count += count($batch);
        }

        $this->lastTask = $this->connection->waitTasks($tasks);
        $this->connection->log([
            'type'  => 'insertAll',
            'index' => $this->getIndexName(),
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * 插入数据并返回主键值.
     *
     * @param array $data 文档数据
     *
     * @return string|int
     */
    public function insertGetId(array $data)
    {
        $pk = $this->getPk();

        if (!isset($data[$pk]) || '' === $data[$pk]) {
            $data[$pk] = $this->generateId();
        }

        $this->insert($data);

        return $data[$pk];
    }

    /**
     * 生成文档主键(UUID v4).
     *
     * @return string
     */
    protected function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * 更新数据.
     *
     * @param array|Closure $data 更新数据或回调
     *
     * @return int
     */
    public function update($data): int
    {
        if ($this->options['empty']) {
            return 0;
        }

        $pk = $this->getPk();

        if ($data instanceof Closure) {
            $documents = $this->fetchDocuments([]);
            $result    = [];
            foreach ($documents as $key => $document) {
                $new = $data($document, $key);
                if (empty($new) || !is_array($new)) {
                    continue;
                }
                $result[] = $new;
            }

            if (empty($result)) {
                return 0;
            }
        } else {
            $keys = $this->fetchKeys();
            if (empty($keys)) {
                return 0;
            }

            $result = [];
            foreach ($keys as $key) {
                $result[] = array_merge([$pk => $key], $data);
            }
        }

        try {
            $task = $this->getIndex()->updateDocuments($result, $pk);
        } catch (Throwable $e) {
            throw $this->connection->convertException($e);
        }

        $this->lastTask = $this->connection->waitTask($task);
        $this->connection->log([
            'type'  => 'update',
            'index' => $this->getIndexName(),
            'count' => count($result),
        ]);

        return count($result);
    }

    /**
     * 删除数据.
     *
     * @param bool $force 是否物理删除
     *
     * @return int
     */
    public function delete(bool $force = false): int
    {
        if ($this->options['empty']) {
            return 0;
        }

        $soft = $this->options['soft_delete'] ?? null;

        if (!empty($soft) && empty($soft['force']) && !$force) {
            $value = is_null($soft['value']) ? time() : $soft['value'];

            return $this->update([$soft['field'] => $value]);
        }

        if ('' === $this->getFilter()) {
            $stats  = $this->getIndex()->stats();
            $number = (int) ($stats['numberOfDocuments'] ?? 0);

            try {
                $task = $this->getIndex()->deleteAllDocuments();
            } catch (Throwable $e) {
                throw $this->connection->convertException($e);
            }

            $this->lastTask = $this->connection->waitTask($task);

            return $number;
        }

        $keys = $this->fetchKeys();
        if (empty($keys)) {
            return 0;
        }

        try {
            $task = $this->getIndex()->deleteDocuments($keys);
        } catch (Throwable $e) {
            throw $this->connection->convertException($e);
        }

        $this->lastTask = $this->connection->waitTask($task);
        $this->connection->log([
            'type'  => 'delete',
            'index' => $this->getIndexName(),
            'count' => count($keys),
        ]);

        return count($keys);
    }

    /**
     * 获取符合条件的主键值列表.
     *
     * @return array
     */
    protected function fetchKeys(): array
    {
        $pk    = $this->getPk();
        $keys  = [];

        foreach ($this->fetchDocuments([$pk]) as $document) {
            if (isset($document[$pk])) {
                $keys[] = $document[$pk];
            }
        }

        return $keys;
    }

    /**
     * 获取符合条件的文档列表.
     *
     * @param array $fields 返回字段,空表示返回全部字段
     *
     * @return array
     */
    protected function fetchDocuments(array $fields = []): array
    {
        if ($this->options['empty']) {
            return [];
        }

        $documents = [];

        // 用户显式指定了 limit 则只取一页
        if (!is_null($this->options['limit'])) {
            $params = $this->getSearchParams();
            if (!empty($fields)) {
                $params['attributesToRetrieve'] = $fields;
            }

            return $this->doSearch($params)->getHits();
        }

        $max   = (int) $this->getConfig('max_update_rows', 1000);
        $size  = (int) $this->getConfig('update_row_size', 1000);
        $offset = 0;

        while (true) {
            $params = $this->getSearchParams();
            if (!empty($fields)) {
                $params['attributesToRetrieve'] = $fields;
            }
            $params['limit']  = $size;
            $params['offset'] = $offset;
            unset($params['page'], $params['hitsPerPage']);

            $hits = $this->doSearch($params)->getHits();

            if (empty($hits)) {
                break;
            }

            foreach ($hits as $hit) {
                $documents[] = $hit;
                if ($max > 0 && count($documents) >= $max) {
                    break 2;
                }
            }

            $offset += count($hits);

            if (count($hits) < $size) {
                break;
            }
        }

        return $documents;
    }

    /**
     * 获取索引统计信息.
     *
     * @return array
     */
    public function stats(): array
    {
        return $this->getIndex()->stats();
    }

    /**
     * 获取索引设置.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->getIndex()->getSettings();
    }

    /**
     * 更新索引设置.
     *
     * @param array $settings 设置项
     *
     * @return array
     */
    public function updateSettings(array $settings): array
    {
        return $this->connection->updateSettings($this->name, $settings);
    }

    /**
     * 转发未定义的方法到索引对象.
     *
     * @param string $method 方法名
     * @param array  $args   参数
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->getIndex()->{$method}(...$args);
    }
}
