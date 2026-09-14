<?php
namespace bingher\db\meilisearch\exception;

/**
 * 模型数据不存在异常.
 */
class ModelNotFoundException extends Exception
{
    /**
     * 架构函数.
     *
     * @param string $model 模型类名
     * @param mixed  $id    主键值
     */
    public function __construct(string $model, $id = '')
    {
        parent::__construct(sprintf('模型数据不存在:[%s] %s', $model, is_scalar($id) ? (string) $id : json_encode($id, JSON_UNESCAPED_UNICODE)));
    }
}
