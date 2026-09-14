<?php
namespace bingher\db\meilisearch;

use think\paginator\driver\Bootstrap;

/**
 * Meilisearch 分页器.
 *
 * 复用 ThinkPHP 的 Bootstrap 分页驱动，
 * 因此可以像 ThinkORM 一样在模板中直接输出分页 HTML。
 */
class Paginator extends Bootstrap
{
    /**
     * 创建分页对象.
     *
     * 父类固定返回 Bootstrap 实例，此处重写以保证返回当前类实例。
     *
     * @param mixed  $items       数据集
     * @param int    $listRows    每页数量
     * @param int    $currentPage 当前页
     * @param int    $total       数据总数
     * @param bool   $simple      简洁模式
     * @param array  $options     分页配置
     *
     * @return static
     */
    public static function make($items, int $listRows, int $currentPage = 1, ?int $total = null, bool $simple = false, array $options = [])
    {
        return new static($items, $listRows, $currentPage, $total, $simple, $options);
    }
}
