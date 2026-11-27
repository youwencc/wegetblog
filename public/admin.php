<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/lib/bootstrap.php';
require_once __DIR__ . '/../app/lib/content.php';
require_once __DIR__ . '/../app/lib/publish.php';
require_once __DIR__ . '/../app/lib/memo.php';

$action = get_str('action');
$view   = get_str('view', 40, 'dashboard');

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0);
    if ($attempts >= 8) {
        flash('error', '尝试次数过多，请重启浏览器会话后再试。');
        redirect('admin.php');
    }
    if ($username !== '' && admin_password_ok($password, $username)) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['admin_user'] = $username;
        $_SESSION['admin_expires'] = time() + 7 * 86400;
        $_SESSION['login_attempts'] = 0;
        flash('ok', '已登录。');
        redirect('admin.php');
    }
    $_SESSION['login_attempts'] = $attempts + 1;
    flash('error', '账户或密码不对。');
    redirect('admin.php');
}

if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $_SESSION = [];
    session_destroy();
    redirect('admin.php');
}

/* 作品图片上传：返回 JSON，编辑器里直接插入或设为封面 */
if ($action === 'upload_image') {
    require_login();
    check_csrf();
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        json_out(['ok' => false, 'error' => '没有收到文件，或上传失败。'], 400);
    }
    if ((int) $file['size'] > 5 * 1024 * 1024) {
        json_out(['ok' => false, 'error' => '图片不能超过 5 MB。'], 400);
    }
    $info = @getimagesize($file['tmp_name']);
    $extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $ext = $info ? ($extMap[$info[2]] ?? '') : '';
    if ($ext === '') {
        json_out(['ok' => false, 'error' => '只支持 JPG / PNG / GIF / WebP 图片。'], 400);
    }
    $dir = CY_APP . '/assets/uploads/works';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        json_out(['ok' => false, 'error' => '无法创建上传目录，请检查 app/assets 的写权限。'], 500);
    }
    $name = date('Ym') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        json_out(['ok' => false, 'error' => '保存文件失败。'], 500);
    }
    $rel = 'assets/uploads/works/' . $name;
    // 顺手复制到 dist，后台预览能立刻看到；正式发布时还会再复制一次
    try {
        $distFile = dist_path($rel);
        if (is_dir(dirname($distFile)) || mkdir(dirname($distFile), 0775, true)) {
            @copy($target, $distFile);
        }
    } catch (Throwable $e) {
        // dist 还没生成时忽略
    }
    json_out([
        'ok' => true,
        'path' => $rel,
        'url' => '/' . $rel,
        'html' => '<figure class="prose-figure"><img src="../' . h($rel) . '" alt="" loading="lazy"><figcaption>图片说明（可删）</figcaption></figure>',
    ]);
}

if ($action !== '' && $action !== 'login' && $action !== 'logout') {
    require_login();
    check_csrf();

    if ($action === 'save_post') {
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $message] = post_save($_POST, $id ?: null);
        flash($ok ? 'ok' : 'error', $message);
        redirect($ok ? 'admin.php' : 'admin.php?view=post&id=' . $id);
    }

    if ($action === 'delete_post') {
        $id = (int) ($_POST['id'] ?? 0);
        $post = post_by_id($id);
        if ($post) {
            post_delete($id);
            flash('ok', '已删除文章「' . $post['title'] . '」。记得重新生成一次静态站。');
        }
        redirect('admin.php');
    }

    if ($action === 'save_work') {
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $message] = work_save($_POST, $id ?: null);
        flash($ok ? 'ok' : 'error', $message);
        redirect($ok ? 'admin.php' : 'admin.php?view=work&id=' . $id);
    }

    if ($action === 'delete_work') {
        $id = (int) ($_POST['id'] ?? 0);
        $work = work_by_id($id);
        if ($work) {
            work_delete($id);
            flash('ok', '已删除作品「' . $work['title'] . '」。记得重新生成一次静态站。');
        }
        redirect('admin.php');
    }

    if ($action === 'save_category') {
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $message] = category_save($_POST, $id ?: null);
        flash($ok ? 'ok' : 'error', $message);
        redirect($ok ? 'admin.php?view=categories' : 'admin.php?view=category&id=' . $id);
    }

    if ($action === 'delete_category') {
        $id = (int) ($_POST['id'] ?? 0);
        $category = category_by_id($id);
        if ($category) {
            category_delete($id);
            flash('ok', '已删除分类「' . $category['name'] . '」，相关文章已变为未分类。');
        }
        redirect('admin.php?view=categories');
    }

    if ($action === 'save_account') {
        $account = admin_account();
        $current = (string) ($_POST['current_password'] ?? '');
        $username = trim((string) ($_POST['username'] ?? ''));
        $newPass = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!admin_password_ok($current, $account['username'])) {
            flash('error', '当前密码不对，账户未修改。');
            redirect('admin.php?view=account');
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username)) {
            flash('error', '账户名需为 3–40 位字母、数字、下划线、点或短横线。');
            redirect('admin.php?view=account');
        }
        if ($newPass !== '' && strlen($newPass) < 8) {
            flash('error', '新密码至少 8 位。');
            redirect('admin.php?view=account');
        }
        if ($newPass !== '' && $newPass !== $confirm) {
            flash('error', '两次输入的新密码不一致。');
            redirect('admin.php?view=account');
        }
        $hash = $newPass !== ''
            ? password_hash($newPass, PASSWORD_DEFAULT)
            : ($account['password_hash'] !== '' ? $account['password_hash'] : password_hash($current, PASSWORD_DEFAULT));
        try {
            admin_account_save($username, $hash);
            $_SESSION['admin_user'] = $username;
            flash('ok', $newPass !== '' ? '账户和密码已更新。' : '账户已更新，密码未改。');
        } catch (Throwable $e) {
            flash('error', '保存账户失败：' . $e->getMessage());
        }
        redirect('admin.php?view=account');
    }

    if ($action === 'save_memo') {
        $id = (int) ($_POST['id'] ?? 0);
        [$ok, $message] = memo_save($_POST, $id ?: null);
        flash($ok ? 'ok' : 'error', $message);
        redirect('admin.php?view=memos' . ($ok ? '' : ($id ? '&id=' . $id : '')));
    }

    if ($action === 'delete_memo') {
        $id = (int) ($_POST['id'] ?? 0);
        $memo = memo_by_id($id);
        if ($memo) {
            memo_delete($id);
            flash('ok', '已删除备忘录。');
        }
        redirect('admin.php?view=memos');
    }

    if ($action === 'toggle_memo') {
        $id = (int) ($_POST['id'] ?? 0);
        if (memo_by_id($id)) {
            memo_toggle_pin($id);
            flash('ok', '已更新置顶状态。');
        }
        redirect('admin.php?view=memos');
    }

    if ($action === 'save_settings') {
        [$ok, $message] = settings_save_form($_POST);
        flash($ok ? 'ok' : 'error', $message);
        redirect('admin.php?view=settings');
    }

    if ($action === 'import_full') {
        require_once __DIR__ . '/../app/lib/install.php';
        try {
            $import = import_seed_full();
            $publish = publish_site();
            flash('ok', sprintf('全量导入完成：%d 篇文章、%d 个作品、%d 个分类，已重新生成 %d 页静态站。',
                $import['posts'], $import['works'], $import['categories'], $publish['pages']));
        } catch (Throwable $e) {
            flash('error', '全量导入失败：' . $e->getMessage());
        }
        redirect('admin.php');
    }

    if ($action === 'publish') {
        try {
            $stats = publish_site();
            flash('ok', sprintf('静态站已生成：%d 个页面（%d 篇文章、%d 个作品），输出到 %s。接下来同步到 GitHub 即可。',
                $stats['pages'], $stats['posts'], $stats['works'], $stats['dist']));
        } catch (Throwable $e) {
            flash('error', '生成失败：' . $e->getMessage());
        }
        redirect('admin.php');
    }
}

$loggedIn = is_logged_in();
$flash = take_flash();

$dbError = null;
$postEdit = null;
$workEdit = null;
$categoryEdit = null;
$memoEdit = null;
$allPosts = [];
$allWorks = [];
$allCategories = [];
$allMemos = [];
$pinnedMemos = [];
$memoStats = ['total' => 0, 'pinned' => 0];
$catNames = [];
$stats = ['posts' => 0, 'works' => 0, 'drafts' => 0, 'categories' => 0, 'latest_date' => '', 'latest_post' => ''];
$settings = $loggedIn ? settings_all() : settings_defaults();
if ($loggedIn) {
    try {
        if ($view === 'post') {
            $id = (int) get_str('id');
            $postEdit = $id ? post_by_id($id) : null;
        }
        if ($view === 'work') {
            $id = (int) get_str('id');
            $workEdit = $id ? work_by_id($id) : null;
        }
        if ($view === 'category') {
            $id = (int) get_str('id');
            $categoryEdit = $id ? category_by_id($id) : null;
        }
        if ($view === 'memo' || $view === 'memos' || $view === 'dashboard') {
            $id = (int) get_str('id');
            $memoEdit = ($view === 'memo' && $id) ? memo_by_id($id) : null;
            $allMemos = memo_all($view === 'dashboard' ? 4 : 0);
            $memoStats = memo_stats();
        }
        $allPosts = posts('all');
        $allWorks = works('all');
        $allCategories = categories();
        $stats = site_stats();
        $settings = settings_all();
        $pinnedMemos = memo_pinned();
        $catNames = [];
        foreach ($allCategories as $c) {
            $catNames[(int) $c['id']] = $c['name'];
        }
    } catch (Throwable $e) {
        $dbError = $e->getMessage();
    }
}
$lastPublish = $loggedIn ? @filemtime(dist_path('index.html')) : false;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>后台 · <?= h(cfg('site.name')) ?></title>
<style>
:root{--bg:#f6f3ec;--card:#fffdf8;--ink:#1b1913;--muted:#6f6858;--line:#ddd5c4;--accent:#a6432b;--ok:#2f6b46;--err:#a6432b}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.65 "Noto Sans SC",-apple-system,"Segoe UI","Microsoft YaHei",sans-serif}
a{color:var(--accent)}
.wrap{max-width:1100px;margin:0 auto;padding:0 24px}
.login{max-width:400px;margin:0 auto;width:100%}
.login-wrap .flash{max-width:400px;width:100%;margin:0 0 16px}
.login-wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px}
.shell{display:grid;grid-template-columns:216px minmax(0,1fr);min-height:100vh}
.side{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;gap:20px;padding:24px 18px;border-right:1px solid var(--line);background:var(--card)}
.side-brand{display:block;text-decoration:none;color:var(--ink);font-weight:600;font-size:17px;letter-spacing:.02em;line-height:1.3}
.side-brand span{display:block;margin-top:4px;color:var(--muted);font-size:11px;font-weight:400;letter-spacing:.08em;text-transform:uppercase}
.side-nav{display:flex;flex-direction:column;gap:2px}
.side-nav a{display:block;padding:8px 10px;color:var(--muted);text-decoration:none;border-left:2px solid transparent;font-size:14px}
.side-nav a:hover{color:var(--ink)}
.side-nav a.on{color:var(--ink);background:rgba(166,67,43,.06);border-left-color:var(--accent);font-weight:600}
.side-foot{margin-top:auto;padding-top:16px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:6px;font-size:13px}
.side-foot a{color:var(--muted);text-decoration:none;padding:4px 0}
.side-foot a:hover{color:var(--ink)}
.main{width:100%;max-width:1160px;padding:28px 40px 60px}
@media(max-width:760px){
  .shell{grid-template-columns:1fr}
  .side{position:static;height:auto;flex-direction:row;align-items:center;flex-wrap:wrap;gap:12px;padding:14px 18px;border-right:0;border-bottom:1px solid var(--line)}
  .side-brand{font-size:15px}
  .side-brand span{display:none}
  .side-nav{flex-direction:row;flex-wrap:wrap;overflow:visible}
  .side-nav a{border-left:0;border-bottom:2px solid transparent;padding:5px 8px}
  .side-nav a.on{border-left:0;border-bottom-color:var(--accent);background:transparent}
  .side-foot{margin-top:0;border-top:0;flex-direction:row;padding-top:0;margin-left:auto}
  .main{padding:24px 20px 48px}
}
@media(max-width:760px){.grid2,.grid3{grid-template-columns:1fr}td.form-actions{white-space:normal}}
h1{font-size:26px;margin:30px 0 6px;letter-spacing:-.01em}
h2{font-size:17px;margin:0}
.sub{color:var(--muted);margin:0 0 22px}
.flash{padding:10px 14px;border:1px solid var(--line);background:var(--card);margin:18px 0;border-left:3px solid var(--ok)}
.flash.error{border-left-color:var(--err);color:var(--err)}
.card{background:var(--card);border:1px solid var(--line);padding:18px 20px;margin:16px 0}
.row{display:flex;gap:14px;flex-wrap:wrap;align-items:center}
.btn{display:inline-block;padding:9px 16px;background:var(--ink);color:#fff;text-decoration:none;border:0;cursor:pointer;font:inherit;border-radius:2px}
.btn:hover{opacity:.88}
.btn--ghost{background:transparent;color:var(--ink);border:1px solid var(--line)}
.btn--danger{background:transparent;color:var(--err);border:1px solid #e2c3bb}
.small{font-size:13px;padding:5px 10px}
.stats{display:flex;gap:26px;margin:8px 0 0;color:var(--muted)}
.stats b{display:block;color:var(--ink);font-size:22px;font-family:"Noto Serif SC",serif}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-size:12px;color:var(--muted);font-weight:500;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap}
td.form-actions{white-space:nowrap}
.slug{color:var(--muted);font-size:12px;font-family:ui-monospace,monospace}
.pill{display:inline-block;padding:2px 7px;border:1px solid var(--line);font-size:12px;color:var(--muted);border-radius:2px;white-space:nowrap}
.pill.live{color:var(--ok);border-color:#bcd3c4}
.pill.draft{color:var(--muted)}
form.inline{display:inline;margin:0}
label{display:block;font-size:13px;color:var(--muted);margin:14px 0 5px}
label span{color:var(--muted)}
input[type=text],input[type=password],input[type=date],input[type=number],select,textarea{width:100%;padding:9px 11px;border:1px solid var(--line);background:#fff;font:inherit;color:var(--ink);border-radius:2px}
textarea{resize:vertical}
textarea.code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;line-height:1.6;min-height:340px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 20px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:0 20px}
.hint{font-size:12px;color:var(--muted);margin:4px 0 0}
.footer{color:var(--muted);font-size:12px;padding:28px 0 40px}
.bar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:26px}
.linkbtn{background:none;border:0;color:var(--muted);cursor:pointer;font:inherit;padding:4px 0;text-align:left}
.linkbtn:hover{color:var(--ink)}
.side-foot form{margin:0}
.rte{border:1px solid var(--line);background:#fff}
.rte-toolbar{display:flex;flex-wrap:wrap;gap:4px;padding:8px;border-bottom:1px solid var(--line);background:var(--bg);position:sticky;top:0;z-index:2}
.rte-toolbar button{background:transparent;border:1px solid transparent;color:var(--ink);padding:4px 9px;font:13px/1.4 inherit;cursor:pointer;border-radius:2px}
.rte-toolbar button:hover{border-color:var(--line);background:var(--card)}
.rte-toolbar .sep{width:1px;background:var(--line);margin:2px 5px}
.rte-canvas{min-height:420px;padding:18px 20px;outline:none;overflow-wrap:anywhere}
.rte-canvas:focus{box-shadow:inset 0 0 0 2px rgba(166,67,43,.12)}
.rte-canvas h1,.rte-canvas h2,.rte-canvas h3{color:var(--ink);margin:22px 0 10px;line-height:1.35}
.rte-canvas h2{font-size:21px}
.rte-canvas h3{font-size:17px}
.rte-canvas p{margin:0 0 12px}
.rte-canvas ul,.rte-canvas ol{margin:0 0 14px;padding-left:22px}
.rte-canvas blockquote{border-left:3px solid var(--line);margin:14px 0;padding:4px 0 4px 14px;color:var(--muted)}
.rte-canvas pre{background:var(--bg);border:1px solid var(--line);padding:12px;overflow:auto;font:13px/1.6 ui-monospace,Consolas,monospace}
.rte-canvas code{background:var(--bg);padding:1px 4px;font:13px/1.5 ui-monospace,Consolas,monospace}
.rte-canvas img{max-width:100%;height:auto;display:block;margin:14px 0}
.rte-canvas figure{margin:18px 0}
.rte-canvas figcaption{font-size:12px;color:var(--muted);margin-top:6px;text-align:center}
.rte-canvas a{color:var(--accent)}
.rte-status{font-size:12px;color:var(--muted);padding:6px 10px;border-top:1px solid var(--line);min-height:27px}
.media-box{display:flex;gap:12px;flex-wrap:wrap;align-items:center;border:1px dashed var(--line);padding:12px;background:var(--bg)}
.media-box input[type=file]{width:auto;background:transparent;border:0;padding:0}
</style>
<style>
:root{
  --bg:#f4f4f2;--card:#fff;--ink:#18181b;--muted:#71717a;--line:#e4e4e7;
  --accent:#b0492f;--accent-dark:#933a24;--accent-soft:#f9ece8;
  --ok:#15803d;--ok-soft:#f0fdf4;--err:#b91c1c;--err-soft:#fef2f2;
  --ring:0 0 0 3px rgba(176,73,47,.15);--shadow:0 1px 2px rgba(24,24,27,.04),0 8px 24px rgba(24,24,27,.05)
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.65 "Inter","Noto Sans SC",-apple-system,"Segoe UI","Microsoft YaHei",sans-serif;-webkit-font-smoothing:antialiased}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
.wrap{max-width:1100px;margin:0 auto;padding:0 24px}
.shell{display:grid;grid-template-columns:236px minmax(0,1fr);min-height:100vh;background:var(--bg)}
.side{position:sticky;top:0;height:100vh;display:flex;flex-direction:column;gap:22px;padding:24px 16px;border-right:1px solid var(--line);background:#fbfbfa}
.side-brand{display:flex;flex-direction:column;gap:5px;padding:6px 10px 14px;text-decoration:none;color:var(--ink);font-weight:650;font-size:16px;letter-spacing:-.01em;line-height:1.35}
.side-brand::before{content:"";width:26px;height:26px;border-radius:8px;background:linear-gradient(135deg,var(--accent),#d0805f);margin-bottom:8px;box-shadow:0 3px 10px rgba(176,73,47,.25)}
.side-brand span{display:block;color:var(--muted);font-size:10px;font-weight:500;letter-spacing:.14em;text-transform:uppercase}
.side-nav{display:flex;flex-direction:column;gap:3px}
.side-nav a{display:flex;align-items:center;gap:9px;padding:9px 12px;color:var(--muted);text-decoration:none;font-size:13.5px;font-weight:500;border-radius:8px;transition:background .15s,color .15s}
.side-nav a:hover{color:var(--ink);background:#f1f1ef;text-decoration:none}
.side-nav a.on{color:var(--accent);background:var(--accent-soft);font-weight:600}
.side-nav a.on::before{content:"";width:3px;height:16px;border-radius:3px;background:var(--accent);margin-left:-6px}
.side-foot{margin-top:auto;padding-top:14px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:4px;font-size:12.5px}
.side-foot a,.linkbtn{color:var(--muted);text-decoration:none;padding:7px 10px;border-radius:7px;text-align:left}
.side-foot a:hover,.linkbtn:hover{color:var(--ink);background:#f1f1ef;text-decoration:none}
.main{width:100%;max-width:1180px;padding:32px 40px 64px}
h1{font-size:28px;line-height:1.25;margin:8px 0 6px;font-weight:650;letter-spacing:-.025em}
h2{font-size:16px;margin:0 0 10px;font-weight:600;letter-spacing:-.01em}
.sub{color:var(--muted);margin:0 0 24px;font-size:13.5px;max-width:72ch}
.flash{padding:12px 16px;border:1px solid #bbf7d0;background:var(--ok-soft);margin:16px 0;border-radius:10px;color:#166534;font-size:13.5px}
.flash.error{border-color:#fecaca;background:var(--err-soft);color:var(--err)}
.card{background:var(--card);border:1px solid var(--line);padding:22px 24px;margin:16px 0;border-radius:12px;box-shadow:var(--shadow)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 17px;background:var(--accent);color:#fff;text-decoration:none;border:1px solid var(--accent);cursor:pointer;font:600 13.5px/1.4 inherit;border-radius:8px;transition:background .15s,border-color .15s,box-shadow .15s,transform .1s;white-space:nowrap}
.btn:hover{background:var(--accent-dark);border-color:var(--accent-dark);text-decoration:none;box-shadow:0 2px 8px rgba(176,73,47,.22)}
.btn:active{transform:translateY(1px);box-shadow:none}
.btn:focus-visible{outline:0;box-shadow:var(--ring)}
.btn--ghost{background:#fff;color:var(--ink);border-color:var(--line)}
.btn--ghost:hover{background:#f7f7f5;border-color:#d4d4d8;color:var(--ink);box-shadow:0 1px 3px rgba(24,24,27,.06)}
.btn--danger{background:#fff;color:var(--err);border-color:#fecaca}
.btn--danger:hover{background:var(--err-soft);border-color:#fca5a5;color:var(--err);box-shadow:none}
.btn--primary{background:var(--accent);border-color:var(--accent);color:#fff}
.btn:disabled,.btn[disabled]{opacity:.55;cursor:not-allowed;box-shadow:none;transform:none}
.small{font-size:12.5px;padding:6px 11px;border-radius:7px}
.stats{display:flex;gap:10px;margin:6px 0 4px;color:var(--muted);flex-wrap:wrap}
.stats>div{background:#fafaf8;border:1px solid var(--line);border-radius:9px;padding:9px 14px;min-width:82px;font-size:12px}
.stats b{display:block;color:var(--ink);font-size:21px;line-height:1.2;font-weight:650;letter-spacing:-.03em;margin-bottom:2px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-size:11px;color:var(--muted);font-weight:600;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;background:#fafaf8}
th:first-child,td:first-child{padding-left:14px}
tbody tr{transition:background .12s}
tbody tr:hover{background:#fafaf8}
tbody tr:last-child td{border-bottom:0}
td.form-actions{display:table-cell !important;white-space:nowrap;text-align:right;width:124px;min-width:124px;margin:0;padding:12px 10px;border-top:0}
td.form-actions form{display:inline}
td.form-actions .btn{margin-left:4px}
.slug{color:var(--muted);font-size:11.5px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;background:#f4f4f2;padding:2px 6px;border-radius:5px}
.pill{display:inline-block;padding:3px 9px;border:1px solid var(--line);font-size:11.5px;color:var(--muted);border-radius:999px;white-space:nowrap;background:#fff;line-height:1.5}
.pill.live{color:var(--ok);border-color:#bbf7d0;background:var(--ok-soft)}
.pill.draft{color:#a16207;border-color:#fde68a;background:#fffbeb}
form.inline{display:inline;margin:0}
label{display:block;font-size:12.5px;font-weight:600;color:#3f3f46;margin:16px 0 6px;letter-spacing:.01em}
label span{color:var(--muted);font-weight:400}
input[type=text],input[type=password],input[type=date],input[type=number],input[type=email],select,textarea{width:100%;padding:10px 12px;border:1px solid var(--line);background:#fff;font:13.5px/1.5 inherit;color:var(--ink);border-radius:8px;transition:border-color .15s,box-shadow .15s}
input:focus,select:focus,textarea:focus{outline:0;border-color:var(--accent);box-shadow:var(--ring)}
input:disabled{background:#f4f4f2;color:var(--muted);cursor:not-allowed}
input[type=file]{font-size:13px}
textarea{resize:vertical}
textarea.code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;line-height:1.7;min-height:340px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 24px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:0 20px}
.hint{font-size:12px;color:var(--muted);margin:6px 0 0;line-height:1.55}
.footer{color:var(--muted);font-size:12px;padding:30px 0 20px;border-top:1px solid var(--line);margin-top:32px}
.bar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-top:28px;flex-wrap:wrap}
.form-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:22px;padding-top:18px;border-top:1px solid var(--line)}
.media-box{display:flex;gap:12px;flex-wrap:wrap;align-items:center;border:1px dashed #d4d4d8;padding:14px;background:#fafaf8;border-radius:9px}
.media-box input[type=file]{width:auto;background:transparent;border:0;padding:0}
.rte{border:1px solid var(--line);background:#fff;border-radius:10px;overflow:hidden;margin-top:6px}
.rte-toolbar{display:flex;flex-wrap:wrap;gap:3px;padding:8px;border-bottom:1px solid var(--line);background:#fafaf8;position:sticky;top:0;z-index:2}
.rte-toolbar button{background:transparent;border:1px solid transparent;color:#3f3f46;padding:5px 10px;font:500 12.5px/1.5 inherit;cursor:pointer;border-radius:6px;transition:background .12s,border-color .12s}
.rte-toolbar button:hover{background:#fff;border-color:var(--line);color:var(--ink)}
.rte-toolbar button:focus-visible{outline:0;box-shadow:var(--ring)}
.rte-toolbar .sep{width:1px;background:var(--line);margin:3px 6px}
.rte-canvas{min-height:440px;padding:24px 26px;outline:none;overflow-wrap:anywhere;font-size:14.5px;line-height:1.8}
.rte-canvas:focus{box-shadow:inset 0 0 0 2px rgba(176,73,47,.1)}
.rte-canvas h2{font-size:22px;margin:26px 0 10px}
.rte-canvas h3{font-size:17px;margin:22px 0 8px}
.rte-canvas p{margin:0 0 13px}
.rte-canvas blockquote{border-left:3px solid var(--accent);margin:16px 0;padding:4px 0 4px 14px;color:var(--muted)}
.rte-canvas pre{background:#f6f6f4;border:1px solid var(--line);padding:14px;border-radius:8px;overflow:auto;font:12.5px/1.7 ui-monospace,Consolas,monospace}
.rte-canvas code{background:#f4f4f2;padding:2px 5px;border-radius:4px;font:12.5px/1.5 ui-monospace,Consolas,monospace}
.rte-canvas img{max-width:100%;height:auto;display:block;margin:16px 0;border-radius:8px}
.rte-canvas figure{margin:20px 0}
.rte-canvas figcaption{font-size:12px;color:var(--muted);margin-top:7px;text-align:center}
.rte-status{font-size:12px;color:var(--muted);padding:8px 12px;border-top:1px solid var(--line);min-height:31px;background:#fafaf8}
.login-wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:48px 24px;background:radial-gradient(circle at 50% 0%,#fff 0%,var(--bg) 60%)}
.login{max-width:400px;width:100%;padding:34px 32px;border-radius:16px;box-shadow:0 12px 40px rgba(24,24,27,.08);border:1px solid var(--line);background:#fff}
.login h1{font-size:22px;margin:0 0 4px}
.login .sub{margin-bottom:18px}
.login-wrap .flash{max-width:400px;width:100%;margin:0 0 14px}
.login .bar{margin-top:20px}
@media(max-width:900px){.main{padding:28px 26px 56px}}
@media(max-width:760px){
  .shell{grid-template-columns:1fr}
  .side{position:static;height:auto;flex-direction:row;align-items:center;flex-wrap:wrap;gap:10px;padding:12px 16px;border-right:0;border-bottom:1px solid var(--line)}
  .side-brand{flex-direction:row;align-items:center;gap:9px;font-size:14px;padding:4px 6px}
  .side-brand::before{width:22px;height:22px;margin:0}
  .side-brand span{display:none}
  .side-nav{flex-direction:row;flex-wrap:wrap;gap:2px}
  .side-nav a{padding:6px 9px;font-size:12.5px}
  .side-nav a.on::before{display:none}
  .side-foot{margin-top:0;border-top:0;flex-direction:row;padding-top:0;margin-left:auto}
  .side-foot a,.linkbtn{padding:5px 6px}
  .main{padding:24px 18px 48px}
  h1{font-size:24px}
  .card{padding:18px;border-radius:10px}
  .grid2,.grid3{grid-template-columns:1fr}
  td.form-actions{white-space:normal;text-align:left}
  .rte-canvas{padding:18px;min-height:360px}
}
.memo-grid{display:grid;grid-template-columns:360px minmax(0,1fr);gap:22px;align-items:start}
.memo-form{margin:0;position:sticky;top:16px}
.memo-list-head{display:flex;justify-content:space-between;align-items:baseline;gap:12px;padding:4px 2px 10px}
.memo-list-head strong{font-size:14px}
.memo-card{margin:0 0 12px;padding:18px 20px;border-left:3px solid var(--line)}
.memo-card.is-pinned{border-left-color:var(--accent);background:linear-gradient(180deg,#fffdfb,#fff)}
.memo-card-head{display:flex;align-items:center;gap:8px;justify-content:space-between}
.memo-card-head h3{margin:0;font-size:15px;font-weight:650;letter-spacing:-.01em}
.memo-content{margin:10px 0 14px;font-size:13.5px;line-height:1.75;color:#3f3f46;overflow-wrap:anywhere}
.memo-card-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;padding-top:12px;border-top:1px solid var(--line);flex-wrap:wrap}
.memo-card-foot .hint{margin:0}
.memo-empty{color:var(--muted);text-align:center;padding:34px 20px}
.memo-widget{margin-top:18px}
.memo-quick{margin-top:12px;padding:12px;border:1px solid var(--line);border-radius:9px;background:#fafaf8}
.memo-quick textarea{margin:0;min-height:58px;background:#fff;resize:vertical}
.memo-quick .row{margin-top:8px}
.memo-mini-list{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:14px}
.memo-mini{display:flex;flex-direction:column;gap:3px;padding:10px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;transition:border-color .12s,background .12s}
.memo-mini:hover{border-color:#d4d4d8;background:#fafaf8;text-decoration:none}
.memo-mini-title{font-size:12.5px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.memo-mini-body{font-size:12px;color:var(--muted);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.memo-mini-date{font-size:11px;color:#a1a1aa}
.memo-topbar{position:sticky;top:0;z-index:80;height:48px;background:linear-gradient(90deg,#26231f,#332c24);color:#f7f2e7;border-bottom:1px solid rgba(255,255,255,.08);box-shadow:0 2px 10px rgba(0,0,0,.12)}
.memo-topbar-inner{height:100%;display:flex;align-items:center;gap:14px;padding:0 22px;overflow:hidden}
.memo-topbar-label{display:flex;align-items:center;gap:7px;font-size:11.5px;font-weight:600;letter-spacing:.08em;color:#e8c9a8;white-space:nowrap;text-transform:uppercase}
.memo-topbar-label::before{content:"★";font-size:12px;color:#e0a86b}
.memo-topbar-items{display:flex;align-items:center;gap:8px;overflow-x:auto;scrollbar-width:thin;flex:1;min-width:0;padding:4px 0}
.memo-topbar-item{display:flex;align-items:baseline;gap:8px;max-width:420px;padding:5px 11px;border-radius:6px;background:rgba(255,255,255,.07);color:#fff;text-decoration:none;white-space:nowrap;overflow:hidden}
.memo-topbar-item:hover{background:rgba(255,255,255,.13);text-decoration:none}
.memo-topbar-item strong{font-size:12.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;flex-shrink:0;max-width:180px}
.memo-topbar-item span{font-size:12px;color:#cfc6b8;overflow:hidden;text-overflow:ellipsis}
.memo-topbar-all{color:#e8c9a8;font-size:12px;white-space:nowrap;text-decoration:none;padding:5px 9px;border:1px solid rgba(232,201,168,.3);border-radius:6px}
.memo-topbar-all:hover{background:rgba(232,201,168,.12);text-decoration:none}
.shell.with-memo-bar .side{top:48px;height:calc(100vh - 48px)}
@media(max-width:760px){.memo-topbar-inner{padding:0 14px;gap:10px}.memo-topbar-item span{display:none}.memo-topbar-item strong{max-width:160px}.shell.with-memo-bar .side{top:0;height:auto}}
@media(max-width:980px){.memo-grid{grid-template-columns:1fr}.memo-form{position:static}.memo-mini-list{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php if ($loggedIn): ?>
<?php if ($pinnedMemos): ?>
<div class="memo-topbar" aria-label="置顶备忘录">
  <div class="memo-topbar-inner">
    <span class="memo-topbar-label">置顶备忘 · <?= count($pinnedMemos) ?></span>
    <div class="memo-topbar-items">
      <?php foreach ($pinnedMemos as $memo): ?>
        <?php $miniTitle = $memo['title'] !== '' ? $memo['title'] : memo_excerpt($memo['content'], 26); ?>
        <a class="memo-topbar-item" href="admin.php?view=memo&amp;id=<?= (int) $memo['id'] ?>" title="<?= h(memo_excerpt($memo['content'], 120)) ?>">
          <strong><?= h($miniTitle) ?></strong>
          <?php if ($memo['title'] !== ''): ?><span><?= h(memo_excerpt($memo['content'], 60)) ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <a class="memo-topbar-all" href="admin.php?view=memos">管理</a>
  </div>
</div>
<?php endif; ?>
<div class="shell <?= $pinnedMemos ? 'with-memo-bar' : '' ?>">
  <aside class="side">
    <a class="side-brand" href="admin.php"><?= h(cfg('site.name')) ?><span>发布后台</span></a>
    <nav class="side-nav">
      <a class="<?= $view === 'dashboard' ? 'on' : '' ?>" href="admin.php">概览</a>
      <a class="<?= $view === 'post' ? 'on' : '' ?>" href="admin.php#posts">文章</a>
      <a class="<?= $view === 'work' ? 'on' : '' ?>" href="admin.php#works">作品</a>
      <a class="<?= $view === 'categories' || $view === 'category' ? 'on' : '' ?>" href="admin.php?view=categories">分类</a>
      <a class="<?= $view === 'memos' || $view === 'memo' ? 'on' : '' ?>" href="admin.php?view=memos">备忘录</a>
      <a class="<?= $view === 'settings' ? 'on' : '' ?>" href="admin.php?view=settings">站点设置</a>
      <a class="<?= $view === 'account' ? 'on' : '' ?>" href="admin.php?view=account">账户与密码</a>
    </nav>
    <div class="side-foot">
      <a href="<?= h(cfg('site.base_url')) ?>" target="_blank" rel="noopener">查看前台 ↗</a>
      <form method="post" action="admin.php?action=logout"><?= csrf_field() ?><button class="linkbtn" type="submit">退出登录</button></form>
    </div>
  </aside>
  <main class="main">
<?php else: ?>
<div class="login-wrap">
<?php endif; ?>
<?php if ($flash): ?>
<div class="flash <?= $flash['type'] === 'error' ? 'error' : '' ?>"><?= h($flash['message']) ?></div>
<?php endif; ?>
<?php if ($dbError): ?>
<div class="flash error">
  <strong>数据库还没连上。</strong>
  请检查 <code>app/config.php</code> 里的 <code>db</code> 配置，并确认数据库已创建、<code>install.php</code> 已执行过。
  <br><small><?= h($dbError) ?></small>
</div>
<?php endif; ?>

<?php if (!$loggedIn): ?>
  <div class="login card">
    <h1 style="margin-top:0;font-size:22px">登录后台</h1>
    <p class="sub">输入密码后可以管理文章、作品并生成静态站。</p>
    <form method="post" action="admin.php?action=login">
      <?= csrf_field() ?>
      <label for="username">账户</label>
      <input id="username" type="text" name="username" value="admin" autocomplete="username" required autofocus>
      <label for="password">密码</label>
      <input id="password" type="password" name="password" autocomplete="current-password" required>
      <p class="hint">密码提示：admin123</p>
      <div class="bar"><button class="btn" type="submit">登录</button><a class="small" href="<?= h(cfg('site.base_url')) ?>">返回站点</a></div>
    </form>
  </div>
<?php else: ?>

  <?php if ($view === 'post'): ?>
    <?php $p = $postEdit; ?>
    <h1><?= $p ? '编辑文章' : '新建文章' ?></h1>
    <p class="sub">正文是纯 HTML。发布状态的文章才会进入静态站、RSS 和 sitemap。</p>
    <form method="post" action="admin.php?action=save_post" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
      <input type="hidden" name="back" value="admin.php">
      <div class="grid2">
        <div>
          <label>标题</label>
          <input type="text" name="title" value="<?= h($p['title'] ?? '') ?>" required maxlength="200">
        </div>
        <div>
          <label>slug <span>（URL，小写字母/数字/连字符）</span></label>
          <input type="text" name="slug" value="<?= h($p['slug'] ?? '') ?>" required pattern="[a-z0-9][a-z0-9\-]{0,119}">
        </div>
        <div>
          <label>SEO 标题</label>
          <input type="text" name="seo_title" value="<?= h($p['seo_title'] ?? '') ?>" maxlength="240" placeholder="留空自动生成：标题 | 电脑小百科 · 技术笔记">
        </div>
        <div>
          <label>描述 <span>（meta description，建议 80–150 字）</span></label>
          <input type="text" name="description" value="<?= h($p['description'] ?? '') ?>" maxlength="400">
        </div>
      </div>
      <div class="grid3">
        <div>
          <label>eyebrow</label>
          <input type="text" name="eyebrow" value="<?= h($p['eyebrow'] ?? '') ?>" placeholder="PixiJS / 性能 · 项目笔记">
        </div>
        <div>
          <label>分类 <span>（在「分类」里管理）</span></label>
          <select name="category_id">
            <option value="0">未分类</option>
            <?php foreach ($allCategories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) ($p['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="hidden" name="section" value="<?= h($p['section'] ?? '') ?>">
        </div>
        <div>
          <label>标签 <span>（英文逗号分隔）</span></label>
          <input type="text" name="tags" value="<?= h(implode(', ', $p['tags'] ?? [])) ?>" placeholder="PixiJS, 性能">
        </div>
        <div>
          <label>关联作品 <span>（作品标题，可选）</span></label>
          <input type="text" name="project" value="<?= h($p['project'] ?? '') ?>" placeholder="像素数码工坊">
        </div>
        <div>
          <label>发布日期</label>
          <input type="date" name="published_at" value="<?= h($p['published_at'] ?? '') ?>">
        </div>
        <div>
          <label>更新日期</label>
          <input type="date" name="updated_on" value="<?= h($p['updated_on'] ?? '') ?>">
        </div>
        <div>
          <label>状态</label>
          <select name="status">
            <option value="draft" <?= ($p['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>草稿</option>
            <option value="published" <?= ($p['status'] ?? '') === 'published' ? 'selected' : '' ?>>已发布</option>
          </select>
        </div>
        <div>
          <label>关键词 keywords</label>
          <input type="text" name="keywords" value="<?= h($p['keywords'] ?? '') ?>">
        </div>
      </div>
      <label>正文 <span>（所见即所得，可直接排版、插图；保存时还原为 HTML）</span></label>
      <div class="media-box" style="margin-bottom:10px">
        <strong style="font-size:13px">插入图片</strong>
        <input type="file" id="inlineFile" data-inline-images multiple accept="image/jpeg,image/png,image/gif,image/webp">
        <span class="hint">可一次选多张（每张 ≤ 5 MB），会插到正文光标处。</span>
      </div>
      <textarea class="code" name="body_html" data-rte required spellcheck="false"><?= h($p['body_html'] ?? '') ?></textarea>
      <div class="bar">
        <div class="row"><button class="btn" type="submit">保存文章</button><a class="btn btn--ghost" href="admin.php">取消</a></div>
        <?php if (!empty($p['published_at'])): ?>
        <a class="small" href="<?= h(cfg('site.base_url') . '/posts/' . $p['slug'] . '.html') ?>" target="_blank" rel="noopener">查看线上页面 ↗</a>
        <?php endif; ?>
      </div>
    </form>

  <?php elseif ($view === 'work'): ?>
    <?php $w = $workEdit; ?>
    <h1><?= $w ? '编辑作品' : '新建作品' ?></h1>
    <p class="sub">作品页用于介绍项目，列表里会展示英文名、三个要点和右侧状态。</p>
    <form method="post" action="admin.php?action=save_work" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($w['id'] ?? 0) ?>">
      <input type="hidden" name="back" value="admin.php">
      <div class="grid2">
        <div>
          <label>名称</label>
          <input type="text" name="title" value="<?= h($w['title'] ?? '') ?>" required>
        </div>
        <div>
          <label>slug</label>
          <input type="text" name="slug" value="<?= h($w['slug'] ?? '') ?>" required pattern="[a-z0-9][a-z0-9\-]{0,119}">
        </div>
        <div>
          <label>英文名 / 副标题</label>
          <input type="text" name="en" value="<?= h($w['en'] ?? '') ?>">
        </div>
        <div>
          <label>eyebrow</label>
          <input type="text" name="eyebrow" value="<?= h($w['eyebrow'] ?? '') ?>" placeholder="Works / Desktop · 2025 —">
        </div>
        <div>
          <label>一句话简介 lede</label>
          <input type="text" name="lede" value="<?= h($w['lede'] ?? '') ?>" maxlength="500">
        </div>
        <div>
          <label>描述 <span>（列表与 meta description）</span></label>
          <input type="text" name="description" value="<?= h($w['description'] ?? '') ?>" maxlength="400">
        </div>
      </div>
      <div class="grid3">
        <div>
          <label>状态文案</label>
          <input type="text" name="status_label" value="<?= h($w['status_label'] ?? '') ?>" placeholder="v2.1 · 已发布">
        </div>
        <div>
          <label>状态颜色</label>
          <select name="status_kind">
            <option value="" <?= ($w['status_kind'] ?? '') === '' ? 'selected' : '' ?>>无</option>
            <option value="live" <?= ($w['status_kind'] ?? '') === 'live' ? 'selected' : '' ?>>绿（已发布 / 开源）</option>
            <option value="wip" <?= ($w['status_kind'] ?? '') === 'wip' ? 'selected' : '' ?>>橙（进行中）</option>
          </select>
        </div>
        <div>
          <label>图示</label>
          <select name="mark_key">
            <?php foreach (['generic' => '通用', 'winlite' => '窗口', 'eq' => '波形', 'pixel' => '像素屏'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($w['mark_key'] ?? 'generic') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>周期</label>
          <input type="text" name="period" value="<?= h($w['period'] ?? '') ?>" placeholder="2025 —">
        </div>
        <div>
          <label>关键数字</label>
          <input type="text" name="metric" value="<?= h($w['metric'] ?? '') ?>" placeholder="空闲内存 38 MB">
        </div>
        <div>
          <label>排序 <span>（越小越前）</span></label>
          <input type="number" name="sort_order" value="<?= (int) ($w['sort_order'] ?? 0) ?>">
        </div>
      </div>
      <div class="grid2">
        <div>
          <label>要点 points <span>（每行一条，列表用）</span></label>
          <textarea name="points" rows="5"><?= h(implode("\n", $w['points'] ?? [])) ?></textarea>
        </div>
        <div>
          <label>meta chips <span>（每行一条，详情页标题下）</span></label>
          <textarea name="meta_chips" rows="5"><?= h(implode("\n", $w['meta_chips'] ?? [])) ?></textarea>
        </div>
        <div>
          <label>参数表 specs <span>（每行：名称 | 内容）</span></label>
          <textarea name="specs" rows="6"><?php foreach (($w['specs'] ?? []) as $spec): ?><?= h(($spec['label'] ?? '') . ' | ' . ($spec['value'] ?? '')) . "\n" ?><?php endforeach; ?></textarea>
        </div>
        <div>
          <label>SEO 标题</label>
          <input type="text" name="seo_title" value="<?= h($w['seo_title'] ?? '') ?>">
          <label>应用类型 app_type</label>
          <select name="app_type">
            <?php foreach (['SoftwareApplication' => '软件', 'VideoGame' => '游戏', 'WebApplication' => '网页应用'] as $k => $label): ?>
            <option value="<?= $k ?>" <?= ($w['app_type'] ?? '') === $k ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
          <label>应用分类 application_category</label>
          <input type="text" name="application_category" value="<?= h($w['application_category'] ?? '') ?>" placeholder="UtilityApplication / GameApplication">
          <label>系统 operating_system</label>
          <input type="text" name="operating_system" value="<?= h($w['operating_system'] ?? '') ?>" placeholder="Windows 10 / 11">
          <label>版本 version</label>
          <input type="text" name="version" value="<?= h($w['version'] ?? '') ?>">
        </div>
      </div>

      <div class="grid2">
        <div>
          <label>作品封面 <span>（列表和详情页顶部展示，可选）</span></label>
          <input type="hidden" name="cover_image" id="coverPath" value="<?= h($w['cover_image'] ?? '') ?>">
          <div class="row">
            <input type="file" id="coverFile" accept="image/jpeg,image/png,image/gif,image/webp" style="width:auto">
            <button class="btn btn--ghost small" type="button" id="coverClear">移除</button>
          </div>
          <div id="coverPreview" style="margin-top:10px">
            <?php if (!empty($w['cover_image'])): ?>
            <img src="/<?= h($w['cover_image']) ?>" alt="" style="max-width:240px;border:1px solid var(--line);display:block">
            <?php endif; ?>
          </div>
          <p class="hint">建议 16:10 横图，至少 800px 宽；JPG / PNG / WebP，5 MB 以内。</p>
        </div>
        <div>
          <label>往正文插入多张图片</label>
          <div class="media-box">
            <input type="file" id="inlineFile" data-inline-images multiple accept="image/jpeg,image/png,image/gif,image/webp">
          </div>
          <p class="hint">可一次选多张，插到正文光标处；上传后说明文字可直接改。图片存在 <code>app/assets/uploads/works</code>，发布时随 assets 一起复制。</p>
        </div>
      </div>

      <label>正文 <span>（所见即所得，保存时还原为 HTML）</span></label>
      <textarea class="code" name="body_html" data-rte required spellcheck="false"><?= h($w['body_html'] ?? '') ?></textarea>
      <label>相关段落 HTML <span>（详情页「相关」区块，可选）</span></label>
      <textarea name="related_html" rows="4"><?= h($w['related_html'] ?? '') ?></textarea>
      <div class="bar">
        <div class="row">
          <button class="btn" type="submit">保存作品</button>
          <a class="btn btn--ghost" href="admin.php">取消</a>
          <label style="margin:0;display:flex;gap:6px;align-items:center;color:var(--ink)"><input type="checkbox" name="is_published" value="1" <?= !empty($w['is_published']) || !$w ? 'checked' : '' ?>> 前台显示</label>
        </div>
        <?php if (!empty($w['slug'])): ?>
        <a class="small" href="<?= h(cfg('site.base_url') . '/works/' . $w['slug'] . '.html') ?>" target="_blank" rel="noopener">查看线上页面 ↗</a>
        <?php endif; ?>
      </div>
    </form>

    <script>
    (function () {
      var form = document.querySelector('form[action="admin.php?action=save_work"]');
      if (!form) return;
      var csrf = form.querySelector('input[name="_csrf"]').value;
      var coverPath = document.getElementById('coverPath');
      var preview = document.getElementById('coverPreview');
      var body = form.querySelector('textarea[name="body_html"]');
      var status = document.createElement('p');
      status.className = 'hint';
      status.style.marginTop = '8px';

      function upload(file) {
        var fd = new FormData();
        fd.append('file', file);
        fd.append('_csrf', csrf);
        return fetch('admin.php?action=upload_image', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (d) { if (!d.ok) throw new Error(d.error || '上传失败'); return d; });
      }

      document.getElementById('coverFile').addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        status.textContent = '封面上传中…';
        upload(file).then(function (d) {
          coverPath.value = d.path;
          preview.innerHTML = '<img src="' + d.url + '" alt="" style="max-width:240px;border:1px solid var(--line);display:block">';
          status.textContent = '封面已上传：' + d.path;
        }).catch(function (err) { status.textContent = err.message; });
      });

      document.getElementById('coverClear').addEventListener('click', function () {
        coverPath.value = '';
        preview.innerHTML = '';
        status.textContent = '封面已移除，保存后生效。';
      });

      form.appendChild(status);
    })();
    </script>

  <?php elseif ($view === 'memos' || $view === 'memo'): ?>
    <?php $isEditing = !empty($memoEdit); ?>
    <h1>备忘录</h1>
    <p class="sub">只保存在后台数据库，不会出现在前台。可以置顶、修改和删除，适合记待办、临时想法和发布前检查项。</p>
    <div class="memo-grid">
      <form method="post" action="admin.php?action=save_memo" class="card memo-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) ($memoEdit['id'] ?? 0) ?>">
        <h2><?= $isEditing ? '编辑备忘录' : '新建备忘录' ?></h2>
        <label>标题 <span>（可选）</span></label>
        <input type="text" name="title" maxlength="120" value="<?= h($memoEdit['title'] ?? '') ?>" placeholder="例如：发布前检查">
        <label>内容</label>
        <textarea name="content" rows="9" required placeholder="记一条待办、想法或提醒…"><?= h($memoEdit['content'] ?? '') ?></textarea>
        <label style="display:flex;gap:8px;align-items:center;margin-top:14px">
          <input type="checkbox" name="pinned" value="1" style="width:auto" <?= !empty($memoEdit['pinned']) ? 'checked' : '' ?>>
          置顶这条备忘录
        </label>
        <div class="form-actions">
          <button class="btn btn--primary" type="submit"><?= $isEditing ? '保存修改' : '添加备忘录' ?></button>
          <?php if ($isEditing): ?><a class="btn btn--ghost" href="admin.php?view=memos">取消编辑</a><?php endif; ?>
        </div>
      </form>

      <div class="memo-list">
        <div class="memo-list-head">
          <strong>全部备忘录</strong>
          <span class="hint">共 <?= (int) $memoStats['total'] ?> 条 · 置顶 <?= (int) $memoStats['pinned'] ?> 条</span>
        </div>
        <?php if (!$allMemos): ?>
          <div class="card memo-empty">还没有备忘录，从左边写一条开始。</div>
        <?php endif; ?>
        <?php foreach ($allMemos as $memo): ?>
          <article class="card memo-card <?= !empty($memo['pinned']) ? 'is-pinned' : '' ?>">
            <div class="memo-card-head">
              <h3><?= h($memo['title'] !== '' ? $memo['title'] : '未命名备忘录') ?></h3>
              <?php if (!empty($memo['pinned'])): ?><span class="pill live">置顶</span><?php endif; ?>
            </div>
            <div class="memo-content"><?= nl2br(h($memo['content'])) ?></div>
            <div class="memo-card-foot">
              <span class="hint">更新于 <?= h(date('Y-m-d H:i', strtotime((string) $memo['updated_at']))) ?></span>
              <div class="row" style="gap:6px">
                <form method="post" action="admin.php?action=toggle_memo" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $memo['id'] ?>"><button class="btn btn--ghost small" type="submit"><?= !empty($memo['pinned']) ? '取消置顶' : '置顶' ?></button></form>
                <a class="btn btn--ghost small" href="admin.php?view=memo&amp;id=<?= (int) $memo['id'] ?>">编辑</a>
                <form method="post" action="admin.php?action=delete_memo" class="inline" onsubmit="return confirm('删除这条备忘录？')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $memo['id'] ?>"><button class="btn btn--danger small" type="submit">删除</button></form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>

  <?php elseif ($view === 'categories'): ?>
    <h1>分类</h1>
    <p class="sub">分类用于给文章归档，文章编辑页可以选。删除分类不会删除文章，只会把文章变成未分类。</p>
    <div class="bar" style="margin-top:0">
      <span class="hint">共 <?= count($allCategories) ?> 个分类</span>
      <a class="btn btn--ghost small" href="admin.php?view=category">新建分类</a>
    </div>
    <div class="card" style="padding:0">
      <table>
        <thead><tr><th>名称</th><th>slug</th><th>描述</th><th>文章</th><th>排序</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allCategories as $c): ?>
          <tr>
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><span class="slug"><?= h($c['slug']) ?></span></td>
            <td><?= h($c['description']) ?></td>
            <td><?= (int) $c['post_count'] ?> 篇</td>
            <td><?= (int) $c['sort_order'] ?></td>
            <td class="form-actions">
              <a class="btn btn--ghost small" href="admin.php?view=category&amp;id=<?= (int) $c['id'] ?>">编辑</a>
              <form class="inline" method="post" action="admin.php?action=delete_category" onsubmit="return confirm('确定删除这个分类？文章不会被删除，会变成未分类。')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button class="btn btn--danger small" type="submit">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allCategories): ?>
          <tr><td colspan="6" class="hint">还没有分类，点右上角新建一个。</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="footer">分类顺序会用于文章归档与筛选排序。</div>

  <?php elseif ($view === 'category'): ?>
    <?php $cat = $categoryEdit; ?>
    <h1><?= $cat ? '编辑分类' : '新建分类' ?></h1>
    <p class="sub">slug 留空会自动生成；分类名修改后，已归类文章的栏目文字会一起更新。</p>
    <form method="post" action="admin.php?action=save_category" class="card">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($cat['id'] ?? 0) ?>">
      <div class="grid2">
        <div>
          <label>分类名</label>
          <input type="text" name="name" value="<?= h($cat['name'] ?? '') ?>" required maxlength="60">
        </div>
        <div>
          <label>slug <span>（留空自动生成，如 cat-4a336d）</span></label>
          <input type="text" name="slug" value="<?= h($cat['slug'] ?? '') ?>" pattern="[a-z0-9][a-z0-9\-]{0,79}" placeholder="自动生成">
        </div>
      </div>
      <label>描述 <span>（可留空，用于后台识别）</span></label>
      <input type="text" name="description" value="<?= h($cat['description'] ?? '') ?>" maxlength="400">
      <label>排序 <span>（越小越前）</span></label>
      <input type="number" name="sort_order" value="<?= (int) ($cat['sort_order'] ?? 0) ?>">
      <div class="bar">
        <div class="row">
          <button class="btn" type="submit">保存分类</button>
          <a class="btn btn--ghost" href="admin.php?view=categories">取消</a>
        </div>
      </div>
    </form>

  <?php elseif ($view === 'account'): ?>
    <?php $account = admin_account(); ?>
    <h1>账户与密码</h1>
    <p class="sub">用于登录后台的账户名和密码。修改后立即生效，已登录的会话保持有效。</p>
    <form method="post" action="admin.php?action=save_account" class="card form-card">
      <?= csrf_field() ?>
      <div class="grid2">
        <div>
          <label>当前账户</label>
          <input type="text" value="<?= h($account['username']) ?>" disabled>
        </div>
        <div>
          <label>当前密码 <span>（必填，用于确认身份）</span></label>
          <input type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <div>
          <label>新账户名</label>
          <input type="text" name="username" value="<?= h($account['username']) ?>" required pattern="[A-Za-z0-9_.-]{3,40}" autocomplete="username">
          <p class="hint">3–40 位字母、数字、下划线、点或短横线。</p>
        </div>
        <div>
          <label>新密码 <span>（留空则不修改）</span></label>
          <input type="password" name="new_password" autocomplete="new-password" minlength="8">
          <p class="hint">至少 8 位；建议字母、数字、符号混合。</p>
        </div>
        <div>
          <label>确认新密码</label>
          <input type="password" name="confirm_password" autocomplete="new-password" minlength="8">
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn--primary" type="submit">保存账户</button>
        <a class="btn btn--ghost" href="admin.php">返回概览</a>
      </div>
    </form>

  <?php elseif ($view === 'settings'): ?>
    <h1>站点设置</h1>
    <p class="sub">导航、页脚、首页 Logbook 与各页 SEO 都存在数据库里。保存后需要点一次「生成静态站」。</p>
    <form method="post" action="admin.php?action=save_settings" class="card">
      <?= csrf_field() ?>

      <h2>顶部导航</h2>
      <p class="hint">每行：<code>key | 标签 | 链接</code>。key 用于高亮当前页（home / about / blog / column / works），外部链接填 <code>-</code>。链接可写 <code>blog.html</code>、<code>/blog.html</code> 或完整 <code>https://…</code>。</p>
      <textarea name="nav_rows" rows="7" spellcheck="false"><?php foreach ($settings['nav'] as $item): ?><?= h(($item['key'] ?: '-') . ' | ' . $item['label'] . ' | ' . $item['url']) . "\n" ?><?php endforeach; ?></textarea>

      <h2 style="margin-top:28px">联系方式</h2>
      <p class="hint">这里保存后，页脚和「关于」页的联系方式会一起更新；点一次「生成静态站」生效。</p>
      <div class="grid3">
        <div>
          <label>邮箱</label>
          <input type="email" name="contact_email" value="<?= h($settings['contact']['email'] ?? '') ?>" placeholder="hello@example.com">
        </div>
        <div>
          <label>GitHub</label>
          <input type="text" name="contact_github" value="<?= h($settings['contact']['github'] ?? '') ?>" placeholder="https://github.com/…">
        </div>
        <div>
          <label>微博</label>
          <input type="text" name="contact_weibo" value="<?= h($settings['contact']['weibo'] ?? '') ?>" placeholder="https://weibo.com/u/…">
        </div>
        <div>
          <label>微信 <span>（填微信号，前台只显示文字）</span></label>
          <input type="text" name="contact_wechat" value="<?= h($settings['contact']['wechat'] ?? '') ?>" placeholder="微信号">
        </div>
        <div>
          <label>QQ <span>（填号码，前台只显示文字）</span></label>
          <input type="text" name="contact_qq" value="<?= h($settings['contact']['qq'] ?? '') ?>" placeholder="QQ 号">
        </div>
        <div>
          <label>B站</label>
          <input type="text" name="contact_bilibili" value="<?= h($settings['contact']['bilibili'] ?? '') ?>" placeholder="https://space.bilibili.com/…">
        </div>
      </div>
      <label>其他联系链接 <span>（每行：标签 | 链接，可留空）</span></label>
      <textarea name="contact_extra" rows="3" spellcheck="false"><?php foreach (($settings['contact']['extra'] ?? []) as $item): ?><?= h(($item['label'] ?? '') . ' | ' . ($item['url'] ?? '')) . "\n" ?><?php endforeach; ?></textarea>

      <h2 style="margin-top:28px">页脚</h2>
      <div class="grid2">
        <div>
          <label>简介</label>
          <textarea name="footer_intro" rows="3"><?= h($settings['footer']['intro']) ?></textarea>
          <label>目录链接 <span>（每行：标签 | 链接）</span></label>
          <textarea name="footer_links" rows="5" spellcheck="false"><?php foreach ($settings['footer']['links'] as $link): ?><?= h($link['label'] . ' | ' . $link['url']) . "\n" ?><?php endforeach; ?></textarea>
        </div>
        <div>
          <label>版权文字 <span>（留空自动：© 起始年–今年 作者；可用 {site} / {author}）</span></label>
          <input type="text" name="footer_copyright" value="<?= h($settings['footer']['copyright']) ?>">
          <label>备案号 <span>（留空不显示）</span></label>
          <input type="text" name="footer_icp" value="<?= h($settings['footer']['icp']) ?>" placeholder="粤ICP备xxxxxxxx号">
          <label>底栏说明 1</label>
          <input type="text" name="footer_note" value="<?= h($settings['footer']['note']) ?>">
          <label>底栏说明 2</label>
          <input type="text" name="footer_host" value="<?= h($settings['footer']['host']) ?>">
        </div>
      </div>

      <h2 style="margin-top:28px">首页 Logbook</h2>
      <p class="hint">每行：<code>YYYY-MM-DD | 事项</code>。按填写顺序滚动展示，建议 6–10 条。</p>
      <textarea name="logbook_rows" rows="9" spellcheck="false"><?php foreach ($settings['logbook'] as $item): ?><?= h($item['date'] . ' | ' . $item['text']) . "\n" ?><?php endforeach; ?></textarea>

      <h2 style="margin-top:28px">SEO</h2>
      <div class="grid2">
        <div>
          <label>全站关键词 keywords <span>（英文逗号分隔，留空不输出）</span></label>
          <input type="text" name="seo_keywords" value="<?= h($settings['seo']['keywords']) ?>" placeholder="Windows 工具, PixiJS, 像素游戏">
          <label>分享图 og:image <span>（站内路径或完整 URL）</span></label>
          <input type="text" name="seo_og_image" value="<?= h($settings['seo']['og_image']) ?>" placeholder="assets/og.png">
        </div>
        <div>
          <label>文章标题模板</label>
          <input type="text" name="seo_post_title_tpl" value="<?= h($settings['seo']['post_title_tpl']) ?>">
          <label>作品标题模板</label>
          <input type="text" name="seo_work_title_tpl" value="<?= h($settings['seo']['work_title_tpl']) ?>">
          <p class="hint">可用占位：<code>{title}</code>、<code>{site}</code>、<code>{author}</code>。文章/作品单独填了 SEO 标题时优先用它。</p>
        </div>
      </div>

      <?php
      $seoPages = [
          'home' => '首页', 'blog' => '博客列表', 'works' => '作品列表',
          'about' => '关于页', 'column' => '专栏页',
      ];
      foreach ($seoPages as $key => $label): ?>
      <div class="grid2" style="margin-top:16px">
        <div>
          <label><?= $label ?> · 标题 <span>（留空用模板默认）</span></label>
          <input type="text" name="seo_<?= $key ?>_title" value="<?= h($settings['seo'][$key . '_title'] ?? '') ?>" placeholder="<?= h(cfg('site.name') . ' — …') ?>">
        </div>
        <div>
          <label><?= $label ?> · 描述</label>
          <input type="text" name="seo_<?= $key ?>_desc" value="<?= h($settings['seo'][$key . '_desc']) ?>" maxlength="400">
        </div>
      </div>
      <?php endforeach; ?>

      <div class="bar">
        <div class="row"><button class="btn" type="submit">保存设置</button><a class="btn btn--ghost" href="admin.php">返回</a></div>
        <span class="hint">关于页、专栏页保存后会改写它们的 title / description / canonical。</span>
      </div>
    </form>

  <?php else: ?>
    <h1>内容管理</h1>
    <p class="sub">文章和作品都存在 MySQL 里。改完点「生成静态站」，再把 <code>dist/</code> 同步到 GitHub Pages。</p>

    <div class="card">
      <div class="row" style="justify-content:space-between">
        <div class="stats">
          <div><b><?= (int) $stats['posts'] ?></b>已发布文章</div>
          <div><b><?= (int) $stats['drafts'] ?></b>草稿</div>
          <div><b><?= (int) $stats['works'] ?></b>作品</div>
          <div><b><?= (int) ($stats['categories'] ?? 0) ?></b>分类</div>
        </div>
        <div class="row">
          <a class="btn btn--ghost" href="admin.php?view=settings">站点设置</a>
          <form method="post" action="admin.php?action=import_full" class="inline" onsubmit="return confirm('全量导入会用 seed.json 覆盖同名文章和作品（其他内容保留），并重新生成静态站。继续？')">
            <?= csrf_field() ?>
            <button class="btn btn--ghost" type="submit" title="从 seed.json 重新导入全部示例内容">全量导入</button>
          </form>
          <form method="post" action="admin.php?action=publish" class="inline">
            <?= csrf_field() ?>
            <button class="btn" type="submit">生成静态站</button>
          </form>
        </div>
      </div>
      <p class="hint">上次生成：<?= $lastPublish ? date('Y-m-d H:i:s', $lastPublish) : '还没有生成过' ?>　输出目录：<?= h(dist_path()) ?></p>
    </div>

    <div class="card memo-widget">
      <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
          <h2>备忘录</h2>
          <p class="hint" style="margin-top:2px">后台随手记，不会出现在前台。共 <?= (int) $memoStats['total'] ?> 条，置顶 <?= (int) $memoStats['pinned'] ?> 条。</p>
        </div>
        <a class="btn btn--ghost small" href="admin.php?view=memos">全部备忘录</a>
      </div>
      <form method="post" action="admin.php?action=save_memo" class="memo-quick">
        <?= csrf_field() ?>
        <textarea name="content" rows="2" required placeholder="记一条待办或想法，Ctrl+Enter 快速保存…"></textarea>
        <div class="row" style="justify-content:space-between">
          <label style="display:flex;gap:7px;align-items:center;margin:0"><input type="checkbox" name="pinned" value="1" style="width:auto"> 置顶</label>
          <button class="btn small" type="submit">添加</button>
        </div>
      </form>
      <?php if ($allMemos): ?>
      <div class="memo-mini-list">
        <?php foreach ($allMemos as $memo): ?>
          <a class="memo-mini" href="admin.php?view=memo&amp;id=<?= (int) $memo['id'] ?>">
            <span class="memo-mini-title"><?= h($memo['title'] !== '' ? $memo['title'] : memo_excerpt($memo['content'], 28)) ?></span>
            <span class="memo-mini-body"><?= h(memo_excerpt($memo['content'], 80)) ?></span>
            <span class="memo-mini-date"><?= h(date('m-d H:i', strtotime((string) $memo['updated_at']))) ?><?= !empty($memo['pinned']) ? ' · 置顶' : '' ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <p class="hint">还没有备忘录。</p>
      <?php endif; ?>
    </div>

    <div class="bar" style="margin-top:26px" id="posts">
      <h2>文章（<?= count($allPosts) ?>）</h2>
      <a class="btn btn--ghost small" href="admin.php?view=post">新建文章</a>
    </div>
    <div class="card" style="padding:0">
      <table>
        <thead><tr><th>标题</th><th>分类</th><th>日期</th><th>状态</th><th>字数</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allPosts as $p): ?>
          <tr>
            <td><strong><?= h($p['title']) ?></strong><br><span class="slug">/posts/<?= h($p['slug']) ?>.html</span></td>
            <td><?= h($catNames[(int) $p['category_id']] ?? '未分类') ?></td>
            <td><?= h($p['published_at'] ? fmt_date($p['published_at']) : '—') ?></td>
            <td><span class="pill <?= $p['status'] === 'published' ? 'live' : 'draft' ?>"><?= $p['status'] === 'published' ? '已发布' : '草稿' ?></span></td>
            <td><?= number_format((int) $p['word_count']) ?></td>
            <td class="form-actions">
              <a class="btn btn--ghost small" href="admin.php?view=post&amp;id=<?= (int) $p['id'] ?>">编辑</a>
              <form class="inline" method="post" action="admin.php?action=delete_post" onsubmit="return confirm('确定删除这篇文章？该操作不可撤销。')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <button class="btn btn--danger small" type="submit">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allPosts): ?>
          <tr><td colspan="6" class="hint">还没有文章。点「新建文章」或运行安装脚本导入现有文章。</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="bar" style="margin-top:34px" id="works">
      <h2>作品（<?= count($allWorks) ?>）</h2>
      <a class="btn btn--ghost small" href="admin.php?view=work">新建作品</a>
    </div>
    <div class="card" style="padding:0">
      <table>
        <thead><tr><th>名称</th><th>周期</th><th>状态</th><th>排序</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allWorks as $w): ?>
          <tr>
            <td><strong><?= h($w['title']) ?></strong> <span class="slug"><?= h($w['en']) ?></span><br><span class="slug">/works/<?= h($w['slug']) ?>.html</span></td>
            <td><?= h($w['period']) ?></td>
            <td><span class="pill <?= $w['is_published'] ? 'live' : 'draft' ?>"><?= $w['is_published'] ? '显示' : '隐藏' ?></span> <?= h($w['status_label']) ?></td>
            <td><?= (int) $w['sort_order'] ?></td>
            <td class="form-actions">
              <a class="btn btn--ghost small" href="admin.php?view=work&amp;id=<?= (int) $w['id'] ?>">编辑</a>
              <form class="inline" method="post" action="admin.php?action=delete_work" onsubmit="return confirm('确定删除这个作品？该操作不可撤销。')">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
                <button class="btn btn--danger small" type="submit">删除</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allWorks): ?>
          <tr><td colspan="5" class="hint">还没有作品。</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="bar" style="margin-top:34px">
      <h2>分类（<?= count($allCategories) ?>）</h2>
      <div class="row">
        <a class="btn btn--ghost small" href="admin.php?view=categories">管理分类</a>
        <a class="btn btn--ghost small" href="admin.php?view=category">新建分类</a>
      </div>
    </div>
    <div class="card" style="padding:0">
      <table>
        <thead><tr><th>名称</th><th>slug</th><th>文章</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($allCategories as $c): ?>
          <tr>
            <td><strong><?= h($c['name']) ?></strong><?= $c['description'] !== '' ? '<br><span class="slug">' . h($c['description']) . '</span>' : '' ?></td>
            <td><span class="slug"><?= h($c['slug']) ?></span></td>
            <td><?= (int) $c['post_count'] ?> 篇</td>
            <td class="form-actions"><a class="btn btn--ghost small" href="admin.php?view=category&amp;id=<?= (int) $c['id'] ?>">编辑</a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$allCategories): ?>
          <tr><td colspan="4" class="hint">还没有分类。文章里的「栏目」会在迁移时自动变成分类。</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="footer">
      后台路径：<?= h($_SERVER['SCRIPT_NAME'] ?? 'admin.php') ?>　·　建议安装完成后删除 <code>install.php</code>，并定期备份数据库。
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php if ($loggedIn): ?>
  </main>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script>
(function () {
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function mount(ta) {
    var form = ta.closest('form');
    if (!form) return;

    var wrap = document.createElement('div');
    wrap.className = 'rte';
    var bar = document.createElement('div');
    bar.className = 'rte-toolbar';
    var canvas = document.createElement('div');
    canvas.className = 'rte-canvas';
    canvas.contentEditable = 'true';
    canvas.innerHTML = ta.value;
    canvas.setAttribute('role', 'textbox');
    canvas.setAttribute('aria-multiline', 'true');
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.multiple = true;
    fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
    fileInput.style.display = 'none';
    var statusEl = document.createElement('div');
    statusEl.className = 'rte-status';
    statusEl.textContent = '支持粘贴、拖入图片；工具条可改标题 / 加粗 / 列表 / 链接。';

    ta.style.display = 'none';
    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar);
    wrap.appendChild(canvas);
    wrap.appendChild(fileInput);
    wrap.appendChild(statusEl);
    wrap.appendChild(ta);

    var lastRange = null;

    function remember() {
      var sel = window.getSelection();
      if (!sel || !sel.rangeCount) return;
      var r = sel.getRangeAt(0);
      if (canvas.contains(r.commonAncestorContainer)) lastRange = r.cloneRange();
    }
    document.addEventListener('selectionchange', remember);
    canvas.addEventListener('mouseup', remember);
    canvas.addEventListener('keyup', remember);

    function restore() {
      var sel = window.getSelection();
      sel.removeAllRanges();
      if (lastRange) sel.addRange(lastRange);
    }

    function sync() {
      ta.value = canvas.innerHTML;
    }

    function exec(cmd, value) {
      canvas.focus();
      restore();
      document.execCommand(cmd, false, value || null);
      remember();
      sync();
    }

    function insertHtml(html) {
      canvas.focus();
      var sel = window.getSelection();
      sel.removeAllRanges();
      if (lastRange) {
        sel.addRange(lastRange);
      } else {
        var r = document.createRange();
        r.selectNodeContents(canvas);
        r.collapse(false);
        sel.addRange(r);
      }
      document.execCommand('insertHTML', false, html);
      remember();
      sync();
    }

    function wrapSelection(before, after) {
      var sel = window.getSelection();
      var text = sel && sel.rangeCount && canvas.contains(sel.anchorNode) ? sel.toString() : '';
      insertHtml(before + (text ? esc(text) : '内容') + after);
    }

    function csrf() {
      var input = form.querySelector('input[name="_csrf"]');
      return input ? input.value : '';
    }

    function insertFiles(files) {
      var list = Array.prototype.slice.call(files || []);
      if (!list.length) return;
      var htmls = [];
      var fails = 0;
      var done = 0;
      statusEl.textContent = '正在上传 0/' + list.length + '…';
      var chain = Promise.resolve();
      list.forEach(function (file, index) {
        chain = chain.then(function () {
          var fd = new FormData();
          fd.append('file', file);
          fd.append('_csrf', csrf());
          return fetch('admin.php?action=upload_image', { method: 'POST', body: fd })
            .then(function (res) { return res.json(); })
            .then(function (data) {
              if (!data.ok) throw new Error(data.error || '上传失败');
              htmls.push(data.html);
              done++;
              statusEl.textContent = '已上传 ' + done + '/' + list.length + '…';
            })
            .catch(function (err) {
              fails++;
              statusEl.textContent = '第 ' + (index + 1) + ' 张失败：' + err.message;
            });
        });
      });
      chain.then(function () {
        if (htmls.length) {
          insertHtml(htmls.join('\n'));
          statusEl.textContent = '已插入 ' + htmls.length + ' 张图片' + (fails ? '，失败 ' + fails + ' 张' : '');
        } else {
          statusEl.textContent = '没有图片插入' + (fails ? '（全部失败）' : '');
        }
      });
    }

    var buttons = [
      ['H2', 'h2', 'formatBlock', '<h2>', '二级标题'],
      ['H3', 'h3', 'formatBlock', '<h3>', '三级标题'],
      ['正文', 'p', 'formatBlock', '<p>', '普通段落'],
      ['sep'],
      ['B', 'bold', 'bold', null, '加粗'],
      ['I', 'italic', 'italic', null, '斜体'],
      ['代码', 'code', null, null, '行内代码'],
      ['链接', 'link', null, null, '插入链接'],
      ['sep'],
      ['• 列表', 'ul', 'insertUnorderedList', null, '无序列表'],
      ['1. 列表', 'ol', 'insertOrderedList', null, '有序列表'],
      ['引用', 'quote', 'formatBlock', '<blockquote>', '引用'],
      ['sep'],
      ['图片', 'image', null, null, '上传并插入图片（可多选）'],
      ['撤销', 'undo', 'undo', null, '撤销'],
      ['重做', 'redo', 'redo', null, '重做'],
    ];

    buttons.forEach(function (item) {
      if (item[0] === 'sep') {
        var sep = document.createElement('span');
        sep.className = 'sep';
        bar.appendChild(sep);
        return;
      }
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = item[0];
      btn.title = item[4];
      btn.addEventListener('click', function () {
        var cmd = item[2];
        if (item[1] === 'code') { wrapSelection('<code>', '</code>'); return; }
        if (item[1] === 'link') {
          var sel = window.getSelection();
          var text = sel && sel.rangeCount ? sel.toString() : '';
          var href = window.prompt('链接地址（可填相对路径，如 ../posts/xxx.html）', 'https://');
          if (!href) return;
          if (text) {
            exec('createLink', href);
          } else {
            insertHtml('<a href="' + esc(href).replace(/"/g, '%22') + '">链接文字</a>');
          }
          return;
        }
        if (item[1] === 'image') { fileInput.click(); return; }
        exec(cmd, item[3]);
      });
      bar.appendChild(btn);
    });

    var sourceBtn = document.createElement('button');
    sourceBtn.type = 'button';
    sourceBtn.textContent = '源码';
    sourceBtn.title = '在 HTML 源码和可视化之间切换';
    var sourceMode = false;
    sourceBtn.addEventListener('click', function () {
      sourceMode = !sourceMode;
      if (sourceMode) {
        canvas.style.display = 'none';
        ta.style.display = '';
        ta.classList.add('rte-source-on');
        sourceBtn.textContent = '可视化';
        statusEl.textContent = '源码模式：直接编辑 HTML，切回可视化时生效。';
      } else {
        ta.style.display = 'none';
        canvas.style.display = '';
        canvas.innerHTML = ta.value;
        sourceBtn.textContent = '源码';
        statusEl.textContent = '已切回可视化。';
      }
    });
    bar.appendChild(sourceBtn);

    fileInput.addEventListener('change', function () {
      insertFiles(fileInput.files);
      fileInput.value = '';
    });

    canvas.addEventListener('input', sync);
    canvas.addEventListener('blur', sync);
    canvas.addEventListener('paste', function (e) {
      if (!e.clipboardData || !e.clipboardData.items) return;
      var files = [];
      for (var i = 0; i < e.clipboardData.items.length; i++) {
        var item = e.clipboardData.items[i];
        if (item.kind === 'file' && item.type.indexOf('image/') === 0) {
          var f = item.getAsFile();
          if (f) files.push(f);
        }
      }
      if (files.length) {
        e.preventDefault();
        insertFiles(files);
      }
    });
    form.addEventListener('submit', function () {
      if (!sourceMode) sync();
    });

    ta._rte = { insertFiles: insertFiles, sync: sync };
  }

  document.querySelectorAll('textarea[data-rte]').forEach(mount);

  document.querySelectorAll('[data-inline-images]').forEach(function (input) {
    input.addEventListener('change', function () {
      var ta = input.form ? input.form.querySelector('textarea[data-rte]') : null;
      if (ta && ta._rte) ta._rte.insertFiles(input.files);
      input.value = '';
    });
  });

  // 拖图片进正文也能直接上传
  document.querySelectorAll('.rte-canvas').forEach(function (canvas) {
    canvas.addEventListener('dragover', function (e) { e.preventDefault(); });
    canvas.addEventListener('drop', function (e) {
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      e.preventDefault();
      var ta = canvas.parentNode.querySelector('textarea[data-rte]');
      if (ta && ta._rte) ta._rte.insertFiles(e.dataTransfer.files);
    });
  });
})();
</script>
<script>
document.querySelectorAll('.memo-quick textarea').forEach(function (ta) {
  ta.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      if (ta.form) ta.form.requestSubmit ? ta.form.requestSubmit() : ta.form.submit();
    }
  });
});
</script>
</body>
</html>
