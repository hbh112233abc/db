<?php
namespace bingher\db\meilisearch;

/**
 * Meilisearch 客户端门面.
 *
 * 用法等价于 ThinkORM 的 Db 门面：
 *      use bingher\db\meilisearch\Client as Meili;
 *
 *      Meili::table('books')->where('status', 1)->select();
 *      Meili::connect('main')->table('books')->search('php')->select();
 */
class Client
{
    /**
     * 设置配置.
     *
     * @param array $config 配置
     *
     * @return void
     */
    public static function setConfig(array $config): void
    {
        Manager::instance()->setConfig($config);
    }

    /**
     * 获取配置.
     *
     * @param string $name    配置名
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public static function getConfig(string $name = '', $default = null)
    {
        return Manager::instance()->getConfig($name, $default);
    }

    /**
     * 获取连接对象.
     *
     * @param string|null $name  连接名
     * @param bool        $force 是否强制重连
     *
     * @return Connection
     */
    public static function connect(?string $name = null, bool $force = false): Connection
    {
        return Manager::instance()->connect($name, $force);
    }

    /**
     * 指定索引返回查询器.
     *
     * @param string $name 索引名
     *
     * @return Query
     */
    public static function table(string $name): Query
    {
        return Manager::instance()->table($name);
    }

    /**
     * 指定索引返回查询器（含前缀）.
     *
     * @param string $name 索引名
     *
     * @return Query
     */
    public static function name(string $name): Query
    {
        return Manager::instance()->name($name);
    }

    /**
     * 设置自定义查询类.
     *
     * @param string $class 类名
     *
     * @return void
     */
    public static function setQuery(string $class): void
    {
        Manager::instance()->setQueryClass($class);
    }

    /**
     * 设置自定义模型类.
     *
     * @param string $class 类名
     *
     * @return void
     */
    public static function setModel(string $class): void
    {
        Manager::instance()->setModelClass($class);
    }

    /**
     * 注册请求日志监听.
     *
     * @param callable $callback 回调
     *
     * @return void
     */
    public static function listen(callable $callback): void
    {
        Manager::instance()->connect()->listen($callback);
    }

    /**
     * 获取请求日志.
     *
     * @param bool $clear 是否清空
     *
     * @return array
     */
    public static function getLog(bool $clear = false): array
    {
        return Manager::instance()->connect()->getLog($clear);
    }

    /**
     * 转发静态方法到默认连接.
     *
     * @param string $method 方法名
     * @param array  $args   参数
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return Manager::instance()->connect()->{$method}(...$args);
    }
}
