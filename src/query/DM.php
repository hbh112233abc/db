<?php
namespace bingher\db\query;

use think\db\Query;
use think\helper\Str;

class DM extends Query
{
    /**
     * 得到当前或者指定名称的数据表.
     * @param bool $alias 是否返回数据表别名
     *
     * @return string|array|Raw
     */
    public function getTable(bool $alias = false)
    {
        if (isset($this->options['table'])) {
            $table =  $this->options['table'];
            if ($alias && is_string($table) && !empty($this->options['alias'][$table])) {
                return $this->options['alias'][$table];
            }
            return $table;
        }
        $schema = strtoupper($this->connection->getConfig('database'));
        return $schema.'.'.$this->prefix . Str::snake($this->name) . $this->suffix;
    }
}
