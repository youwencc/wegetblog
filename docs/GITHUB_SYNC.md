# GitHub 同步工作流

`.github/workflows/ftp-sync.yml` 负责把虚拟主机上的 `dist/` 搬到 GitHub Pages。它只搬静态文件，源码和数据库不会进入仓库的发布产物。

## 需要的 Secrets

| Secret | 含义 | 示例 |
| --- | --- | --- |
| `FTP_HOST` | 主机 FTP 地址 | `ftp.chenyu.me` |
| `FTP_USER` | FTP 账号 | `chenyu` |
| `FTP_PASS` | FTP 密码 | — |
| `FTP_DIR` | 主机上 `dist/` 的路径 | `/dist` |

`FTP_DIR` 必须指向 `dist/`，不是站点根。如果配成站点根，安全阀会拦下来：拉到的文件里没有 `dist/index.html` 的对应结构，工作流直接失败。

## 一次同步做了什么

1. **恢复镜像**：上一轮的 FTP 内容缓存在 `/tmp/ftpmirror`，用 `actions/cache/restore` 取回，key 由 `FTP_DIR` 的哈希 + `run_id` 组成
2. **FTP 增量拉取**：`lftp mirror --no-perms` 比对大小和修改时间，只下载变化的文件；限速 2 MB/s、串行、不 `--delete`，避免触发主机防护和目录错乱
3. **白名单过滤**：`rsync` 只放行 html / css / js / json / xml / txt / 图片 / 字体 / 音视频 / pdf / csv，`--exclude='*'` 兜底
4. **三道安全阀**
   - 拉取为空 → 失败（多半是账号或路径错）
   - `dist/index.html` 不存在 → 失败
   - `posts/` + `works/` 的 html 数量，镜像与 `dist/` 必须一致 → 防止拉了一半就提交
5. **提交**：只有内容真的变了才 `git commit`，提交信息带北京时间
6. **发布**：`upload-pages-artifact` + `deploy-pages`，只上传 `dist/`

没有变化时跳过部署，不会每半小时重复发布同一版。

## 为什么镜像放在 /tmp

`rsync --delete` 会把目标目录里「源没有的东西」删掉。镜像如果放在仓库内部，就会被当成多余文件删掉，下一轮又得全量下载。放在 runner 的 `/tmp` 才安全。

## 什么时候需要全量重建

- 换了主机或 `FTP_DIR`：指纹变了，key 自动变化，会重新全量拉
- 怀疑镜像损坏：手动触发时勾选 `full_refresh`
- 缓存被 GitHub 逐出（仓库缓存上限 10 GB，LRU 清理）：下一次自动全量

## 常见失败

| 现象 | 原因 | 处理 |
| --- | --- | --- |
| `FTP 拉取结果为空` | 账号密码错、`FTP_DIR` 写错、主机禁 FTP | 用 FileZilla 手动连一次确认路径 |
| `530 Login incorrect` | 密码含特殊字符被截断 | 换一个不含 `$`、`#` 的密码，或改在 Secret 里转义 |
| `550 ... not found` | `FTP_DIR` 少了前导 `/` 或多了 `dist/` | 用绝对路径，例如 `/home/账号/dist` |
| `内容页数量不一致` | 拉取中断 / 主机目录还在写入 | 重跑；连续失败就勾 `full_refresh` |
| Pages 404 | `dist/` 没内容或 Pages Source 没选 Actions | 看 Actions 日志与 Pages 设置 |
| 页面是旧的 | 后台还没点「生成静态站」 | 先发布，再等同步或手动触发 |

失败时工作流会把诊断信息（不含密码）写进运行页的 Summary：主机、目录、账号、磁盘、镜像文件数、FTP 日志关键行。

## 手动触发

Actions → `Sync site from hosting` → Run workflow：

- 不勾 `full_refresh`：常规增量
- 勾 `full_refresh`：忽略缓存全量重下，怀疑镜像坏了时用

## 与后台的关系

后台只负责把数据库写成 `dist/`，不直接对外发布。这样：

- 线上永远是纯静态页，没有 PHP 请求，速度快、攻击面小
- 数据库密码、后台登录都留在主机上
- Pages 仓库即使公开，也只有 HTML、CSS、JS

想改成「主机直接对外服务」，把 `site.base_url` 指向主机域名，并把 Web 根指向 `dist/` 即可，但那样 `admin.php` 要单独放在另一个目录，且需要自己处理 404 和 HTTPS。
