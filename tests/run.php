<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Phpsort\Counter;
use Phpsort\MultiSort;

/**
 * 既有回归用例：覆盖单键 / 多键 / 数值 / 字符串 / null / 退化输入。
 *
 * 用法：php tests/run.php —— 全绿退 0，否则退 1。
 */

final class CaseFailure extends \Exception
{
    public function __construct(string $expected, string $actual)
    {
        parent::__construct('assertion failed');
        $this->expected = $expected;
        $this->actual = $actual;
    }

    public string $expected;

    public string $actual;
}

function show(mixed $value): string
{
    $text = is_string($value) ? $value : var_export($value, true);

    return str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $text);
}

function expectSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new CaseFailure(show($expected), show($actual));
    }
}

function expectTrue(bool $ok, string $expected = 'true', string $actual = 'false'): void
{
    if (!$ok) {
        throw new CaseFailure($expected, $actual);
    }
}

/** 把一组行投影成 id 序列，便于比对顺序。 */
function ids(array $rows): array
{
    return array_map(static fn (array $row): mixed => $row['id'], $rows);
}

/** @return list<array<string, mixed>> */
function rows(array $spec): array
{
    $out = [];
    foreach ($spec as $id => $values) {
        $out[] = ['id' => $id] + $values;
    }

    return $out;
}

$cases = [];

$cases[] = [
    '单键升序（int）',
    static function (): void {
        $out = MultiSort::sort(rows(['a' => ['k' => 3], 'b' => ['k' => 1], 'c' => ['k' => 2]]), ['k' => 'asc']);

        expectSame(['b', 'c', 'a'], ids($out));
    },
];

$cases[] = [
    '单键降序（int）',
    static function (): void {
        $out = MultiSort::sort(rows(['a' => ['k' => 3], 'b' => ['k' => 1], 'c' => ['k' => 2]]), ['k' => 'desc']);

        expectSame(['a', 'c', 'b'], ids($out));
    },
];

$cases[] = [
    '双键优先级：前一个键优先',
    static function (): void {
        $out = MultiSort::sort(
            rows([
                'a' => ['g' => 2, 's' => 1],
                'b' => ['g' => 1, 's' => 9],
                'c' => ['g' => 4, 's' => 0],
                'd' => ['g' => 3, 's' => 5],
            ]),
            ['g' => 'asc', 's' => 'asc']
        );

        expectSame(['b', 'a', 'd', 'c'], ids($out));
    },
];

$cases[] = [
    '三键优先级',
    static function (): void {
        $out = MultiSort::sort(
            rows([
                'a' => ['g' => 3, 's' => 1, 't' => 2],
                'b' => ['g' => 1, 's' => 1, 't' => 1],
                'c' => ['g' => 4, 's' => 9, 't' => 9],
                'd' => ['g' => 2, 's' => 0, 't' => 0],
            ]),
            ['g' => 'asc', 's' => 'asc', 't' => 'asc']
        );

        expectSame(['b', 'd', 'a', 'c'], ids($out));
    },
];

$cases[] = [
    '字符串键按字节序（大写在小写之前）',
    static function (): void {
        $out = MultiSort::sort(
            rows(['a' => ['k' => 'b'], 'b' => ['k' => 'A'], 'c' => ['k' => 'a'], 'd' => ['k' => 'B']]),
            ['k' => 'asc']
        );

        expectSame(['b', 'd', 'c', 'a'], ids($out));
    },
];

$cases[] = [
    'int 与 float 按键值数值比较（1 < 1.5 < 2）',
    static function (): void {
        $out = MultiSort::sort(
            rows(['a' => ['k' => 2], 'b' => ['k' => 1.5], 'c' => ['k' => 1], 'd' => ['k' => 10]]),
            ['k' => 'asc']
        );

        expectSame(['c', 'b', 'a', 'd'], ids($out));
    },
];

$cases[] = [
    'null 排在非 null 之前（升序）',
    static function (): void {
        $out = MultiSort::sort(
            rows(['a' => ['k' => 2], 'b' => ['k' => null], 'c' => ['k' => 1]]),
            ['k' => 'asc']
        );

        expectSame(['b', 'c', 'a'], ids($out));
    },
];

$cases[] = [
    'null 排在非 null 之前（降序）',
    static function (): void {
        $out = MultiSort::sort(
            rows(['a' => ['k' => 2], 'b' => ['k' => null], 'c' => ['k' => 1]]),
            ['k' => 'desc']
        );

        expectSame(['b', 'a', 'c'], ids($out));
    },
];

$cases[] = [
    '不修改调用方传进来的数组',
    static function (): void {
        $input = rows(['a' => ['k' => 2], 'b' => ['k' => 1]]);
        $snapshot = $input;

        MultiSort::sort($input, ['k' => 'asc']);

        expectSame($snapshot, $input);
    },
];

$cases[] = [
    '返回的是 list（键从 0 连续编号）',
    static function (): void {
        $out = MultiSort::sort(rows(['a' => ['k' => 2], 'b' => ['k' => 1]]), ['k' => 'asc']);

        expectSame([0, 1], array_keys($out));
    },
];

$cases[] = [
    '空 rows 返回空数组',
    static function (): void {
        expectSame([], MultiSort::sort([], ['k' => 'asc']));
    },
];

$cases[] = [
    'keys 为空时保持原始顺序，且多次调用结果相同',
    static function (): void {
        $input = rows(['a' => ['k' => 3], 'b' => ['k' => 1], 'c' => ['k' => 2]]);

        $first = MultiSort::sort($input, []);
        $second = MultiSort::sort($input, []);

        expectSame(['a', 'b', 'c'], ids($first));
        expectSame(ids($first), ids($second));
    },
];

$passed = 0;
$total = count($cases);

foreach ($cases as $index => $case) {
    $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
    $name = $case[0];

    try {
        $case[1]();
        $passed++;
        echo "PASS  $number $name\n";
    } catch (\Throwable $error) {
        $expected = $error instanceof CaseFailure ? $error->expected : '(无异常)';
        $actual = $error instanceof CaseFailure ? $error->actual : $error->getMessage();
        echo "FAIL  $number $name  期望=$expected 实际=$actual\n";
    }
}

Counter::reset();

echo "通过 $passed/$total\n";

exit($passed === $total ? 0 : 1);
