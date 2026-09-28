<?php

declare(strict_types=1);

/**
 * phpsort 固定验收程序（固定件）。**别改这个文件。**
 *
 * 用法：
 *   php check/check.php                 跑全部场景
 *   php check/check.php -list           列出全部场景
 *   php check/check.php --only cost     只跑某一组（semantics/stable/cost/edge，可逗号分隔）
 *
 * 四组共 8 个场景：
 *   semantics 3 —— 返回新数组且不改入参；多键优先级（含主键相等的行）；null 恒定在前；
 *   stable    2 —— 全键相等的行保持输入顺序（升序 / 降序）；
 *   cost      2 —— n=50_000、2 键时 Counter::$cmp < 1_200_000，且结果顺序正确；
 *   edge      1 —— 空 rows 返回 []；空 keys 保持原顺序。
 *
 * 期望顺序由本文件独立算出（不调用被测实现），所以「什么都不排」也过不了。
 * 判据全程确定：只用整数算术造数据，不读时间、不用随机源、不依赖 PHP 数组的哈希顺序。
 */

require __DIR__ . '/../autoload.php';

use Phpsort\Counter;
use Phpsort\MultiSort;

// ---------------------------------------------------------------------------
// 断言失败
// ---------------------------------------------------------------------------

final class CheckFailure extends \Exception
{
    public function __construct(
        public readonly string $expected = '-',
        public readonly string $actual = '-',
        string $message = 'assertion failed'
    ) {
        parent::__construct($message);
    }
}

function show(mixed $value): string
{
    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            return strlen($encoded) > 300 ? substr($encoded, 0, 300) . '…' : $encoded;
        }
    }

    $text = is_string($value) ? $value : var_export($value, true);

    return str_replace(["\n", "\r", "\t"], ['\n', '\r', '\t'], $text);
}

function expectSame(mixed $expected, mixed $actual, string $what = ''): void
{
    if ($expected !== $actual) {
        throw new CheckFailure(show($expected), show($actual), $what === '' ? 'assertion failed' : $what);
    }
}

function expectTrue(bool $ok, string $expected, string $actual, string $what = ''): void
{
    if (!$ok) {
        throw new CheckFailure($expected, $actual, $what === '' ? 'assertion failed' : $what);
    }
}

// ---------------------------------------------------------------------------
// 独立参照实现（只在本文件内使用，属于「期望」而不是「答案」）
// ---------------------------------------------------------------------------

function refValue(array $row, string $name): mixed
{
    return $row[$name] ?? null;
}

/** null 恒定在前；int/float 按数值；string 按字节序。 */
function refCompare(mixed $a, mixed $b): int
{
    if ($a === null || $b === null) {
        return $a === $b ? 0 : ($a === null ? -1 : 1);
    }

    return (is_string($a) && is_string($b)) ? strcmp($a, $b) : ($a <=> $b);
}

/**
 * 稳定性用「原始下标」兜底，因此本函数给出的一定是契约要求的顺序。
 *
 * @param list<array<string, mixed>> $rows
 * @param array<string, string>      $keys
 *
 * @return list<mixed>
 */
function refIds(array $rows, array $keys): array
{
    $indexed = [];
    foreach ($rows as $i => $row) {
        $indexed[] = [$i, $row];
    }

    usort($indexed, static function (array $a, array $b) use ($keys): int {
        foreach ($keys as $name => $dir) {
            $down = is_string($dir) && strtolower($dir) === 'desc';
            $x = refValue($a[1], (string) $name);
            $y = refValue($b[1], (string) $name);

            if ($x === null || $y === null) {
                if ($x === $y) {
                    continue;
                }

                return $x === null ? -1 : 1;   // null 恒定在前，与升降序无关
            }

            $d = refCompare($x, $y);
            if ($d !== 0) {
                return $down ? -$d : $d;
            }
        }

        return $a[0] <=> $b[0];
    });

    return array_map(static fn (array $pair): mixed => $pair[1]['id'] ?? null, $indexed);
}

/** @return list<array<string, mixed>> */
function makeRows(array $spec): array
{
    $out = [];
    foreach ($spec as $id => $values) {
        $out[] = ['id' => $id] + $values;
    }

    return $out;
}

function idsOf(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        $out[] = is_array($row) ? ($row['id'] ?? null) : null;
    }

    return $out;
}

/**
 * n 行、2 个键的规模数据。全部用整数算术生成，跨平台一致。
 *
 * @return list<array<string, mixed>>
 */
function scaleRows(int $n, string $shape): array
{
    $rows = [];

    for ($i = 0; $i < $n; $i++) {
        if ($shape === 'grid') {
            $a = intdiv($i, 100);
            $b = $i % 100;
        } else {
            $a = ($i * 2654435761) % 4294967291;
            $b = ($i * 7919) % 100003;
        }

        $rows[] = ['id' => $i, 'a' => $a, 'b' => $b];
    }

    return $rows;
}

const COST_LIMIT = 1_200_000;
const COST_N = 50_000;

/** @return array{0: int, 1: array} */
function measure(array $rows, array $keys): array
{
    Counter::reset();
    $out = MultiSort::sort($rows, $keys);

    return [Counter::$cmp, $out];
}

// ---------------------------------------------------------------------------
// semantics 3
// ---------------------------------------------------------------------------

$semantics = [
    [
        'name' => 'new-array',
        'why' => '返回新 list、不改入参、同一输入多次调用结果相同',
        'fn' => static function (): void {
            $input = makeRows(['a' => ['k' => 2], 'b' => ['k' => 1], 'c' => ['k' => 3]]);
            $snapshot = $input;

            Counter::reset();
            $first = MultiSort::sort($input, ['k' => 'asc']);
            $second = MultiSort::sort($input, ['k' => 'asc']);

            expectSame($snapshot, $input, '入参不许被修改');
            expectSame(['b', 'a', 'c'], idsOf($first), '返回值顺序');
            expectSame([0, 1, 2], array_keys($first), '返回值必须是 list');
            expectSame(idsOf($first), idsOf($second), '同一输入多次调用结果必须相同');
        },
    ],
    [
        'name' => 'key-priority',
        'why' => '多键优先级正确；主键相等的行按次键排；字符串按字节序',
        'fn' => static function (): void {
            $rows = makeRows([
                'a' => ['g' => 1, 's' => 'b'],
                'b' => ['g' => 1, 's' => 'A'],
                'c' => ['g' => 0, 's' => 'z'],
                'd' => ['g' => 1, 's' => 'a'],
                'e' => ['g' => 0, 's' => 'B'],
                'f' => ['g' => 2, 's' => 'm'],
            ]);
            $keys = ['g' => 'asc', 's' => 'asc'];

            $want = refIds($rows, $keys);

            Counter::reset();
            $got = idsOf(MultiSort::sort($rows, $keys));

            expectSame($want, $got, '主键升序、主键相等时按次键字节序升序');
        },
    ],
    [
        'name' => 'null-first',
        'why' => 'null 恒定排在非 null 之前，升降序都一样',
        'fn' => static function (): void {
            $rows = makeRows([
                'a' => ['k' => 2],
                'b' => ['k' => null],
                'c' => ['k' => 1],
                'd' => ['k' => 3],
            ]);

            Counter::reset();
            $asc = idsOf(MultiSort::sort($rows, ['k' => 'asc']));
            $desc = idsOf(MultiSort::sort($rows, ['k' => 'desc']));

            expectSame('b', $asc[0] ?? null, '升序时 null 必须在最前');
            expectSame('b', $desc[0] ?? null, '降序时 null 也必须在最前');
            expectSame(['c', 'a', 'd'], array_slice($asc, 1), '升序时非 null 部分');
            expectSame(['d', 'a', 'c'], array_slice($desc, 1), '降序时非 null 部分');
        },
    ],
];

// ---------------------------------------------------------------------------
// stable 2
// ---------------------------------------------------------------------------

/** @return list<array<string, mixed>> */
function tieRows(int $n, array $values): array
{
    $rows = [];
    for ($i = 1; $i <= $n; $i++) {
        $rows[] = ['id' => 'r' . $i] + $values;
    }

    return $rows;
}

$stable = [
    [
        'name' => 'asc-ties',
        'why' => '全键相等的行保持输入顺序（升序）',
        'fn' => static function (): void {
            $rows = tieRows(24, ['g' => 7]);
            $want = array_map(static fn (int $i): string => 'r' . $i, range(1, 24));

            Counter::reset();
            $got = idsOf(MultiSort::sort($rows, ['g' => 'asc']));

            expectSame($want, $got, '全键相等的行必须保持输入顺序');
        },
    ],
    [
        'name' => 'desc-ties',
        'why' => '全键相等的行保持输入顺序（降序、多键）',
        'fn' => static function (): void {
            $rows = tieRows(24, ['g' => 2, 's' => 5]);
            $want = array_map(static fn (int $i): string => 'r' . $i, range(1, 24));
            $keys = ['g' => 'desc', 's' => 'desc'];

            Counter::reset();
            $got = idsOf(MultiSort::sort($rows, $keys));

            expectSame($want, $got, '降序下全键相等的行必须保持输入顺序');
            expectSame(refIds($rows, $keys), $got, '期望顺序须与独立参照一致');
        },
    ],
];

// ---------------------------------------------------------------------------
// cost 2
// ---------------------------------------------------------------------------

$cost = [];

foreach (['distinct' => 'distinct', 'grid' => 'grid'] as $label => $shape) {
    $cost[] = [
        'name' => 'scale-' . $label,
        'why' => 'n=50000、2 键时 Counter::$cmp < ' . COST_LIMIT . '，且顺序正确',
        'fn' => static function () use ($shape): void {
            $rows = scaleRows(COST_N, $shape);
            $keys = ['a' => 'asc', 'b' => 'asc'];
            $want = refIds($rows, $keys);

            [$cmp, $out] = measure($rows, $keys);
            $got = idsOf($out);

            expectSame($want, $got, '规模数据的排序结果必须正确（顺序 + 稳定）');
            expectTrue(
                $cmp < COST_LIMIT,
                'Counter::$cmp < ' . COST_LIMIT,
                'Counter::$cmp = ' . $cmp,
                '比较次数超过上界'
            );
        },
    ];
}

// ---------------------------------------------------------------------------
// edge 1
// ---------------------------------------------------------------------------

$edge = [
    [
        'name' => 'empty-inputs',
        'why' => 'rows 为空返回 []；keys 为空保持原顺序',
        'fn' => static function (): void {
            Counter::reset();
            expectSame([], MultiSort::sort([], ['k' => 'asc']), '空 rows 必须返回 []');

            $rows = makeRows(['a' => ['k' => 3], 'b' => ['k' => 1], 'c' => ['k' => 2]]);
            Counter::reset();
            $out = MultiSort::sort($rows, []);

            expectSame(['a', 'b', 'c'], idsOf($out), '空 keys 必须保持原始顺序');
            expectSame([0, 1, 2], array_keys($out), '空 keys 的返回值也必须是 list');

            $dup = makeRows(['x' => ['k' => 1], 'y' => ['k' => 1], 'z' => ['k' => 1]]);
            Counter::reset();
            expectSame(['x', 'y', 'z'], idsOf(MultiSort::sort($dup, [])), '空 keys 且键全相等时也必须保持原顺序');
        },
    ],
];

$GROUPS = [
    'semantics' => $semantics,
    'stable' => $stable,
    'cost' => $cost,
    'edge' => $edge,
];

// ---------------------------------------------------------------------------
// runner
// ---------------------------------------------------------------------------

$flat = [];
foreach ($GROUPS as $group => $scenarios) {
    foreach ($scenarios as $scenario) {
        $flat[] = [$group, $scenario['name'], $scenario['why'], $scenario['fn']];
    }
}

$argv = $argv ?? [];
$doList = false;
$only = null;

for ($i = 1; $i < count($argv); $i++) {
    $arg = (string) $argv[$i];

    if ($arg === '-list' || $arg === '--list') {
        $doList = true;
    } elseif ($arg === '--only' || $arg === '--group') {
        $i++;
        if ($i >= count($argv)) {
            fwrite(STDERR, "--only 需要一个组名（semantics/stable/cost/edge）\n");
            exit(2);
        }
        $only = [];
        foreach (explode(',', (string) $argv[$i]) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $only[$part] = true;
            }
        }
    } elseif ($arg === '-h' || $arg === '--help') {
        echo "用法: php check/check.php [-list] [--only <组名>]\n";
        exit(0);
    } else {
        fwrite(STDERR, "未知参数: {$arg}\n");
        exit(2);
    }
}

if ($doList) {
    foreach ($flat as [$group, $name, $why, $_fn]) {
        printf("[%-9s] %-14s %s\n", $group, $name, $why);
    }
    exit(0);
}

$selected = [];
foreach ($flat as $entry) {
    if ($only === null || isset($only[$entry[0]])) {
        $selected[] = $entry;
    }
}

if ($selected === []) {
    fwrite(STDERR, "没有匹配的场景\n");
    exit(2);
}

$passed = 0;
$failed = 0;

foreach ($selected as [$group, $name, $_why, $fn]) {
    try {
        $fn();
    } catch (CheckFailure $exc) {
        $failed++;
        printf("FAIL %s/%s  期望=%s 实际=%s（%s）\n", $group, $name, $exc->expected, $exc->actual, $exc->getMessage());
        continue;
    } catch (\Throwable $exc) {
        $failed++;
        printf("FAIL %s/%s  期望=正常返回 实际=%s: %s\n", $group, $name, get_class($exc), $exc->getMessage());
        continue;
    }

    $passed++;
    printf("PASS %s/%s\n", $group, $name);
}

$total = count($selected);
printf("结果：通过 %d/%d\n", $passed, $total);
exit($failed === 0 ? 0 : 1);
