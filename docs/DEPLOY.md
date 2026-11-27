# 部署说明

这套站点的结构是「本地 PHP + MySQL 生成静态站，GitHub Pages 发布」：

```
本地（PHP 8.2 + MySQL，phpStudy）
├─ app/                 逻辑、模板、配置
│  ├─ config.php        数据库与站点配置（不提交 Git）
│  ├─ lib/ bootstrap / content / render / publish / install
│  ├─ templates/        首页、博客、作品、详情页模板
│  ├─ assets/           style.css、main.js（生成时复制到 dist）
│  └─ content/seed.json 现有 4 篇文章 + 3 个作品
├─ public/              后台入口
│  ├─ admin.php         后台：文章/作品增删改 + 生成静态站
│  └─ install.php       一次性初始化：建表、导入、生成
└─ dist/                生成的静态站，发布产物，随代码提交

本地生成 dist/ ──commit + push──► GitHub Pages
```

后台写的每一篇内容都先落到 MySQL，点「生成静态站」后写成 HTML，commit + push 后 Pages 立即部署。PHP 源码和数据库密码不会出现在 Pages 上，所以仓库公开也没关系。

## 一、准备数据库

本地 phpStudy 里建 MySQL 数据库，记下主机、端口、库名、用户名、密码。字符集选 `utf8mb4`：

```bash
mysql -u root -p -e "CREATE DATABASE chenyu_blog CHARACTER SET utf8mb4"
```

## 二、初始化与写配置

用 FTP 或面板的文件管理器上传：

1. `app/` → 账号根目录下的 `app/`（**不要**放进 Web 根）
2. `public/admin.php`、`public/install.php` → Web 根目录
3. `dist/` 先不用传，安装脚本会在账号根目录生成

常见的目录对应关系：

| 主机目录 | 放什么 |
| --- | --- |
| `/home/你的账号/app` | 整个 `app/` |
| `/home/你的账号/wwwroot`（或 `public_html`、`htdocs`） | `admin.php`、`install.php` |
| `/home/你的账号/dist` | 自动生成，不用手动建 |

`admin.php` 里用 `__DIR__ . '/../app/...'` 找逻辑文件，所以 `app/` 必须是 Web 根的**同级目录**。如果你的主机只能把两者放在一起，就把 `app/` 放进 Web 根下并改名（例如 `app-core`），同时把 `admin.php`、`install.php` 里的路径改掉——但这样 PHP 源码可能被直接访问，不建议。

## 三、写配置

复制 `app/config.example.php` 为 `app/config.php`，填写：

```php
'db' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => '你的库名',
    'user' => '你的用户名',
    'pass' => '你的密码',
],
'site' => [
    'base_url'  => 'https://你的域名',   // 不要以斜杠结尾
    'name'      => '电脑小百科 · 技术笔记',
    'author'    => '电脑小百科',
    'email'     => 'hello@chenyu.me',
    'github'    => 'https://github.com/你的账号',
    'dist_dir'  => dirname(__DIR__) . '/dist',
    'seed_file' => __DIR__ . '/content/seed.json',
    'start_year'=> 2021,
],
'admin' => [
    'password_hash' => '',       // 上线前填这里
    'password'      => 'change-me',
    'session'       => 'cy_admin',
],
'install_key' => '一串随机字符',
```

`config.php` 已写进 `.gitignore`，不要提交。

### 生成密码哈希

本地或主机 SSH 里执行：

```bash
php tools/password.php "你的新密码"
```

把输出填到 `admin.password_hash`，并把 `admin.password` 改成空字符串 `''`。这样只认哈希，不认明文。

## 四、初始化

浏览器打开：

```
https://你的域名/install.php?key=配置里的 install_key
```

它会依次：

1. 建 `posts`、`works`、`categories`、`settings` 表（可重复执行），并做增量迁移：补 `works.cover_image`、`posts.category_id`，把旧文章的 `section` 自动建成分类
2. 如果表是空的，从 `app/content/seed.json` 导入 4 篇文章 + 3 个作品
3. 生成一整站静态文件到 `dist/`

看到「初始化完成」后：

1. 登录 `https://你的域名/admin.php`
2. 确认文章、作品都在，随便打开一篇编辑保存一次
3. **删除 `install.php`**

如果提示目录不可写，把 `dist/` 权限改成 755（或 775），所有者设为 PHP 运行用户。作品图片会在后台上传到 `app/assets/uploads/works/`，这个目录也要可写（775）。

## 五、后台怎么用

`admin.php` 是左侧导航 + 右侧内容的布局：概览、文章、作品、分类、站点设置。

- **文章**：标题、slug（URL）、SEO 标题、描述、栏目分类、标签、关联作品、日期、状态、正文 HTML
- **分类**：名称、slug、描述、排序；删除不删文章，只改成未分类
- **作品**：名称、slug、英文名、lede、状态标签、要点、meta、参数表（每行 `名称 | 内容`）、封面图、正文插图、正文、相关段落
- **站点设置**：顶部导航、页脚链接/备案、首页 Logbook、全站与各页 SEO（首页、博客、作品、关于、专栏）
- **生成静态站**：把当前数据库内容写成 `dist/`，包括首页、博客、作品、详情页、sitemap、RSS、404

保存文章时自动重算字数和阅读分钟。`slug` 决定 URL，改 slug 等于换地址，旧链接会 404，尽量别改已发布的。

### 正文写法

正文是纯 HTML，和原来手写的文章一致：

```html
<p>段落。</p>
<h2 id="goal">小节标题</h2>
<p>更多段落。</p>
<pre><code>code</code></pre>
```

每个 `<h2>` 必须带 `id`，右侧目录是 `main.js` 扫 `h2[id]` 生成的。没有 `id` 的小节不会出现在目录里。

### 发布状态

- 草稿：只存在数据库，不生成页面，不进 sitemap / RSS
- 已发布：需要有发布日期，生成 `dist/` 并 push 后即上线

删除文章或作品后，记得再点一次「生成静态站」，否则 `dist/` 里还留着旧页面。

## 六、发布到 GitHub Pages

后台生成 `dist/` 后，手动推送到仓库，工作流会自动把 `dist/` 发布到 Pages：

```bash
git add dist/
git commit -m "发布：更新静态站"
git push
```

工作流 `.github/workflows/deploy-pages.yml` 在每次 push 到 `master` 时把 `dist/` 打包发布（也可在 Actions 页手动触发）。一次性配置：

1. Settings → Pages → Source 选 **GitHub Actions**
2. 确认 `dist/` 已随代码入库（本仓库的 `.gitignore` 不再忽略它）

发布后 1–2 分钟线上可见。每次后台改完内容 → 点「生成静态站」→ commit + push，三步走完即上线。

### 自定义域名

Pages → Custom domain 填 `chenyu.me`，DNS 加 CNAME 指向 `你的账号.github.io`，勾选 Enforce HTTPS。`site.base_url` 必须和最终域名一致，否则 canonical、sitemap、RSS 里的链接会错。

## 七、本地开发

要求 PHP 8.2+、MySQL 5.7+/8.0。

如果机器上装了 phpStudy，直接用 `PHP 8.2.9nts` + `MySQL 8.0.12`。本地只想看到站点，可以在站点配置里这样写（Apache）：

```apache
DocumentRoot "F:/web-code/blog/dist"
DirectoryIndex index.html

Alias /admin.php   "F:/web-code/blog/public/admin.php"
Alias /install.php "F:/web-code/blog/public/install.php"

<Directory "F:/web-code/blog/dist">
    Options FollowSymLinks
    Require all granted
</Directory>
<Directory "F:/web-code/blog/public">
    Options FollowSymLinks ExecCGI
    Require all granted
</Directory>
```

这样 `http://localhost/` 是生成好的静态站，`http://localhost/admin.php` 是后台。注意站点根目录不要直接指向 `public/`——那里面没有 `index`，Apache 会返回 403。

```bash
# 建库
mysql -u root -p -e "CREATE DATABASE chenyu_blog CHARACTER SET utf8mb4"

# 配置：复制 config.example.php 为 config.php，改 db 段

# 初始化（三行等价于运行一次 install.php）
php -r 'require "app/lib/install.php"; install_schema(); import_seed(); publish_site();'

# 起服务
php -S 127.0.0.1:8080 -t public
# 前台预览：php -S 127.0.0.1:8081 -t dist
```

没有数据库也能检查模板：

```bash
php tools/render-preview.php .preview-dist
```

它用 `seed.json` 直接渲染整套页面到 `.preview-dist/`，适合调样式。

## 八、备份与维护

- 数据库：`mysqldump -u 用户 -p 库名 > backup-$(date +%F).sql`，建议每周一次自动备份
- `dist/` 是生成物，不用备份，随时可从数据库重建
- 升级 PHP 前先看 `php -l` 是否全绿：`find app public tools -name '*.php' -exec php -l {} \;`
- 作品图片在后台上传，落在 `app/assets/uploads/works/`；`dist/` 与数据库一起重建时图片不受影响，备份时记得把这个目录一起打包

## 九、安全检查清单

- [ ] `install.php` 已删除
- [ ] `admin.password_hash` 已填，`admin.password` 已清空
- [ ] `config.php` 不在 Web 根内，且在 `.gitignore` 里
- [ ] 后台 `admin.php` 只在本机/内网运行，不暴露公网；必须公网访问时走 HTTPS
- [ ] 数据库账号不是 root，权限只给这个库
- [ ] 定期备份数据库
