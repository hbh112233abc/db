<?php
namespace bingher\db\meilisearch;

use bingher\db\meilisearch\exception\Exception;
use Meilisearch\Client as MeiliClient;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Exceptions\TimeOutException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * Meilisearch 连接对象.
 *
 * 负责维护官方 SDK 客户端实例、索引名前缀、任务等待、请求日志等，
 * 角色等价于 ThinkORM 的 Connection。
 */
class Connection
{
    /**
     * 连接配置.
     *
     * @var array
     */
    protected $config = [];

    /**
     * 官方 SDK 客户端.
     *
     * @var MeiliClient
     */
    protected $client;

    /**
     * 索引名前缀.
     *
     * @var string
     */
    protected $prefix = '';

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
     * 请求日志监听回调.
     *
     * @var array
     */
    protected $listen = [];

    /**
     * 请求日志.
     *
     * @var array
     */
    protected $log = [];

    /**
     * 架构函数.
     *
     * @param array $config 连接配置
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->prefix = (string) ($config['prefix'] ?? '');
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
        if ('' === $name) {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    /**
     * 获取索引名前缀.
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * 设置索引名前缀.
     *
     * @param string $prefix 前缀
     *
     * @return $this
     */
    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
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
     * 获取官方 SDK 客户端.
     *
     * @return MeiliClient
     */
    public function getClient(): MeiliClient
    {
        if (empty($this->client)) {
            if (!class_exists(MeiliClient::class)) {
                throw new Exception('未安装 Meilisearch SDK,使用 Meilisearch 客户端前请先执行:composer require meilisearch/meilisearch-php guzzlehttp/guzzle');
            }

            $host = (string) $this->getConfig('host', 'http://127.0.0.1:7700');
            if ('' === $host) {
                throw new Exception('Meilisearch 连接缺少 host 配置');
            }

            $apiKey = (string) $this->getConfig('api_key', '');
            $options = [
                'http_client'     => $this->getConfig('http_client'),
                'request_factory' => $this->getConfig('request_factory'),
                'stream_factory'  => $this->getConfig('stream_factory'),
                'client_agents'   => $this->getConfig('client_agents', []),
            ];
            $this->client = $this->createClient($host, $apiKey, $options);
        }

        return $this->client;
    }

    /**
     * 创建官方 SDK 客户端.
     *
     * @param string $host    服务地址
     * @param string $apiKey  API Key
     * @param array  $options 扩展选项
     *
     * @return MeiliClient
     */
    protected function createClient(string $host, string $apiKey, array $options): MeiliClient
    {
        $httpClient     = $options['http_client'] ?? null;
        $requestFactory = $options['request_factory'] ?? null;
        $streamFactory  = $options['stream_factory'] ?? null;
        $clientAgents   = $options['client_agents'] ?? [];

        if (is_string($clientAgents)) {
            $clientAgents = [$clientAgents];
        }

        if (!$httpClient instanceof ClientInterface) {
            $httpClient = null;
        }

        if (!$requestFactory instanceof RequestFactoryInterface) {
            $requestFactory = null;
        }

        if (!$streamFactory instanceof StreamFactoryInterface) {
            $streamFactory = null;
        }

        return new MeiliClient($host, $apiKey, $httpClient, $requestFactory, array_values((array) $clientAgents), $streamFactory);
    }

    /**
     * 获取带前缀的索引名称.
     *
     * @param string $name 索引名
     *
     * @return string
     */
    public function getIndexName(string $name): string
    {
        if ('' === $this->prefix || '' === $name || str_starts_with($name, $this->prefix)) {
            return $name;
        }

        return $this->prefix . $name;
    }

    /**
     * 获取索引对象.
     *
     * @param string $name 索引名
     *
     * @return Indexes
     */
    public function getIndex(string $name): Indexes
    {
        if ('' === trim($name)) {
            throw new Exception('Meilisearch 索引名称不能为空');
        }

        return $this->getClient()->index($this->getIndexName($name));
    }

    /**
     * 指定索引（含前缀）返回查询器.
     *
     * @param string $name 索引名
     *
     * @return Query
     */
    public function table(string $name): Query
    {
        $class = $this->queryClass;
        /** @var Query $query */
        $query = new $class($this, $name);

        return $query;
    }

    /**
     * 指定索引（不含前缀）返回查询器.
     *
     * @param string $name 索引名
     *
     * @return Query
     */
    public function name(string $name): Query
    {
        return $this->table($name)->prefix($this->prefix);
    }

    /**
     * 获取索引列表.
     *
     * @return array
     */
    public function getIndexes(): array
    {
        return $this->getClient()->getIndexes()->getResults();
    }

    /**
     * 判断索引是否存在.
     *
     * @param string $name 索引名
     *
     * @return bool
     */
    public function indexExists(string $name): bool
    {
        try {
            $this->getClient()->getIndex($this->getIndexName($name));

            return true;
        } catch (ApiException $e) {
            if (404 === $e->httpStatus) {
                return false;
            }

            throw $this->convertException($e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }
    }

    /**
     * 创建索引.
     *
     * @param string $name    索引名
     * @param array  $options 索引选项(primaryKey)
     *
     * @return array
     */
    public function createIndex(string $name, array $options = []): array
    {
        $task = $this->getClient()->createIndex($this->getIndexName($name), $options);

        return $this->waitTask($task);
    }

    /**
     * 删除索引.
     *
     * @param string $name 索引名
     *
     * @return array
     */
    public function deleteIndex(string $name): array
    {
        $task = $this->getClient()->deleteIndex($this->getIndexName($name));

        return $this->waitTask($task);
    }

    /**
     * 获取索引设置.
     *
     * @param string $name 索引名
     *
     * @return array
     */
    public function getSettings(string $name): array
    {
        return $this->getIndex($name)->getSettings();
    }

    /**
     * 更新索引设置.
     *
     * @param string $name     索引名
     * @param array  $settings 设置项
     *
     * @return array
     */
    public function updateSettings(string $name, array $settings): array
    {
        $task = $this->getIndex($name)->updateSettings($settings);

        return $this->waitTask($task);
    }

    /**
     * 获取索引统计信息.
     *
     * @param string $name 索引名
     *
     * @return array
     */
    public function getStats(string $name): array
    {
        return $this->getIndex($name)->stats();
    }

    /**
     * 服务健康检查.
     *
     * @return array
     */
    public function health(): array
    {
        return $this->getClient()->health();
    }

    /**
     * 服务版本信息.
     *
     * @return array
     */
    public function version(): array
    {
        return $this->getClient()->version();
    }

    /**
     * 等待任务执行完成.
     *
     * @param mixed $task 任务信息
     *
     * @return array
     */
    public function waitTask($task): array
    {
        $task = $this->normalizeTask($task);

        if (empty($task) || empty($task['taskUid'])) {
            return $task;
        }

        if (!$this->getConfig('wait', true)) {
            return $task;
        }

        $timeout  = (int) $this->getConfig('task_timeout', 5000);
        $interval = (int) $this->getConfig('task_interval', 50);

        try {
            $result = $this->getClient()->waitForTask($task['taskUid'], $timeout, $interval);
        } catch (TimeOutException $e) {
            throw new Exception('等待 Meilisearch 任务超时:' . $task['taskUid'], 0, $e);
        } catch (Throwable $e) {
            throw $this->convertException($e);
        }

        if ('failed' === ($result['status'] ?? '')) {
            $code = $result['error']['code'] ?? '';

            // 索引已存在视为成功,与 ORM 的建表语义保持一致
            if (!in_array($code, ['index_already_exists'], true)) {
                throw new Exception('Meilisearch 任务执行失败:' . json_encode($result, JSON_UNESCAPED_UNICODE), 0, null, $result);
            }
        }

        return $result;
    }

    /**
     * 等待多个任务执行完成.
     *
     * @param array $tasks 任务列表
     *
     * @return array
     */
    public function waitTasks(array $tasks): array
    {
        $results = [];
        foreach ($tasks as $task) {
            $results[] = $this->waitTask($task);
        }

        return $results;
    }

    /**
     * 统一任务返回结构.
     *
     * @param mixed $task 任务信息
     *
     * @return array
     */
    protected function normalizeTask($task): array
    {
        if (is_array($task)) {
            return $task;
        }

        if (is_object($task) && method_exists($task, 'toArray')) {
            return (array) $task->toArray();
        }

        return (array) $task;
    }

    /**
     * 注册请求日志监听.
     *
     * @param callable $callback 回调
     *
     * @return void
     */
    public function listen(callable $callback): void
    {
        $this->listen[] = $callback;
    }

    /**
     * 记录请求日志.
     *
     * @param array $info 日志信息
     *
     * @return void
     */
    public function log(array $info): void
    {
        $info['time'] = $info['time'] ?? date('Y-m-d H:i:s');

        $this->log[] = $info;

        foreach ($this->listen as $callback) {
            call_user_func($callback, $info);
        }
    }

    /**
     * 获取请求日志.
     *
     * @param bool $clear 是否清空
     *
     * @return array
     */
    public function getLog(bool $clear = false): array
    {
        $log = $this->log;

        if ($clear) {
            $this->log = [];
        }

        return $log;
    }

    /**
     * 格式化异常.
     *
     * 把 SDK 抛出的异常补充常见的中文排查提示。
     *
     * @param Throwable $e 原始异常
     *
     * @return Throwable
     */
    public function convertException(Throwable $e): Throwable
    {
        if (!$e instanceof ApiException && !$e instanceof CommunicationException) {
            return $e;
        }

        $hint = $this->getExceptionHint($e);
        if ('' === $hint) {
            return $e;
        }

        return new Exception($e->getMessage() . PHP_EOL . '提示:' . $hint, (int) $e->getCode(), $e);
    }

    /**
     * 获取异常提示.
     *
     * @param Throwable $e 原始异常
     *
     * @return string
     */
    protected function getExceptionHint(Throwable $e): string
    {
        if ($e instanceof CommunicationException) {
            return '无法连接 Meilisearch 服务,请检查 host 配置与服务状态';
        }

        $message = $e->getMessage();

        if (str_contains($message, 'filterable')) {
            return '该字段未设置为 filterableAttributes,请先调用 updateSettings 配置可过滤字段';
        }

        if (str_contains($message, 'sortable')) {
            return '该字段未设置为 sortableAttributes,请先调用 updateSettings 配置可排序字段';
        }

        if (str_contains($message, 'searchable')) {
            return '该字段未设置为 searchableAttributes,请先调用 updateSettings 配置可搜索字段';
        }

        if (str_contains($message, 'not found') && 404 === (int) $e->getCode()) {
            return '索引或文档不存在,请先创建索引并导入数据';
        }

        return '';
    }

    /**
     * 转发未定义的方法到官方 SDK 客户端.
     *
     * @param string $method 方法名
     * @param array  $args   参数
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->getClient()->{$method}(...$args);
    }
}
