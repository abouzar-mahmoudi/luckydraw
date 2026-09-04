<?php
/** @var array $page  @var string $content */
$tool = $page['tool'] ?? null;
$lang = ld_lang();
$langMeta = LD_LANGS[$lang];
$otherLang = $lang === 'fa' ? 'en' : 'fa';
$liveTemplate = pretty_urls() ? base_url() . '/live/{code}' : base_url() . '/?r={code}';
$config = [
    'base' => base_url(),
    'api' => base_url() . '/api.php',
    'liveUrl' => $liveTemplate,
    'signupUrl' => pretty_urls() ? base_url() . '/signup/{code}' : base_url() . '/?s={code}',
    'signupTtlOptions' => Signup::TTL_OPTIONS,
    'tool' => $tool,
    'code' => $page['code'] ?? null,
    'version' => LD_VERSION,
    'lang' => $lang,
    'dir' => $langMeta['dir'],
    'locale' => $langMeta['locale'],
    'i18n' => ld_js_strings(),
    'ttlOptions' => LD_TTL_OPTIONS,
    'maxTtlTotal' => LD_MAX_TTL_TOTAL,
    'maxItems' => LD_MAX_ITEMS,
    'codeRules' => ['min' => LD_CODE_MIN, 'max' => LD_CODE_MAX, 'autoLen' => LD_CODE_AUTO_LEN],
];
$nonce = csp_nonce();
$appName = t('app.name');
?><!DOCTYPE html>
<html lang="<?= e($lang) ?>" dir="<?= e($langMeta['dir']) ?>" data-theme="dark" data-digits="<?= e($langMeta['digits']) ?>" data-lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark light">
<meta name="theme-color" content="#0b0f1a">
<meta name="referrer" content="no-referrer">
<title><?= e($page['title']) ?> | <?= e($appName) ?></title>
<link rel="alternate" hreflang="<?= e($otherLang) ?>" href="<?= e(lang_switch_url($otherLang)) ?>">
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<?php foreach (['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold', 'Black'] as $w): ?>
<link rel="preload" href="<?= base_url() ?>/assets/fonts/vazirmatn/Vazirmatn-<?= $w ?>.woff2" as="font" type="font/woff2" crossorigin>
<?php endforeach; ?>
<link rel="preload" href="<?= base_url() ?>/assets/fontawesome/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= base_url() ?>/assets/fontawesome/webfonts/fa-regular-400.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
<link rel="stylesheet" href="<?= asset('fontawesome/css/all.min.css') ?>">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script nonce="<?= e($nonce) ?>">
(function(){try{var g=function(k){var v=localStorage.getItem('ld.'+k);return v?String(v).replace(/"/g,''):null;};var t=g('theme');if(t==='light'||t==='dark'){document.documentElement.setAttribute('data-theme',t);}var d=g('digits.<?= e($lang) ?>');if(d==='en'||d==='fa'){document.documentElement.setAttribute('data-digits',d);}}catch(e){}})();
window.LD = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
</head>
<body class="<?= e($page['body_class'] ?? '') ?>">
<div class="bg-blobs" aria-hidden="true"><i></i><i></i><i></i></div>

<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= e(route_url('/')) ?>" aria-label="<?= e($appName) ?>">
      <span class="brand-logo"><i class="fa-solid fa-clover"></i></span>
      <span class="brand-text"><?= e($appName) ?></span>
    </a>
    <nav class="topnav" aria-label="<?= tx('nav.tools') ?>">
      <a href="<?= e(route_url('coin')) ?>" class="<?= $tool === 'coin' ? 'active' : '' ?>"><i class="fa-solid fa-coins"></i><span><?= tx('tool.coin') ?></span></a>
      <a href="<?= e(route_url('number')) ?>" class="<?= $tool === 'number' ? 'active' : '' ?>"><i class="fa-solid fa-dice"></i><span><?= tx('tool.number') ?></span></a>
      <a href="<?= e(route_url('pick')) ?>" class="<?= $tool === 'pick' ? 'active' : '' ?>"><i class="fa-solid fa-list-check"></i><span><?= tx('tool.pick') ?></span></a>
      <a href="<?= e(route_url('wheel')) ?>" class="<?= $tool === 'wheel' ? 'active' : '' ?>"><i class="fa-solid fa-dharmachakra"></i><span><?= tx('tool.wheel') ?></span></a>
      <a href="<?= e(route_url('teams')) ?>" class="<?= $tool === 'teams' ? 'active' : '' ?>"><i class="fa-solid fa-people-group"></i><span><?= tx('tool.teams.short') ?></span></a>
    </nav>
    <div class="topbar-actions">
      <a class="icon-btn lang-btn" id="langToggle" href="<?= e(lang_switch_url($otherLang)) ?>" hreflang="<?= e($otherLang) ?>" lang="<?= e($otherLang) ?>" title="<?= tx('nav.language') ?>: <?= e(LD_LANGS[$otherLang]['name']) ?>" aria-label="<?= tx('nav.language_aria') ?>"><span><?= e(LD_LANGS[$otherLang]['short']) ?></span></a>
      <button type="button" class="icon-btn" id="digitsToggle" title="<?= tx('nav.digits') ?>" aria-label="<?= tx('nav.digits_aria') ?>"><span class="digits-glyph"></span></button>
      <button type="button" class="icon-btn" id="soundToggle" title="<?= tx('nav.sound') ?>" aria-label="<?= tx('nav.sound') ?>"><i class="fa-solid fa-volume-high"></i></button>
      <button type="button" class="icon-btn" id="themeToggle" title="<?= tx('nav.theme') ?>" aria-label="<?= tx('nav.theme_aria') ?>"><i class="fa-solid fa-moon"></i></button>
    </div>
  </div>
</header>

<main class="container main">
<?= $content ?>
</main>

<footer class="footer">
  <div class="container footer-inner">
    <span><i class="fa-solid fa-plug-circle-xmark"></i> <?= tx('footer.offline') ?></span>
    <span class="footer-sep">•</span>
    <span><?= e($appName) ?> <?= tx('footer.version') ?> <span class="num"><?= e(LD_VERSION) ?></span></span>
  </div>
</footer>

<div id="toasts" class="toasts" aria-live="polite"></div>
<div id="modalRoot"></div>

<script src="<?= asset('vendor/qrcode.js') ?>"></script>
<script src="<?= asset('vendor/confetti.browser.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/tools.js') ?>"></script>
<?php if (!empty($page['script'])): ?>
<script src="<?= asset('js/' . $page['script'] . '.js') ?>"></script>
<?php elseif (($page['body_class'] ?? '') === 'page-live' || strpos($page['body_class'] ?? '', 'page-live') === 0): ?>
<script src="<?= asset('js/live.js') ?>"></script>
<?php elseif (($page['body_class'] ?? '') === 'page-signup'): ?>
<script src="<?= asset('js/signup.js') ?>"></script>
<?php elseif ($tool !== null): ?>
<script src="<?= asset('js/host.js') ?>"></script>
<?php if (in_array($tool, Signup::TOOLS, true)): ?>
<script src="<?= asset('js/reg.js') ?>"></script>
<?php endif; ?>
<?php endif; ?>
</body>
</html>
