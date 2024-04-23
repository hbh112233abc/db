<?php
declare(strict_types=1);

namespace bingher\db\builder;

use Exception;
use think\db\Builder;
use think\db\BaseQuery as Query;
use think\db\Raw;

/**
 * 达梦数据库驱动
 */
class DM extends Builder
{
    protected $selectSql = 'SELECT  %DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER% %LIMIT%%COMMENT%';

    /**
     * limit分析
     * @access protected
     * @param  Query $query 查询对象
     * @param  mixed $limit
     * @return string
     */
    // protected function parseLimit(Query $query, string $limit): string
    // {
    //     $limitStr = '';

    //     if (!empty($limit)) {
    //         $limit = explode(',', $limit);

    //         if (count($limit) > 1) {
    //             $limitStr = "(numrow>" . $limit[0] . ") AND (numrow<=" . ($limit[0] + $limit[1]) . ")";
    //         } else {
    //             $limitStr = "(numrow>0 AND numrow<=" . $limit[0] . ")";
    //         }

    //     }

    //     return $limitStr ? ' WHERE ' . $limitStr : '';
    // }

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
     * 字段和表名处理
     * @access public
     *
     * @param Query $query  查询对象
     * @param mixed $key    字段名
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
}
