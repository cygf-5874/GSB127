# phpsort

PHP 8 的「多键稳定排序」：按一组键的优先级给行数组排序，键相等的行保持输入顺序。
只依赖 PHP 标准库（php-cli 8.1+），不用 composer、不用第三方依赖、不用 mbstring。

## 用法

```php
require __DIR__ . '/autoload.php';

use Phpsort\MultiSort;

// $keys 是有序映射：键名 => 'asc'|'desc'，数组顺序就是优先级（前一个键优先）
$rows = [
    ['g' => 1, 's' => 3, 'name' => 'a'],
    ['g' => 1, 's' => 1, 'name' => 'b'],
    ['g' => 0, 's' => 2, 'name' => 'c'],
];

MultiSort::sort($rows, ['g' => 'asc', 's' => 'asc']);
// => ['c', 'b', 'a']（按 g 升序；g 相等时按 s 升序）
```

## 对外契约

1. **接口**
   `MultiSort::sort(array $rows, array $keys): array`。
   `$rows` 是 `list<array<string,mixed>>`，`$keys` 是**有序映射** `键名 => 'asc'|'desc'`。
   返回**新数组**（`list`），不修改调用方传进来的 `$rows`。签名固定，不得更改。
2. **必须稳定**：在所有键上都相等的行，必须保持它们在 `$rows` 里的**输入顺序**。
   稳定性只能靠自己记录原始下标来保证，不得依赖某个 PHP 版本的 `usort` 恰好稳定。
3. **比较语义**：`int` / `float` 按键值数值比较；`string` 按**字节序**（`strcmp`）。
   **不得**使用 `strcoll` / `setlocale` / `SORT_LOCALE_STRING` 之类的本地化比较。
4. **`null` 排序**：`null` 恒定排在非 `null` **之前**（升序、降序都一样）。
5. **比较计数**：`Phpsort\Counter::$cmp` 是**键比较次数**计数器——每真正比较两个键值一次就加一。
   `sort()` 必须自行维护这个计数；判据不看墙钟、不看内存。
6. **规模判据**：`n = 50_000` 行、2 个键时，一次 `sort()` 结束时 `Counter::$cmp` 必须
   **小于 1_200_000**。逐键排一趟的写法在同一数据上约为 1.6e6~1.8e6。
7. **退化输入**：`$keys` 为空时按原始顺序返回（仍是新数组）；`$rows` 为空时返回 `[]`。
8. **可复现**：同一输入在同一进程内多次调用结果**逐元素相同**；结果不得依赖
   调用历史、`ini` 设置、默认 locale 或数组的哈希顺序。

## 目录

- `src/MultiSort.php` —— 排序实现
- `src/Counter.php` —— 比较次数计数器（固定入口）
- `tests/run.php` —— 既有回归用例
- `repro.php` —— 调用方给的最小复现
- `check/check.php` —— 固定验收（**勿改**）
- `scripts/check.sh` —— 固定验收入口

## 自检

```bash
php tests/run.php
php repro.php
bash scripts/check.sh          # = php check/check.php
bash scripts/check.sh -list    # 列出全部场景
```

`check/check.php` 支持 `-list` 与 `--only <组名>`（组名：`semantics` / `stable` / `cost` / `edge`），
逐场景打印 `PASS <组>/<名>` 或 `FAIL <组>/<名>  期望=… 实际=…`，结尾打印 `结果：通过 x/N`，
全过 `exit 0`，否则 `exit 1`，失败不早退。
