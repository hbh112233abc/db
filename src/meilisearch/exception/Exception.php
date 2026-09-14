<?php
namespace bingher\db\meilisearch\exception;

use Throwable;

/**
 * Meilisearch 操作异常.
 */
class Exception extends \Exception
{
    /**
     * 异常附加数据.
     *
     * @var array
     */
    protected $data = [];

    /**
     * 架构函数.
     *
     * @param string         $message  异常信息
     * @param int            $code     异常代码
     * @param Throwable|null $previous 前任异常
     * @param array          $data     附加数据
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $data = [])
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * 获取附加数据.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 设置附加数据.
     *
     * @param array $data 附加数据
     *
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }
}
