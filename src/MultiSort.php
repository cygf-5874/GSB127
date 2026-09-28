<?php

declare(strict_types=1);

namespace Phpsort;

/**
 * 多键排序。
 *
 * `$keys` 是有序映射 `键名 => 'asc'|'desc'`，数组顺序即优先级。
 * 当前的写法：从优先级最低的键开始，每个键单独 `usort` 一趟。
 */
final class MultiSort
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, string>      $keys
     *
     * @return list<array<string, mixed>>
     */
    public static function sort(array $rows, array $keys): array
    {
        $plan = [];
        foreach ($keys as $name => $dir) {
            $plan[] = [(string) $name, self::direction($dir)];
        }

        for ($i = count($plan) - 1; $i >= 0; $i--) {
            [$name, $dir] = $plan[$i];

            usort($rows, static function (array $a, array $b) use ($name, $dir): int {
                Counter::$cmp++;

                $d = self::compareValues(self::valueOf($a, $name), self::valueOf($b, $name), $dir);

                return $d < 0 ? -1 : 1;
            });
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function valueOf(array $row, string $name): mixed
    {
        return $row[$name] ?? null;
    }

    /**
     * 比较两个键值：`null` 恒定在前；`int`/`float` 按数值；`string` 按字节序（`strcmp`）。
     */
    private static function compareValues(mixed $a, mixed $b, int $dir): int
    {
        if ($a === null) {
            return $b === null ? 0 : -1;
        }

        if ($b === null) {
            return 1;
        }

        if (is_string($a) && is_string($b)) {
            $d = strcmp($a, $b);
        } else {
            $d = $a <=> $b;
        }

        if ($d !== 0 && $dir < 0) {
            $d = -$d;
        }

        return $d;
    }

    private static function direction(mixed $dir): int
    {
        return (is_string($dir) && strtolower($dir) === 'desc') ? -1 : 1;
    }
}
