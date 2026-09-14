<?php
namespace bingher\db\meilisearch;

use bingher\db\meilisearch\exception\Exception;

/**
 * Meilisearch 连接管理器.
 *
 * 角色等价于 ThinkORM 的 DbManager，负责配置解析与连接池管理。
 */
class Manager
{
    /**
     * 管理器单例.
     *
     * @var static|null
     */
    protected static $instance;

    /**
     * 配置信息.
     *
     * @var array
     */
    protected $config = [];

    /**
     * 连接池.
     *
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * 查询类名.
     *
     * @var string
     */
    protected $queryClass = Query::class;

    /**
     * 模型类名.
     *
     * @var string
     */
    protected $modelClass = Model::class;

    /**
     * 默认配置.
     *
     * @var array
     */
    protected $defaults = [
        'default'         => 'main',
        'prefix'          => '',
        'wait'            => true,
        'task_timeout'    => 5000,
        'task_interval'   => 50,
        'limit'           => 1000,
        'max_update_rows' => 1000,
        'update_row_size' => 1000,
    ];

    /**
     * 需要合并到每个连接的全局配置.
     *
     * @var array
     */
    protected $globalKeys = [
        'prefix',
        'wait',
        'task_timeout',
        'task_interval',
        'limit',
        'max_update_rows',
        'update_row_size',
    ];

    /**
     * 架构函数.
     *
     * @param array|null $config 配置
     */
    public function __construct(?array $config = null)
    {
        $this->config = array_merge($this->defaults, is_null($config) ? $this->loadConfig() : $config);
    }

    /**
     * 获取管理器单例.
     *
     * @param array|null $config 配置
     *
     * @return static
     */
    public static function instance(?array $config = null)
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($config);
        } elseif (!is_null($config)) {
            self::$instance->setConfig($config);
        }

        return self::$instance;
    }

    /**
     * 设置管理器单例.
     *
     * @param static|null $manager 管理器
     *
     * @return void
     */
    public static function setInstance(?self $manager): void
    {
        self::$instance = $manager;
    }

    /**
     * 读取配置.
     *
     * 优先使用框架 config('meilisearch') 配置。
     *
     * @return array
     */
    protected function loadConfig(): array
    {
        if (function_exists('config')) {
            $config = config('meilisearch');

            if (is_array($config)) {
                return $config;
            }
        }

        return [];
    }

    /**
     * 设置配置.
     *
     * @param array $config 配置
     *
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    /**
     * 获取配置.
     *
     * @param string $name    配置名
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public function getConfig(string $name = '', $default = null)
    {
        if ('' === $name) {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    /**
     * 设置查询类名.
     *
     * @param string $class 类名
     *
     * @return $this
     */
    public function setQueryClass(string $class)
    {
        $this->queryClass = $class;

        return $this;
    }

    /**
     * 获取查询类名.
     *
     * @return string
     */
    public function getQueryClass(): string
    {
        return $this->queryClass;
    }

    /**
     * 设置模型类名.
     *
     * @param string $class 类名
     *
     * @return $this
     */
    public function setModelClass(string $class)
    {
        $this->modelClass = $class;

        return $this;
    }

    /**
     * 获取模型类名.
     *
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * 获取连接对象.
     *
     * @param string|null $name  连接名
     * @param bool        $force 是否强制重连
     *
     * @return Connection
     */
    public function connect(?string $name = null, bool $force = false): Connection
    {
        $name = $name ?: (string) $this->getConfig('default');

        if ($force || !isset($this->connections[$name])) {
            $this->connections[$name] = $this->createConnection($name);
        }

        return $this->connections[$name];
    }

    /**
     * 创建连接对象.
     *
     * @param string $name 连接名
     *
     * @return Connection
     */
    protected function createConnection(string $name): Connection
    {
        $connections = $this->getConfig('connections', []);

        // 兼容单连接写法：顶层直接配置 host
        if (empty($connections) && $this->getConfig('host')) {
            $connections = [$this->getConfig('default') => $this->getConfig()];
        }

        if (!isset($connections[$name])) {
            throw new Exception('未定义的 Meilisearch 连接配置:' . $name);
        }

        $config = $connections[$name];

        foreach ($this->globalKeys as $key) {
            if (!array_key_exists($key, $config) && !is_null($this->getConfig($key))) {
                $config[$key] = $this->getConfig($key);
            }
        }

        $config['name'] = $name;

        $connection = new Connection($config);
        $connection->setQueryClass($this->queryClass);

        return $connection;
    }

    /**
     * 获取默认连接.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connect();
    }

    /**
     * 指定索引返回查询器.
     *
     * @param string $name 索引名
     *
     * @return Query
     */
    public function table(string $name): Query
    {
        return $this->connect()->table($name);
    }

    /**
     * 指定索引返回查询器（含前缀）.
     *
     * @param string $name 索引名
     *
     * @return Query
     */
    public function name(string $name): Query
    {
        return $this->connect()->name($name);
    }

    /**
     * 转发未定义方法到默认连接.
     *
     * @param string $method 方法名
     * @param array  $args   参数
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->connect()->{$method}(...$args);
    }
}
