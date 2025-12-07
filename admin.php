<?php
declare(strict_types=1);

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);
if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0777, true);

function load_json(string $file, array $default = []): array {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}
function save_json(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function upload_file(string $field, string $uploadsDir): ?string {
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $name = basename($_FILES[$field]['name']);
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $safe = uniqid('u_', true) . ($ext? ".{$ext}" : '');
    $dest = $uploadsDir . DIRECTORY_SEPARATOR . $safe;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return 'uploads/' . $safe;
    }
    return null;
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmt_price($p): string {
    $s = trim((string)$p);
    if ($s === '') return '';
    $s = preg_replace('/\s*(₺|tl)\s*/iu', '', $s);
    return trim($s) . ' ₺';
}
function str_limit(string $s, int $l): string {
    if (function_exists('mb_substr')) return mb_substr($s, 0, $l, 'UTF-8');
    return substr($s, 0, $l);
}

$configFile = $dataDir . DIRECTORY_SEPARATOR . 'config.json';
$catsFile = $dataDir . DIRECTORY_SEPARATOR . 'categories.json';
$menuFile = $dataDir . DIRECTORY_SEPARATOR . 'menu.json';

$config = load_json($configFile, [
    'site_title' => 'Menü',
    'logo' => '',
    'favicon' => '',
    'social' => ['facebook' => '', 'instagram' => '', 'twitter' => '']
]);
$categories = load_json($catsFile, []);
$menu = load_json($menuFile, []);

session_start();
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (empty($_SESSION['admin'])) {
        if ($action === 'login') {
            $pwd = (string)($_POST['password'] ?? '');
            $hash = (string)($config['admin_password_hash'] ?? '');
            if ($hash !== '' && password_verify($pwd, $hash)) {
                $_SESSION['admin'] = true;
                header('Location: admin.php'); exit;
            } else {
                $login_error = 'Hatalı şifre';
            }
        } elseif ($action === 'set_admin_password') {
            $hash = (string)($config['admin_password_hash'] ?? '');
            if ($hash === '') {
                $pwd = trim((string)($_POST['password'] ?? ''));
                if ($pwd !== '') {
                    $config['admin_password_hash'] = password_hash($pwd, PASSWORD_DEFAULT);
                    save_json($configFile, $config);
                    $_SESSION['admin'] = true;
                    header('Location: admin.php'); exit;
                } else {
                    $login_error = 'Şifre girin';
                }
            }
        } else {
            header('Location: admin.php'); exit;
        }
    } else {
        if ($action === 'logout') { unset($_SESSION['admin']); header('Location: admin.php'); exit; }
    }
}

$hasPwd = (string)($config['admin_password_hash'] ?? '') !== '';
if (empty($_SESSION['admin'])) {
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Giriş</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>.admin-wrap{max-width:420px;margin:40px auto;padding:16px}.admin-card{background:#fff;border-radius:12px;padding:16px;box-shadow:0 2px 10px rgba(0,0,0,.06)}.row{display:grid;gap:8px}input[type=password]{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px}.btn{padding:10px 14px;border-radius:8px;border:none;background:#111;color:#fff}.error{color:#c00;margin-top:8px}</style>
    </head>
    <body>
        <div class="admin-wrap">
            <div class="admin-card">
                <h2><?php echo $hasPwd? 'Admin Giriş':'Admin Şifre Oluştur'; ?></h2>
                <form method="post" class="row">
                    <input type="hidden" name="action" value="<?php echo $hasPwd? 'login':'set_admin_password'; ?>">
                    <input type="password" name="password" placeholder="Şifre">
                    <button class="btn" type="submit"><?php echo $hasPwd? 'Giriş':'Kaydet'; ?></button>
                    <?php if ($login_error): ?><div class="error"><?php echo h($login_error); ?></div><?php endif; ?>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$tab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'dashboard';
$tab = in_array($tab, ['dashboard','kategoriler','menuler','ayarlar'], true) ? $tab : 'dashboard';
$catCount = count($categories);
$itemCount = count($menu);
$favCount = 0; foreach ($menu as $it) { if (!empty($it['favorite'])) $favCount++; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_settings') {
        $config['site_title'] = trim((string)($_POST['site_title'] ?? $config['site_title']));
        $logoPath = upload_file('logo', $uploadsDir);
        if ($logoPath) $config['logo'] = $logoPath;
        $favPath = upload_file('favicon', $uploadsDir);
        if ($favPath) $config['favicon'] = $favPath;
        $config['social']['facebook'] = trim((string)($_POST['facebook'] ?? ''));
        $config['social']['instagram'] = trim((string)($_POST['instagram'] ?? ''));
        $config['social']['twitter'] = trim((string)($_POST['twitter'] ?? ''));
        $config['wifi_password'] = trim((string)($_POST['wifi_password'] ?? (string)($config['wifi_password'] ?? '')));
        $hb = (string)($_POST['header_bg'] ?? (string)($config['header_bg'] ?? ''));
        $fb = (string)($_POST['footer_bg'] ?? (string)($config['footer_bg'] ?? ''));
        if ($hb !== '') $config['header_bg'] = $hb;
        if ($fb !== '') $config['footer_bg'] = $fb;
        save_json($configFile, $config);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
        header('Location: ' . $loc); exit;
    }
    if ($action === 'clear_cache') {
        $cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { if (is_file($f)) @unlink($f); }
        }
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'change_admin_password') {
        $current = trim((string)($_POST['current'] ?? ''));
        $pwd = trim((string)($_POST['password'] ?? ''));
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        $hash = (string)($config['admin_password_hash'] ?? '');
        $ok = false; $msg = '';
        if ($pwd === '' || $confirm === '') {
            $msg = 'Yeni şifre alanları boş olamaz';
        } elseif ($pwd !== $confirm) {
            $msg = 'Yeni şifre ile tekrarı eşleşmiyor';
        } elseif ($hash === '' || password_verify($current, $hash)) {
            $config['admin_password_hash'] = password_hash($pwd, PASSWORD_DEFAULT);
            save_json($configFile, $config);
            $ok = true; $msg = 'Şifre güncellendi';
        } else {
            $msg = 'Mevcut şifre doğru değil';
        }
        $_SESSION['flash'] = ['type' => $ok? 'success':'error', 'msg' => $msg];
        $ret = (string)($_POST['ret'] ?? ''); $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'add_category') {
        $name = trim((string)($_POST['cat_name'] ?? ''));
        $image = upload_file('cat_image', $uploadsDir);
        $layout = in_array(($_POST['cat_layout'] ?? 'two'), ['full','two','vertical'], true) ? (string)$_POST['cat_layout'] : 'two';
        if ($name !== '') {
            $id = uniqid('c');
            $categories[] = ['id' => $id, 'name' => $name, 'image' => $image ?? '', 'visible' => true, 'layout' => $layout];
            save_json($catsFile, $categories);
        }
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'toggle_category') {
        $id = (string)($_POST['id'] ?? '');
        foreach ($categories as &$cat) {
            if ((string)$cat['id'] === $id) { $cat['visible'] = !empty($cat['visible']) ? false : true; }
        }
        unset($cat);
        save_json($catsFile, $categories);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'set_category_layout') {
        $id = (string)($_POST['id'] ?? '');
        $layout = in_array(($_POST['layout'] ?? 'two'), ['full','two','vertical'], true) ? (string)$_POST['layout'] : 'two';
        foreach ($categories as &$cat) {
            if ((string)$cat['id'] === $id) { $cat['layout'] = $layout; }
        }
        unset($cat);
        save_json($catsFile, $categories);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'edit_category') {
        $id = (string)($_POST['id'] ?? '');
        foreach ($categories as &$cat) {
            if ((string)$cat['id'] === $id) {
                $name = trim((string)($_POST['cat_name'] ?? (string)$cat['name']));
                $layout = in_array(($_POST['cat_layout'] ?? (string)($cat['layout'] ?? 'two')), ['full','two','vertical'], true) ? (string)$_POST['cat_layout'] : (string)($cat['layout'] ?? 'two');
                $visible = !empty($_POST['visible']);
                $img = upload_file('edit_cat_image', $uploadsDir);
                $cat['name'] = $name;
                $cat['layout'] = $layout;
                $cat['visible'] = $visible;
                if ($img) { $cat['image'] = $img; }
                break;
            }
        }
        unset($cat);
        save_json($catsFile, $categories);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'delete_category') {
        $id = (string)($_POST['id'] ?? '');
        $categories = array_values(array_filter($categories, function($c) use ($id){ return (string)($c['id'] ?? '') !== $id; }));
        $menu = array_values(array_filter($menu, function($m) use ($id){ return (string)($m['category_id'] ?? '') !== $id; }));
        save_json($catsFile, $categories);
        save_json($menuFile, $menu);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'add_item') {
        $name = trim((string)($_POST['item_name'] ?? ''));
        $price = trim((string)($_POST['item_price'] ?? ''));
        $cid = (string)($_POST['item_category'] ?? '');
        $desc = str_limit(trim((string)($_POST['item_desc'] ?? '')), 80);
        $vn = isset($_POST['item_var_name']) && is_array($_POST['item_var_name']) ? $_POST['item_var_name'] : [];
        $vp = isset($_POST['item_var_price']) && is_array($_POST['item_var_price']) ? $_POST['item_var_price'] : [];
        $variants = [];
        $maxc = max(count($vn), count($vp));
        for ($i=0; $i<$maxc; $i++) {
            $n = trim((string)($vn[$i] ?? ''));
            $p = trim((string)($vp[$i] ?? ''));
            if ($n !== '') { $variants[] = ['name' => $n, 'price' => $p]; }
        }
        $img = upload_file('item_image', $uploadsDir);
        if ($name !== '' && $cid !== '') {
            $id = uniqid('m');
            $menu[] = ['id' => $id, 'name' => $name, 'price' => $price, 'image' => $img ?? '', 'category_id' => $cid, 'favorite' => !empty($_POST['item_favorite']), 'desc' => $desc, 'variants' => $variants];
            save_json($menuFile, $menu);
        }
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'edit_item') {
        $id = (string)($_POST['id'] ?? '');
        foreach ($menu as &$it) {
            if ((string)$it['id'] === $id) {
                $name = trim((string)($_POST['item_name'] ?? (string)$it['name']));
                $price = trim((string)($_POST['item_price'] ?? (string)$it['price']));
                $cid = (string)($_POST['item_category'] ?? (string)$it['category_id']);
                $fav = !empty($_POST['item_favorite']);
                $desc = str_limit(trim((string)($_POST['item_desc'] ?? (string)($it['desc'] ?? ''))), 80);
                $vn = isset($_POST['edit_var_name']) && is_array($_POST['edit_var_name']) ? $_POST['edit_var_name'] : [];
                $vp = isset($_POST['edit_var_price']) && is_array($_POST['edit_var_price']) ? $_POST['edit_var_price'] : [];
                $variants = [];
                $maxc = max(count($vn), count($vp));
                for ($i=0; $i<$maxc; $i++) {
                    $n = trim((string)($vn[$i] ?? ''));
                    $p = trim((string)($vp[$i] ?? ''));
                    if ($n !== '') { $variants[] = ['name' => $n, 'price' => $p]; }
                }
                $img = upload_file('edit_item_image', $uploadsDir);
                $it['name'] = $name;
                $it['price'] = $price;
                $it['category_id'] = $cid;
                $it['favorite'] = $fav;
                $it['desc'] = $desc;
                $it['variants'] = $variants;
                if ($img) { $it['image'] = $img; }
                break;
            }
        }
        unset($it);
        save_json($menuFile, $menu);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
    if ($action === 'delete_item') {
        $id = (string)($_POST['id'] ?? '');
        $menu = array_values(array_filter($menu, function($m) use ($id){ return (string)($m['id'] ?? '') !== $id; }));
        save_json($menuFile, $menu);
        $ret = (string)($_POST['ret'] ?? '');
        $anchor = (string)($_POST['anchor'] ?? '');
        $loc = 'admin.php' . ($ret? ('?tab=' . urlencode($ret)) : '') . ($anchor? ('#' . rawurlencode($anchor)) : '');
        header('Location: ' . $loc); exit;
    }
}

?><!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .admin-wrap { max-width: 900px; margin: 0 auto; padding: 16px; }
        .admin-card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.06); }
        .admin-header { position: sticky; top: 0; z-index: 10; background: #fff; border-bottom: 1px solid #eee; }
        .admin-nav { display: flex; align-items: center; justify-content: space-between; max-width: 900px; margin: 0 auto; padding: 10px 16px; }
        .admin-left { display:flex; align-items:center; gap:8px; }
        .admin-tabs { display:flex; gap:10px; }
        .admin-tabs a { padding:8px 12px; border-radius:8px; text-decoration:none; color:#111; border:1px solid #eee; }
        .admin-tabs a.active { background:#111; color:#fff; border-color:#111; }
        .admin-actions { display:flex; align-items:center; gap:8px; }
        .icon-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; border:1px solid #eee; background:#fff; color:#111; text-decoration:none; }
        .menu-toggle { display:none; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .admin-list { display: grid; gap: 8px; }

        .row { display:flex; align-items:center; gap:8px; }
        .admin-table { width:100%; border-collapse: collapse; }
        .admin-table th, .admin-table td { border-bottom: 1px solid #eee; padding: 8px; text-align: left; }
        input[type=text], select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        input[type=file] { width: 100%; }
        .btn { padding: 10px 14px; border-radius: 8px; border: none; background:#111; color:#fff; }
        .thumb { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; background: #eee; display: inline-block; }
        .highlight-row td { background: #fff3cd !important; }
        .highlight-box { background: #fffbe6 !important; box-shadow: 0 0 0 2px #ffe08a inset; }
        
        @media (max-width: 640px) {
            .admin-left { flex-direction: row; align-items: center; }
            .grid-2 { grid-template-columns: 1fr; }
            .row { flex-direction: column; align-items: stretch; }
            .admin-wrap { padding: 12px; }
            .admin-card { padding: 12px; }
            .admin-table { font-size: 13px; }
            .admin-table th, .admin-table td { padding: 6px; }
            .thumb { width: 44px; height: 44px; }
            .admin-table form { grid-template-columns: 1fr !important; }
            .btn { width: 100%; }
            .table-responsive { overflow-x: auto; }
            .menu-toggle { display:inline-flex; }
            .admin-tabs { display:none; gap:8px; margin-top:0; flex-wrap: wrap; }
            body.admin-nav-open .admin-tabs { display:flex; }
            .admin-actions { justify-content: flex-end; }
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="admin-nav">
            <div class="admin-left">
                <a class="icon-btn menu-toggle" href="#" id="adminMenuToggle" title="Menüyü Aç/Kapat" aria-label="Menüyü Aç/Kapat">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/></svg>
                </a>
                <div class="admin-tabs">
                    <a href="admin.php?tab=dashboard" class="<?php echo $tab==='dashboard'? 'active':''; ?>">Dashboard</a>
                    <a href="admin.php?tab=kategoriler" class="<?php echo $tab==='kategoriler'? 'active':''; ?>">Kategoriler</a>
                    <a href="admin.php?tab=menuler" class="<?php echo $tab==='menuler'? 'active':''; ?>">Menü Öğeleri</a>
                    <a href="admin.php?tab=ayarlar" class="<?php echo $tab==='ayarlar'? 'active':''; ?>">Ayarlar</a>
                </div>
            <div class="admin-actions">
                <a class="icon-btn" href="index.php" title="Siteye Dön" aria-label="Siteye Dön">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 11l9-8 9 8v10a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-4H9v4a2 2 0 0 1-2 2H3V11z"/></svg>
                </a>
                <form method="post">
                    <input type="hidden" name="action" value="logout">
                    <button class="btn" type="submit">Çıkış</button>
                </form>
            </div>
        </div>
    </header>
    <div class="admin-wrap">
        <?php if ($tab==='dashboard'): ?>
        <div class="admin-card">
            <h2>Dashboard</h2>
            <div class="grid-2">
                <div class="admin-card" style="margin:0;">
                    <h3>Kategori Sayısı</h3>
                    <div><?php echo (int)$catCount; ?></div>
                </div>
                <div class="admin-card" style="margin:0;">
                    <h3>Menü Öğesi</h3>
                    <div><?php echo (int)$itemCount; ?></div>
                </div>
                <div class="admin-card" style="margin:0;">
                    <h3>Favoriler</h3>
                    <div><?php echo (int)$favCount; ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($tab==='ayarlar'): ?>
        <?php if (!empty($_SESSION['flash']) && $tab==='ayarlar'): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="admin-card" style="border-left:4px solid <?php echo ($f['type']==='success')? '#2d8a34':'#c00'; ?>;">
            <strong><?php echo h((string)$f['msg']); ?></strong>
        </div>
        <?php endif; ?>
        <div class="admin-card">
            <h2>Önbellek</h2>
            <form method="post" class="row">
                <input type="hidden" name="action" value="clear_cache">
                <input type="hidden" name="ret" value="ayarlar">
                <button class="btn" type="submit">Önbelleği Temizle</button>
            </form>
        </div>
        <div class="admin-card">
            <h2>Site Ayarları</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="ret" value="ayarlar">
                <div class="grid-2">
                    <div>
                        <label>Site Başlığı</label>
                        <input type="text" name="site_title" value="<?php echo h((string)$config['site_title']); ?>">
                    </div>
                    <div>
                        <label>Logo</label>
                        <input type="file" name="logo" accept="image/*">
                    </div>
                    <div>
                        <label>Favicon</label>
                        <input type="file" name="favicon" accept="image/*">
                    </div>
                </div>
                <div class="grid-2" style="margin-top:12px;">
                    <div>
                        <label>Facebook</label>
                        <input type="text" name="facebook" value="<?php echo h((string)$config['social']['facebook']); ?>">
                    </div>
                    <div>
                        <label>Instagram</label>
                        <input type="text" name="instagram" value="<?php echo h((string)$config['social']['instagram']); ?>">
                    </div>
                    <div>
                        <label>Twitter/X</label>
                        <input type="text" name="twitter" value="<?php echo h((string)$config['social']['twitter']); ?>">
                    </div>
                    <div>
                        <label>WiFi Şifresi</label>
                        <input type="text" name="wifi_password" value="<?php echo h((string)($config['wifi_password'] ?? '')); ?>">
                    </div>
                    <div>
                        <label>Header Arkaplan</label>
                        <input type="color" name="header_bg" value="<?php echo h((string)($config['header_bg'] ?? '#ffffff')); ?>">
                    </div>
                    <div>
                        <label>Footer Arkaplan</label>
                        <input type="color" name="footer_bg" value="<?php echo h((string)($config['footer_bg'] ?? '#ffffff')); ?>">
                    </div>
                </div>
                <div style="margin-top:12px;"><button class="btn" type="submit">Kaydet</button></div>
            </form>
        </div>
        <div class="admin-card">
            <h2>Admin Şifresi</h2>
            <form method="post" class="row">
                <input type="hidden" name="action" value="change_admin_password">
                <input type="hidden" name="ret" value="ayarlar">
                <input type="password" name="current" placeholder="Mevcut şifre">
                <input type="password" name="password" placeholder="Yeni şifre">
                <input type="password" name="confirm" placeholder="Şifre tekrarı">
                <button class="btn" type="submit">Güncelle</button>
            </form>
        </div>
        <?php endif; ?>

        

        <?php if ($tab==='kategoriler'): ?>
        <div class="admin-card" id="kategoriler">
            <h2>Kategoriler</h2>
            <form method="post" enctype="multipart/form-data" class="row">
                <input type="hidden" name="action" value="add_category">
                <input type="hidden" name="ret" value="kategoriler">
                <input type="hidden" name="anchor" value="kategoriler">
                <input type="text" name="cat_name" placeholder="Kategori adı">
                <input type="file" name="cat_image" accept="image/*">
                <select name="cat_layout">
                    <option value="two">Yan Yana</option>
                    <option value="full">Tam</option>
                    <option value="vertical">Dikey</option>
                </select>
                <button class="btn" type="submit">Ekle</button>
            </form>
            <div class="table-responsive">
            <table class="admin-table" style="margin-top:10px;">
                <thead><tr><th>Görsel</th><th>Ad</th><th>Görünür</th><th>Görünüm</th><th>İşlem</th></tr></thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr id="cat-<?php echo h((string)$cat['id']); ?>">
                            <td>
                                <?php if (!empty($cat['image'])): ?>
                                    <img class="thumb" src="<?php echo h((string)$cat['image']); ?>" alt="">
                                <?php else: ?>
                                    <span class="thumb"></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h((string)$cat['name']); ?></td>
                            <td><?php echo !empty($cat['visible'])? 'Evet':'Hayır'; ?></td>
                            <td><?php $l = ($cat['layout'] ?? 'two'); echo $l==='full' ? 'Tam' : ($l==='vertical' ? 'Dikey' : 'Yan Yana'); ?></td>
                            <td>
                                <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:6px;align-items:center;">
                                    <input type="hidden" name="action" value="edit_category">
                                    <input type="hidden" name="id" value="<?php echo h((string)$cat['id']); ?>">
                                    <input type="hidden" name="ret" value="kategoriler">
                                    <input type="hidden" name="anchor" value="cat-<?php echo h((string)$cat['id']); ?>">
                                    <input type="text" name="cat_name" value="<?php echo h((string)$cat['name']); ?>" placeholder="Ad">
                                    <select name="cat_layout">
                                        <option value="two" <?php echo (($cat['layout'] ?? 'two')==='two')? 'selected':''; ?>>Yan Yana</option>
                                        <option value="full" <?php echo (($cat['layout'] ?? 'two')==='full')? 'selected':''; ?>>Tam</option>
                                        <option value="vertical" <?php echo (($cat['layout'] ?? 'two')==='vertical')? 'selected':''; ?>>Dikey</option>
                                    </select>
                                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="visible" value="1" <?php echo !empty($cat['visible'])? 'checked':''; ?>> Görünür</label>
                                    <input type="file" name="edit_cat_image" accept="image/*">
                                    <button class="btn" type="submit">Güncelle</button>
                                </form>
                                <form method="post" style="display:inline;margin-top:6px;">
                                     <input type="hidden" name="action" value="delete_category">
                                     <input type="hidden" name="id" value="<?php echo h((string)$cat['id']); ?>">
                                     <input type="hidden" name="ret" value="kategoriler">
                                     <input type="hidden" name="anchor" value="kategoriler">
                                     <button class="btn" type="submit" onclick="return confirm('Kategori ve bağlı menüler silinecek. Onaylıyor musunuz?');">Sil</button>
                                 </form>
                             </td>
                         </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tab==='menuler'): ?>
        <div class="admin-card" id="menuler">
            <h2>Menü Öğeleri</h2>
            <form method="post" enctype="multipart/form-data" class="admin-list">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="ret" value="menuler">
                <input type="hidden" name="anchor" value="menuler">
                <div class="grid-2">
                    <div><input type="text" name="item_name" placeholder="Ad"></div>
                    <div><input type="text" name="item_price" placeholder="Fiyat"></div>
                    <div>
                        <select name="item_category">
                            <option value="">Kategori seçin</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo h((string)$cat['id']); ?>"><?php echo h((string)$cat['name']); ?></option>
                            <?php endforeach; ?>

                        </select>
                    </div>
                </div>
                <div class="row">
                    <input type="text" name="item_desc" placeholder="Açıklama" maxlength="80">
                    <input type="file" name="item_image" accept="image/*">
                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="item_favorite" value="1"> Favori</label>
                    <button class="btn" type="submit">Ekle</button>
                </div>
            </form>
            <div class="table-responsive">
            <table class="admin-table" style="margin-top:10px;">
                <thead><tr><th>Görsel</th><th>Ad</th><th>Fiyat</th><th>Kategori</th><th>Favori</th><th>İşlem</th></tr></thead>
                <tbody>
                    <?php foreach ($menu as $it): ?>
                        <tr id="item-<?php echo h((string)$it['id']); ?>">
                            <td>
                                <?php if (!empty($it['image'])): ?>
                                    <img class="thumb" src="<?php echo h((string)$it['image']); ?>" alt="">
                                <?php else: ?>
                                    <span class="thumb"></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h((string)$it['name']); ?></td>
                            <td><?php echo h(fmt_price((string)$it['price'])); ?></td>
                            <td>
                                <?php
                                $cn = '';
                                foreach ($categories as $cat) { if ((string)$cat['id'] === (string)$it['category_id']) { $cn = $cat['name']; break; } }
                                echo h((string)$cn);
                                ?>
                            </td>
                            <td><?php echo !empty($it['favorite'])? 'Evet':'Hayır'; ?></td>
                            <td>
                                <form method="post" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;align-items:center;">
                                    <input type="hidden" name="action" value="edit_item">
                                    <input type="hidden" name="id" value="<?php echo h((string)$it['id']); ?>">
                                    <input type="hidden" name="ret" value="menuler">
                                    <input type="hidden" name="anchor" value="item-<?php echo h((string)$it['id']); ?>">
                                    <input type="text" name="item_name" value="<?php echo h((string)$it['name']); ?>" placeholder="Ad">
                                    <input type="text" name="item_price" value="<?php echo h((string)$it['price']); ?>" placeholder="Fiyat">
                                    <input type="text" name="item_desc" value="<?php echo h((string)($it['desc'] ?? '')); ?>" placeholder="Açıklama" maxlength="80" style="grid-column:1/-1;">
                                    <div style="grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                                        <?php $vars = isset($it['variants']) && is_array($it['variants']) ? $it['variants'] : []; foreach ($vars as $v): ?>
                                            <input type="text" name="edit_var_name[]" value="<?php echo h((string)($v['name'] ?? '')); ?>" placeholder="Çeşit adı">
                                            <input type="text" name="edit_var_price[]" value="<?php echo h((string)($v['price'] ?? '')); ?>" placeholder="Çeşit fiyatı">
                                        <?php endforeach; ?>
                                        <input type="text" name="edit_var_name[]" value="" placeholder="Çeşit adı">
                                        <input type="text" name="edit_var_price[]" value="" placeholder="Çeşit fiyatı">
                                    </div>
                                    <select name="item_category">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo h((string)$cat['id']); ?>" <?php echo ((string)$cat['id'] === (string)$it['category_id'])? 'selected':''; ?>><?php echo h((string)$cat['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="item_favorite" value="1" <?php echo !empty($it['favorite'])? 'checked':''; ?>> Favori</label>
                                    <input type="file" name="edit_item_image" accept="image/*">
                                    <button class="btn" type="submit">Güncelle</button>
                                </form>
                                <form method="post" style="display:inline;margin-top:6px;">
                                    <input type="hidden" name="action" value="delete_item">
                                    <input type="hidden" name="id" value="<?php echo h((string)$it['id']); ?>">
                                    <input type="hidden" name="ret" value="menuler">
                                    <input type="hidden" name="anchor" value="menuler">
                                    <button class="btn" type="submit" onclick="return confirm('Menü öğesi silinecek, onaylıyor musunuz?');">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>

        
    </div>
    <script>
    (function(){
        var t = document.getElementById('adminMenuToggle');
        if (t) { t.addEventListener('click', function(e){ e.preventDefault(); document.body.classList.toggle('admin-nav-open'); }); }
        var h = location.hash ? location.hash.substring(1) : '';
        if (h) {
            var el = document.getElementById(h);
            if (el) {
                if (el.tagName && el.tagName.toLowerCase() === 'tr') {
                    el.classList.add('highlight-row');
                    setTimeout(function(){ el.classList.remove('highlight-row'); }, 1800);
                } else {
                    el.classList.add('highlight-box');
                    setTimeout(function(){ el.classList.remove('highlight-box'); }, 1800);
                }
            }
        }
    })();
    </script>
</body>
</html>
