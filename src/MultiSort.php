<?php

declare(strict_types=1);

namespace Phpsort;

/**
 * 多键排序。
 *
 * `$keys` 是有序映射 `键名 => 'asc'|'desc'`，数组顺序即优先级。
 * 一次排序完成全部键比较，所有键相等时用原始下标保证稳定。
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
        if ($keys === []) {
            return array_values($rows);
        }

        $plan = [];
        foreach ($keys as $name => $dir) {
            $plan[] = [(string) $name, self::isDescending($dir)];
        }

        $indexed = [];
        foreach ($rows as $index => $row) {
            $indexed[] = [$row, $index];
        }

        usort($indexed, static function (array $left, array $right) use ($plan): int {
            [$leftRow, $leftIndex] = $left;
            [$rightRow, $rightIndex] = $right;

            foreach ($plan as [$name, $descending]) {
                Counter::$cmp++;

                $result = self::compareValues(
                    self::valueOf($leftRow, $name),
                    self::valueOf($rightRow, $name),
                    $descending
                );

                if ($result !== 0) {
                    return $result;
                }
            }

            return $leftIndex <=> $rightIndex;
        });

        return array_map(static fn (array $item): array => $item[0], $indexed);
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
    private static function compareValues(mixed $a, mixed $b, bool $descending): int
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

        if ($d !== 0 && $descending) {
            $d = -$d;
        }

        return $d;
    }

    private static function isDescending(mixed $dir): bool
    {
        return is_string($dir) && strtolower($dir) === 'desc';
    }
}
