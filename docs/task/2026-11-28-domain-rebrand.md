# 2026-11-28 域名与品牌替换（chenyu → weget.site / 电脑小百科）

## 状态

进行中（源码侧已完成，dist 待重新生成）

## 目标

- 域名 `chenyu.me` → `weget.site`
- 品牌展示文字 `chenyu` → `电脑小百科`
- 邮箱 `hello@chenyu.me` → `2591735711@qq.com`（用户中途指定，不用 hello@weget.site）
- GitHub 链接 `github.com/chenyu` → `github.com/youwencc`（git remote 实际账号，原链接是过时账号）

## 改动

- `app/config.php` — base_url 改 https://weget.site，github 改 youwencc（私有不入库）
- `app/config.example.php` — base_url、email、github 同步；db 示例名 chenyu_blog/chenyu → wegetblog
- `app/templates/static/about.html` — 域名/邮箱/GitHub 卡片/JSON-LD；`<span class="cv">chenyu</span>` → 电脑小百科
- `app/templates/static/column.html` — 域名与 JSON-LD
- `app/content/seed.json` — 9 处 canonical 域名、specs 里 github.com/chenyu
- `tools/selftest.php` — canonical 断言字符串
- `docs/DEPLOY.md` — 域名、邮箱、建库示例名
- `dist/wiki/{ai-agent-wiki,architecture,git,models}.html` — 手写知识库页页脚 GitHub 链接（wiki 无生成源，直接改）

## 验证

- `grep -rn chenyu app/ tools/ docs/ public/ .github/` → 无残留
- `php -l` config.php / config.example.php / selftest.php → 无语法错误
- seed.json `json_decode` → valid
- 数据库 settings 表 site 行 → 检查过不含 chenyu，无需改库
- `dist/CNAME` 已是 weget.site，未动

## 遗留

- `dist/` 生成产物（about/blog/column/works/posts/feed.xml/sitemap.xml/robots.txt/works.html）仍带旧域名，**需跑一次「生成静态站」（php tools/publish.php）刷新**；等用户确认后执行
- GitHub Pages 自定义域名已切 weget.site（CNAME 已就位），DNS 解析需用户侧确认
- `posts/`、`intro/` 旧版留档含旧域名，不再被复制进 dist（AGENTS.md「原样复制进 dist」的说法已过时），按留档原则未动
- 同日另发现：后台「生成静态站」卡死问题的排查进行到一半被打断，已有线索记于会话（18:19 PHP 日志有 session_start() 阻塞 30 秒超时的 Fatal；public/.htaccess 是 smliser 项目的无关规则文件，建议清掉）

## 风险

- github.com/youwencc 是按 git remote 推断的真实账号，如站点作者另有账号需改回
- 库名示例改为 wegetblog 仅影响文档与模板示例，不影响实际库
