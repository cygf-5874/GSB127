<?php

declare(strict_types=1);

/**
 * 调用方给的最小复现：一组行在所有键上完全相等，排序后 ids 应当原样不动。
 *
 * 跑法：php repro.php —— 现象不再复现才退 0，否则退 1。
 */

require __DIR__ . '/autoload.php';

use Phpsort\Counter;
use Phpsort\MultiSort;

$rows = [];
$want = [];
for ($i = 1; $i <= 8; $i++) {
    $id = 'r' . $i;
    $rows[] = ['id' => $id, 'g' => 7, 's' => 7];
    $want[] = $id;
}

Counter::reset();
$out = MultiSort::sort($rows, ['g' => 'asc', 's' => 'asc']);
$got = array_map(static fn (array $row): string => (string) $row['id'], $out);

printf("① 全键相等的 8 行，输入顺序：\n   %s\n", implode(' ', $want));
printf("   实际输出：\n   %s\n", implode(' ', $got));

$broken = $got !== $want;

echo $broken ? "② 顺序被打乱了\n" : "② 顺序保持不变\n";

exit($broken ? 1 : 0);
