<?php
declare(strict_types=1);

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$uploadsDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0777, true);
}

function load_json(string $file, array $default = []): array {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

$config = load_json($dataDir . DIRECTORY_SEPARATOR . 'config.json', [
    'site_title' => 'Menü',
    'logo' => '',
    'favicon' => '',
    'social' => [
        'facebook' => '',
        'instagram' => '',
        'twitter' => ''
    ]
]);
$categories = load_json($dataDir . DIRECTORY_SEPARATOR . 'categories.json', []);
$menu = load_json($dataDir . DIRECTORY_SEPARATOR . 'menu.json', []);

$cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($cacheDir)) { mkdir($cacheDir, 0777, true); }
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . 'home.html';
$cfgFile = $dataDir . DIRECTORY_SEPARATOR . 'config.json';
$catsFile = $dataDir . DIRECTORY_SEPARATOR . 'categories.json';
$menuFile = $dataDir . DIRECTORY_SEPARATOR . 'menu.json';
$latest = max(
    file_exists($cfgFile) ? (int)filemtime($cfgFile) : 0,
    file_exists($catsFile) ? (int)filemtime($catsFile) : 0,
    file_exists($menuFile) ? (int)filemtime($menuFile) : 0
);
if (file_exists($cacheFile) && (int)filemtime($cacheFile) >= $latest) {
    readfile($cacheFile);
    exit;
}
ob_start();
$lang = $_COOKIE['lang'] ?? 'tr';
$title = $config['site_title'] ?? 'Menü';
$logo = $config['logo'] ?? '';
$favicon = $config['favicon'] ?? '';
$social = $config['social'] ?? ['facebook' => '', 'instagram' => '', 'twitter' => ''];
$headerBg = (string)($config['header_bg'] ?? '');
$footerBg = (string)($config['footer_bg'] ?? '');

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmt_price($p): string {
    $s = trim((string)$p);
    if ($s === '') return '';
    $s = preg_replace('/\s*(₺|tl)\s*/iu', '', $s);
    return trim($s) . ' ₺';
}

?><!doctype html>
<html lang="<?php echo h($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo h($favicon); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="site-header" style="<?php echo $headerBg? 'background:'.h($headerBg):''; ?>">
        <div class="header-left">
            <button id="wifiToggle" class="icon-btn" aria-label="WiFi">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 20c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6.6-6.6c-3.6-3.6-9.6-3.6-13.2 0-.4.4-1 .4-1.4 0s-.4-1 0-1.4c4.4-4.4 11.6-4.4 16 0 .4.4.4 1 0 1.4s-1 .4-1.4 0zm3.5-3.5c-5.6-5.6-14.7-5.6-20.3 0-.4.4-1 .4-1.4 0s-.4-1 0-1.4c6.4-6.4 16.7-6.4 23.1 0 .4.4.4 1 0 1.4s-1 .4-1.4 0z"/></svg>
            </button>
        </div>
        <div class="header-center">
            <?php if ($logo): ?>
            <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h($title); ?>">
            <?php else: ?>
            <div class="logo-text"><?php echo h($title); ?></div>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <button id="searchToggle" class="icon-btn" aria-label="Ara">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
        </div>
    </header>
    <div id="wifiModal" class="modal">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <button id="wifiClose" class="modal-close" aria-label="Kapat">×</button>
            <div class="modal-body">
                <div class="modal-logo">
                    <?php if ($logo): ?>
                        <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h($title); ?>">
                    <?php else: ?>
                        <div class="logo-text"><?php echo h($title); ?></div>
                    <?php endif; ?>
                </div>
                <div class="wifi-info">
                    <div class="wifi-title">WiFi Şifresi</div>
                    <div class="wifi-pass"><?php echo h((string)($config['wifi_password'] ?? '')); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div id="variantsModal" class="modal">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <button id="varClose" class="modal-close" aria-label="Kapat">×</button>
            <div class="modal-body">
                <div class="modal-logo">
                    <?php if ($logo): ?>
                        <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h($title); ?>">
                    <?php else: ?>
                        <div class="logo-text"><?php echo h($title); ?></div>
                    <?php endif; ?>
                </div>
                <div class="wifi-info">
                    <div class="wifi-title" id="varTitle">Çeşitler</div>
                    <ul class="var-list" id="varList"></ul>
                </div>
            </div>
        </div>
    </div>
    <div id="searchBar" class="search-bar">
        <input type="text" id="searchInput" placeholder="Ara">
        <div id="searchResults" class="search-results"></div>
    </div>

    <main class="container">
        <section class="favorites">
            <h2>Favori Menüler</h2>
            <div class="fav-list">
                <?php foreach ($menu as $item): ?>
                    <?php if (!empty($item['favorite'])): ?>
                        <a class="fav-card" href="category.php?id=<?php echo h((string)($item['category_id'] ?? '')); ?>#item-<?php echo h((string)($item['id'] ?? '')); ?>">
                            <div class="fav-img" style="<?php echo !empty($item['image'])? 'background-image:url('.h($item['image']).')':''; ?>"></div>
                            <div class="fav-info">
                                <div class="fav-name">
                                    <?php echo h((string)($item['name'] ?? '')); ?>
                                    <?php $vars = isset($item['variants']) && is_array($item['variants']) ? $item['variants'] : []; if (count($vars) > 0): $vdata = array_map(function($v){ return ['name'=>(string)($v['name']??''),'price'=>fmt_price($v['price']??'')]; }, $vars); $vjson = json_encode($vdata, JSON_UNESCAPED_UNICODE); $venc = rawurlencode((string)$vjson); ?>
                                    <button class="fav-var-btn" type="button" aria-label="Çeşitler" data-name="<?php echo h((string)($item['name'] ?? '')); ?>" data-variants="<?php echo h($venc); ?>">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2l3 7h7l-6 4 3 7-6-4-6 4 3-7-6-4h7z"/></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div class="fav-price"><?php echo h(fmt_price($item['price'] ?? '')); ?></div>
                            </div>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="categories">
            <div class="cat-header">
                <h2>Kategoriler</h2>
            </div>
            <div class="cat-grid">
                <?php foreach ($categories as $cat): ?>
                    <?php if (empty($cat['visible']) && $cat['visible'] !== true) continue; ?>
                    <?php $layoutVal = (string)($cat['layout'] ?? 'two'); $layout = in_array($layoutVal, ['full','two','vertical'], true) ? $layoutVal : 'two'; ?>
                    <a class="cat-card <?php echo $layout; ?>" href="category.php?id=<?php echo h((string)$cat['id']); ?>" style="<?php echo !empty($cat['image'])? 'background-image:url('.h($cat['image']).')':''; ?>">
                        <div class="cat-title"><?php echo h((string)$cat['name']); ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer class="site-footer" style="<?php echo $footerBg? 'background:'.h($footerBg):''; ?>">
        <div class="footer-left"></div>
        <div class="footer-center"><?php echo h($title); ?></div>
        <div class="footer-right footer-icons">
            <?php if (!empty($social['facebook'])): ?>
            <a class="social-link" href="<?php echo h($social['facebook']); ?>" target="_blank" aria-label="Facebook">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7h-2.5V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 3h-2.4v7A10 10 0 0 0 22 12z"/></svg>
            </a>
            <?php endif; ?>
            <?php if (!empty($social['instagram'])): ?>
            <a class="social-link" href="<?php echo h($social['instagram']); ?>" target="_blank" aria-label="Instagram">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm6-1.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/></svg>
            </a>
            <?php endif; ?>
            <?php if (!empty($social['twitter'])): ?>
            <a class="social-link" href="<?php echo h($social['twitter']); ?>" target="_blank" aria-label="X">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 3l7 7-7 7h3l5-5 5 5h3l-7-7 7-7h-3l-5 5-5-5H3z"/></svg>
            </a>
            <?php endif; ?>
        </div>
    </footer>

    <script>window.__SEARCH__ = { categories: <?php echo json_encode(array_map(function($c){ return ['id'=>(string)($c['id']??''),'name'=>(string)($c['name']??'')]; }, $categories), JSON_UNESCAPED_UNICODE); ?>, menu: <?php echo json_encode(array_map(function($m){ return ['id'=>(string)($m['id']??''),'name'=>(string)($m['name']??''),'category_id'=>(string)($m['category_id']??'')]; }, $menu), JSON_UNESCAPED_UNICODE); ?> };</script>
    <script src="assets/js/app.js"></script>
</body>
</html>
<?php
$out = ob_get_contents();
if ($out !== false) { @file_put_contents($cacheFile, $out); }
ob_end_flush();
?>

