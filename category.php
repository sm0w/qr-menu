<?php
declare(strict_types=1);

$baseDir = __DIR__;
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';

function load_json(string $file, array $default = []): array {
    if (!file_exists($file)) return $default;
    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') return $default;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fmt_price($p): string {
    $s = trim((string)$p);
    if ($s === '') return '';
    $s = preg_replace('/\s*(₺|tl)\s*/iu', '', $s);
    return trim($s) . ' ₺';
}

$config = load_json($dataDir . DIRECTORY_SEPARATOR . 'config.json', []);
$categories = load_json($dataDir . DIRECTORY_SEPARATOR . 'categories.json', []);
$menu = load_json($dataDir . DIRECTORY_SEPARATOR . 'menu.json', []);

$id = isset($_GET['id']) ? (string)$_GET['id'] : '';
$category = null;
foreach ($categories as $cat) {
    if ((string)($cat['id'] ?? '') === $id) { $category = $cat; break; }
}
if (!$category) { header('Location: index.php'); exit; }

$lang = $_COOKIE['lang'] ?? 'tr';
$title = ($category['name'] ?? '') . ' - ' . ($config['site_title'] ?? 'Menü');
$logo = $config['logo'] ?? '';
$headerBg = (string)($config['header_bg'] ?? '');
$footerBg = (string)($config['footer_bg'] ?? '');
$bg = $category['image'] ?? '';

?><!doctype html>
<html lang="<?php echo h($lang); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($title); ?></title>
    <?php if (!empty($config['favicon'])): ?>
    <link rel="icon" href="<?php echo h((string)$config['favicon']); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="cat-header-bg" style="<?php echo $bg? 'background-image:url('.h($bg).')':''; ?>"></div>
    <header class="site-header overlay" style="<?php echo $headerBg? 'background:'.h($headerBg):''; ?>">
        <div class="header-left">
            <button id="wifiToggle" class="icon-btn" aria-label="WiFi">
                <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 20c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6.6-6.6c-3.6-3.6-9.6-3.6-13.2 0-.4.4-1 .4-1.4 0s-.4-1 0-1.4c4.4-4.4 11.6-4.4 16 0 .4.4.4 1 0 1.4s-1 .4-1.4 0zm3.5-3.5c-5.6-5.6-14.7-5.6-20.3 0-.4.4-1 .4-1.4 0s-.4-1 0-1.4c6.4-6.4 16.7-6.4 23.1 0 .4.4.4 1 0 1.4s-1 .4-1.4 0z"/></svg>
            </button>
        </div>
        <div class="header-center">
            <?php if ($logo): ?>
            <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h((string)$config['site_title']); ?>">
            <?php else: ?>
            <div class="logo-text"><?php echo h((string)$config['site_title']); ?></div>
            <?php endif; ?>
        </div>
        <div class="header-right"></div>
    </header>

    <div class="cat-title-bar">
        <h1><?php echo h((string)$category['name']); ?></h1>
        <a class="back-btn" href="index.php">Menüye dön</a>
    </div>

    <div id="wifiModal" class="modal">
        <div class="modal-backdrop"></div>
        <div class="modal-content">
            <button id="wifiClose" class="modal-close" aria-label="Kapat">×</button>
            <div class="modal-body">
                <div class="modal-logo">
                    <?php if ($logo): ?>
                        <img class="logo" src="<?php echo h($logo); ?>" alt="<?php echo h((string)$config['site_title']); ?>">
                    <?php else: ?>
                        <div class="logo-text"><?php echo h((string)$config['site_title']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="wifi-info">
                    <div class="wifi-title">WiFi Şifresi</div>
                    <div class="wifi-pass"><?php echo h((string)($config['wifi_password'] ?? '')); ?></div>
                </div>
            </div>
        </div>
    </div>

    <main class="container">
        <section class="menu-list">
            <?php foreach ($menu as $item): ?>
                <?php if ((string)($item['category_id'] ?? '') !== $id) continue; ?>
                <div class="menu-card" id="item-<?php echo h((string)($item['id'] ?? '')); ?>">
                    <div class="menu-img" style="<?php echo !empty($item['image'])? 'background-image:url('.h($item['image']).')':''; ?>"></div>
                    <div class="menu-info">
                        <div class="menu-name"><?php echo h((string)($item['name'] ?? '')); ?></div>
                    </div>
                    <div class="menu-price-right"><?php echo h(fmt_price($item['price'] ?? '')); ?></div>
                </div>
            <?php endforeach; ?>
        </section>
    </main>

    <footer class="site-footer" style="<?php echo $footerBg? 'background:'.h($footerBg):''; ?>">
        <div class="footer-left"></div>
        <div class="footer-center"><?php echo h((string)$config['site_title']); ?></div>
        <div class="footer-right footer-icons">
            <?php $social = $config['social'] ?? ['facebook'=>'','instagram'=>'','twitter'=>'']; ?>
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

    <script src="assets/js/app.js"></script>
</body>
</html>
