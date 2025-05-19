<?php
namespace bingher\db\query;

use think\db\Query;
use think\helper\Str;
use think\db\ConnectionInterface;

class DM extends Query
{
    /**
     * 架构函数.
     *
     * @param ConnectionInterface $connection 数据库连接对象
     */
    function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection);
        $this->options['schema'] = strtoupper($connection->getConfig('database'));
    }

    /**
     * 表名添加schema
     *
     * @param string $tableName
     * @return string
     */
    public function fitTable(string $tableName)
    {
        if(str_contains($tableName,'.')){
            return $tableName;
        }
        return $this->options['schema'].'.'.$tableName;
    }

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
        return $this->options['schema'].'.'.$this->prefix . Str::snake($this->name) . $this->suffix;
    }
}
