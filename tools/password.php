<?php
declare(strict_types=1);

/**
 * 生成后台密码哈希。
 * 用法：php tools/password.php "你的密码"
 * 把输出填进 app/config.php 的 admin.password_hash，并把 admin.password 置空。
 */

if (PHP_SAPI !== 'cli') {
    exit('只能在命令行运行');
}

$password = $argv[1] ?? '';
if ($password === '') {
    fwrite(STDERR, "用法：php tools/password.php \"你的密码\"\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "密码太短，建议至少 8 位，最好 12 位以上。\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . "\n";
