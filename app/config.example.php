<?php
/**
 * 复制为 app/config.php 后填写。config.php 不要提交到 Git。
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'chenyu_blog',
        'user' => 'chenyu',
        'pass' => 'CHANGE_ME',
    ],

    'site' => [
        'base_url'  => 'https://chenyu.me',      // 末尾不要斜杠；GitHub Pages 自定义域名
        'name'      => '陈屿 · 技术笔记',
        'author'    => '陈屿',
        'email'     => 'hello@chenyu.me',
        'github'    => 'https://github.com/chenyu',
        'lang'      => 'zh-CN',
        'timezone'  => 'Asia/Shanghai',
        'dist_dir'  => dirname(__DIR__) . '/dist',   // 生成静态站的目录，也是 FTP_DIR 指向的目录
        'seed_file' => __DIR__ . '/content/seed.json',
        'start_year'=> 2021,
    ],

    'admin' => [
        // 推荐：用 tools/password.php 生成 password_hash 后填在这里
        'password_hash' => '',
        // 兜底：password_hash 为空时才使用；上线前请改掉
        'password'      => 'change-me',
        'session'       => 'cy_admin',
        'remember'      => 8 * 3600,
    ],

    // 生产环境请改成随机长字符串；install.php / 维护脚本会校验
    'install_key' => 'change-this-install-key',
];
