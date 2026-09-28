多键排序的结果在相等键上顺序是随机的。phpsort 是 PHP 8 的
多键排序库（php-cli，无 composer，无第三方依赖），构建不需要额外步骤，
自检走 `scripts/check.sh`（`check/` 是固定验收程序，别改），既有用例走 `php tests/run.php`。

`MultiSort.php` 里那份实现每个键单独排一趟。判据是**比较次数**不是墙钟：
`src/Counter.php` 里的计数器已给全，`check/` 会按规模卡上界。

任务：改成比较次数更低、且稳定的实现，同时守住 README 里的 8 条契约。

验收：
- php tests/run.php 全绿；
- php check/check.php 退出码 0，8 个场景全过（semantics 3 + stable 2 + cost 2 + edge 1）。

约束：
1. 不改 `check/`、不改 `src/Counter.php` 的字段名与含义；可以新增类文件。
2. 对外 API 名与签名不许改；`tests/run.php` 里的既有用例一条都不许删或改。
3. 不许用 composer，不许引入任何第三方库；不许用 mbstring 扩展。
4. 不许修改调用方传进来的数组。
