<?php
namespace bingher\db\meilisearch;

use ArrayAccess;
use bingher\db\meilisearch\exception\Exception;
use JsonSerializable;

/**
 * Meilisearch 模型基类.
 *
 * 用法与 ThinkORM 的 Model 保持一致：
 *      class Book extends Model { protected $index = 'books'; }
 *      Book::find(1);
 *      Book::where('status', 1)->select();
 */
class Model implements ArrayAccess, JsonSerializable
{
    /**
     * 索引名称（等价 ThinkORM 的表名）.
     *
     * @var string
     */
    protected $index = '';

    /**
     * 索引名称（index 别名）.
     *
     * @var string
     */
    protected $table = '';

    /**
     * 索引名称（不含前缀）.
     *
     * @var string
     */
    protected $name = '';

    /**
     * 连接标识.
     *
     * @var string
     */
    protected $connection = 'main';

    /**
     * 主键名.
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * 数据信息.
     *
     * @var array
     */
    protected $data = [];

    /**
     * 原始数据.
     *
     * @var array
     */
    protected $origin = [];

    /**
     * 是否存在于索引中.
     *
     * @var bool
     */
    protected $exists = false;

    /**
     * 是否自动写入时间戳.
     *
     * @var bool|string
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段.
     *
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段.
     *
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 软删除字段（为空表示不启用软删除）.
     *
     * @var string
     */
    protected $deleteTime = '';

    /**
     * 时间字段格式.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * 字段类型定义.
     *
     * @var array
     */
    protected $type = [];

    /**
     * JSON 字段.
     *
     * @var array
     */
    protected $json = [];

    /**
     * 隐藏字段.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * 显示字段.
     *
     * @var array
     */
    protected $visible = [];

    /**
     * 追加字段.
     *
     * @var array
     */
    protected $append = [];

    /**
     * 模型事件.
     *
     * @var array
     */
    protected static $event = [];

    /**
     * 架构函数.
     *
     * @param array $data 数据
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->data($data)->syncOrigin();
        }
    }

    /* ------------------------------------------------------------------ */
    /* 基础信息                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 获取索引名称.
     *
     * @return string
     */
    public function getIndex(): string
    {
        $name = $this->index ?: $this->table ?: $this->name;

        if ('' === $name) {
            $class = static::class;
            $name  = substr($class, false === strrpos($class, '\\') ? 0 : strrpos($class, '\\') + 1);
            $name  = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        }

        return $name;
    }

    /**
     * 获取表名（getIndex 别名）.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->getIndex();
    }

    /**
     * 获取连接标识.
     *
     * @return string
     */
    public function getConnectionName(): string
    {
        return $this->connection;
    }

    /**
     * 获取主键名.
     *
     * @return string
     */
    public function getPk(): string
    {
        return $this->pk;
    }

    /**
     * 获取软删除字段名.
     *
     * @return string
     */
    public function getDeleteTime(): string
    {
        return $this->deleteTime;
    }

    /**
     * 获取连接对象.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return Client::connect($this->connection);
    }

    /**
     * 创建查询器.
     *
     * @param string|null $index 索引名
     *
     * @return Query
     */
    public function db(?string $index = null): Query
    {
        return $this->getConnection()->table($index ?: $this->getIndex())->setModel(static::class);
    }

    /**
     * 是否存在于索引中.
     *
     * @param bool $exists 是否存在
     *
     * @return $this
     */
    public function exists(bool $exists = true)
    {
        $this->exists = $exists;

        return $this;
    }

    /**
     * 获取是否存在于索引中.
     *
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->exists;
    }

    /**
     * 是否已软删除.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        return !empty($this->deleteTime) && !empty($this->getData($this->deleteTime));
    }

    /* ------------------------------------------------------------------ */
    /* 属性操作                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 批量设置数据.
     *
     * @param array $data  数据
     * @param bool  $merge 是否合并
     *
     * @return $this
     */
    public function data(array $data, bool $merge = true)
    {
        $this->data = $merge ? array_merge($this->data, $data) : $data;

        return $this;
    }

    /**
     * 同步原始数据.
     *
     * @return $this
     */
    public function syncOrigin()
    {
        $this->origin = $this->data;

        return $this;
    }

    /**
     * 获取全部数据.
     *
     * @param string|null $name 字段名
     *
     * @return mixed
     */
    public function getData(?string $name = null)
    {
        if (is_null($name)) {
            return $this->data;
        }

        return $this->data[$name] ?? null;
    }

    /**
     * 获取原始数据.
     *
     * @param string|null $name 字段名
     *
     * @return mixed
     */
    public function getOrigin(?string $name = null)
    {
        if (is_null($name)) {
            return $this->origin;
        }

        return $this->origin[$name] ?? null;
    }

    /**
     * 获取变更数据.
     *
     * @return array
     */
    public function getChangedData(): array
    {
        $changed = [];

        foreach ($this->data as $key => $value) {
            if (!array_key_exists($key, $this->origin) || $this->origin[$key] !== $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }

    /**
     * 获取主键值.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getData($this->getPk());
    }

    /**
     * 设置属性值.
     *
     * @param string $name  字段名
     * @param mixed  $value 值
     *
     * @return $this
     */
    public function setAttr(string $name, $value)
    {
        $method = 'set' . $this->parseName($name) . 'Attr';

        if (method_exists($this, $method)) {
            $value = $this->{$method}($value, $this->data);
        }

        if (in_array($name, $this->json, true) && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (in_array($name, $this->json, true) && is_null($value)) {
            $value = null;
        }

        $this->data[$name] = $value;

        return $this;
    }

    /**
     * 获取属性值.
     *
     * @param string $name    字段名
     * @param mixed  $default 默认值
     *
     * @return mixed
     */
    public function getAttr(string $name, $default = null)
    {
        $value = $this->getData($name);

        $method = 'get' . $this->parseName($name) . 'Attr';
        if (method_exists($this, $method)) {
            $value = $this->{$method}($value, $this->data);
        }

        if (is_null($value)) {
            return $default;
        }

        if (in_array($name, $this->json, true) && is_string($value)) {
            $decode = json_decode($value, true);

            return is_null($decode) ? $value : $decode;
        }

        return $this->convertType($name, $value);
    }

    /**
     * 类型转换.
     *
     * @param string $name  字段名
     * @param mixed  $value 值
     *
     * @return mixed
     */
    protected function convertType(string $name, $value)
    {
        $type = $this->type[$name] ?? '';

        switch ($type) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'string':
                return (string) $value;
            case 'array':
                return is_array($value) ? $value : (array) json_decode((string) $value, true);
            case 'json':
                return is_string($value) ? json_decode($value, true) : $value;
            case 'object':
                return is_string($value) ? json_decode($value) : (object) $value;
            case 'datetime':
                if (is_numeric($value)) {
                    return date($this->dateFormat, (int) $value);
                }

                return $value;
            default:
                return $value;
        }
    }

    /**
     * 下划线转驼峰（首字母大写）.
     *
     * @param string $name 字段名
     *
     * @return string
     */
    protected function parseName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    /**
     * 指定隐藏字段.
     *
     * @param array $fields 字段名
     *
     * @return $this
     */
    public function hidden(array $fields)
    {
        $this->hidden = $fields;

        return $this;
    }

    /**
     * 指定显示字段.
     *
     * @param array $fields 字段名
     *
     * @return $this
     */
    public function visible(array $fields)
    {
        $this->visible = $fields;

        return $this;
    }

    /**
     * 指定追加字段.
     *
     * @param array $fields 字段名
     *
     * @return $this
     */
    public function append(array $fields)
    {
        $this->append = $fields;

        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* 查询与写入                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * 新增记录.
     *
     * @param array $data       数据
     * @param array $allowField 允许字段
     *
     * @return static
     */
    public static function create(array $data, array $allowField = []): self
    {
        $model = new static();
        $model->save(array_merge($data, []), $allowField);

        return $model;
    }

    /**
     * 保存当前数据对象.
     *
     * @param array $data       数据
     * @param array $allowField 允许字段
     *
     * @return bool
     */
    public function save(array $data = [], array $allowField = []): bool
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (!empty($allowField) && !in_array($key, $allowField, true)) {
                    continue;
                }

                $this->setAttr($key, $value);
            }
        }

        $pk  = $this->getPk();
        $key = $this->getKey();

        $this->trigger('before_write', $this);

        if ($this->isExists()) {
            $this->trigger('before_update', $this);
            $this->writeTimestamp('update');

            $changed = $this->getChangedData();
            if (!empty($changed)) {
                $this->db()->where($pk, $key)->update($changed);
            }
            $this->trigger('after_update', $this);
        } else {
            $this->trigger('before_insert', $this);
            $this->writeTimestamp('insert');

            if (empty($this->getData($pk))) {
                $this->setAttr($pk, $this->generateId());
            }

            $this->db()->insert($this->getData());
            $this->exists(true);
            $this->trigger('after_insert', $this);
        }

        $this->syncOrigin();

        return true;
    }

    /**
     * 更新数据（静态方法,与 ThinkORM 保持一致）.
     *
     * 主键可以放在 $data 中，也可以通过 $where 指定更新条件。
     *
     * @param array $data       数据
     * @param array $where      更新条件
     * @param array $allowField 允许字段
     *
     * @return static
     */
    public static function update(array $data, array $where = [], array $allowField = [])
    {
        $model = new static();
        $pk    = $model->getPk();
        $key   = $data[$pk] ?? null;

        $query = $model->db();

        if (!empty($where)) {
            $query->where($where);
        } elseif (!is_null($key)) {
            $query->where($pk, $key);
        } else {
            throw new Exception('更新数据缺少主键或更新条件');
        }

        if (!empty($allowField)) {
            $data = array_intersect_key($data, array_flip($allowField));
        }

        $query->update($data);

        return $model->data($data)->exists(true)->syncOrigin();
    }

    /**
     * 删除当前数据.
     *
     * @param bool $force 是否物理删除
     *
     * @return bool
     */
    public function delete(bool $force = false): bool
    {
        $key = $this->getKey();

        if (empty($key)) {
            return false;
        }

        $this->trigger('before_delete', $this);

        $query = $this->db()->where($this->getPk(), $key);

        if (empty($this->deleteTime) || $force) {
            $query->force(true)->delete(true);
        } else {
            $query->force(false)->delete();
        }

        $this->exists(false);
        $this->trigger('after_delete', $this);

        return true;
    }

    /**
     * 批量删除.
     *
     * @param mixed $data  主键值或主键列表
     * @param bool  $force 是否物理删除
     *
     * @return int
     */
    public static function destroy($data, bool $force = false): int
    {
        $model = new static();
        $pk    = $model->getPk();
        $ids   = array_values(array_filter(is_array($data) ? $data : [$data], function ($value) {
            return !is_null($value) && '' !== $value;
        }));

        if (empty($ids)) {
            return 0;
        }

        return $model->db()->force($force)->whereIn($pk, $ids)->delete($force);
    }

    /**
     * 恢复软删除数据.
     *
     * @return bool
     */
    public function restore(): bool
    {
        if (empty($this->deleteTime)) {
            return false;
        }

        $key = $this->getKey();
        if (empty($key)) {
            return false;
        }

        $this->db()->withTrashed()->where($this->getPk(), $key)->update([$this->deleteTime => null]);
        $this->setAttr($this->deleteTime, null);
        $this->syncOrigin();

        return true;
    }

    /**
     * 写入时间戳.
     *
     * @param string $type 类型:insert|update
     *
     * @return void
     */
    protected function writeTimestamp(string $type): void
    {
        if (true !== $this->autoWriteTimestamp && 'datetime' !== $this->autoWriteTimestamp) {
            return;
        }

        $field = 'insert' === $type ? $this->createTime : $this->updateTime;

        if ('' === $field) {
            return;
        }

        if ('insert' === $type && !empty($this->getData($this->createTime))) {
            return;
        }

        $this->setAttr($field, $this->formatTimestamp());
    }

    /**
     * 生成时间戳.
     *
     * @return string|int
     */
    protected function formatTimestamp()
    {
        return 'datetime' === $this->autoWriteTimestamp
            ? date($this->dateFormat)
            : time();
    }

    /**
     * 生成主键(UUID v4).
     *
     * @return string
     */
    protected function generateId(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /* ------------------------------------------------------------------ */
    /* 模型事件                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * 注册模型事件.
     *
     * @param callable|array $callback 回调
     * @param bool           $override 是否覆盖
     *
     * @return void
     */
    public static function event(string $event, $callback, bool $override = false): void
    {
        $class = static::class;

        if ($override) {
            self::$event[$class][$event] = [];
        }

        self::$event[$class][$event][] = $callback;
    }

    /**
     * 触发模型事件.
     *
     * @param string $event 事件名
     *
     * @return bool
     */
    public function trigger(string $event): bool
    {
        $class = static::class;

        foreach (self::$event[$class][$event] ?? [] as $callback) {
            if (is_callable($callback) && false === $callback($this)) {
                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* 输出与魔术方法                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * 转换为数组.
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->data;

        foreach ($this->append as $field) {
            $data[$field] = $this->getAttr($field);
        }

        if (!empty($this->visible)) {
            $data = array_intersect_key($data, array_flip($this->visible));
        }

        if (!empty($this->hidden)) {
            $data = array_diff_key($data, array_flip($this->hidden));
        }

        foreach ($data as $field => $value) {
            $data[$field] = $this->getAttr((string) $field, $value);
        }

        return $data;
    }

    /**
     * 转换为 JSON.
     *
     * @param int $options JSON 参数
     *
     * @return string
     */
    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * 序列化为 JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * 字符串输出.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    public function __get($name)
    {
        return $this->getAttr((string) $name);
    }

    public function __set($name, $value)
    {
        $this->setAttr((string) $name, $value);
    }

    public function __isset($name)
    {
        return !is_null($this->getAttr((string) $name));
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function offsetExists($offset): bool
    {
        return !is_null($this->getAttr((string) $offset));
    }

    public function offsetGet($offset): mixed
    {
        return $this->getAttr((string) $offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->setAttr((string) $offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * 调用查询器方法（实例）.
     *
     * @param string $method 方法名
     * @param array  $args   参数
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->db()->{$method}(...$args);
    }

    /**
     * 调用查询器方法（静态）.
     *
     * @param string $method 方法名
     * @param array  $args   参数
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        return (new static())->db()->{$method}(...$args);
    }
}
