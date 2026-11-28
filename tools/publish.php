<?php
declare(strict_types=1);

/**
 * 命令行生成静态站（等价于后台的「生成静态站」按钮）。
 * 用法：php tools/publish.php
 */

require_once __DIR__ . '/../app/lib/publish.php';

try {
    $stats = publish_site();
    printf("已生成 %d 个页面（%d 篇文章、%d 个作品），资产 %d 个文件。\n输出目录：%s\n",
        $stats['pages'], $stats['posts'], $stats['works'], $stats['assets'], $stats['dist']);
    if (!empty($stats['restored_works'])) {
        printf("已从 seed.json 补回缺失作品：%s\n", implode(', ', $stats['restored_works']));
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '生成失败：' . $e->getMessage() . "\n");
    exit(1);
}
