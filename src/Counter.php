<?php

declare(strict_types=1);

namespace Phpsort;

/**
 * 键比较次数计数器（固定入口）。
 *
 * 字段名与含义固定：
 *   Counter::$cmp —— 每真正比较两个键值一次就加一。
 *
 * 判据完全不依赖墙钟 / 内存 / 机器速度，只看这个整数。
 */
final class Counter
{
    /** 已发生的键比较次数。 */
    public static int $cmp = 0;

    /** 把计数清零（用例与固定件在每段测量前调用）。 */
    public static function reset(): void
    {
        self::$cmp = 0;
    }
}
