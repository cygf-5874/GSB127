<?php

declare(strict_types=1);

namespace Phpsort;

/**
 * 多键排序。
 *
 * `$keys` 是有序映射 `键名 => 'asc'|'desc'`，数组顺序即优先级。
 * 使用一次字典序比较完成全部键，并用原始下标保证相等行的稳定性。
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
        $ordered = array_values($rows);
        $plan = [];
        foreach ($keys as $name => $dir) {
            $plan[] = [(string) $name, self::direction($dir)];
        }

        if ($plan === []) {
            return $ordered;
        }

        $indexed = [];
        foreach ($ordered as $index => $row) {
            $indexed[] = [$index, $row];
        }

        usort($indexed, static function (array $a, array $b) use ($plan): int {
            foreach ($plan as [$name, $dir]) {
                Counter::$cmp++;

                $d = self::compareValues(self::valueOf($a[1], $name), self::valueOf($b[1], $name), $dir);
                if ($d !== 0) {
                    return $d < 0 ? -1 : 1;
                }
            }

            return $a[0] <=> $b[0];
        });

        return array_map(static fn (array $item): array => $item[1], $indexed);
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
