<?php
namespace bingher\db\query;

use think\db\Query;
use think\helper\Str;

class DM extends Query
{
    /**
     * 得到当前或者指定名称的数据表.
     *
     * @param string $name 不含前缀的数据表名字
     *
     * @return mixed
     */
    public function getTable(string $name = '')
    {
        if (empty($name) && isset($this->options['table'])) {
            return $this->options['table'];
        }

        $name = $name ?: $this->name;
        $schema = strtoupper($this->connection->getConfig('database'));
        return $schema.'.'.$this->prefix . Str::snake($name);
    }


}
