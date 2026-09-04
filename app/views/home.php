<?php /** @var array $tools */ ?>
<section class="hero">
  <div class="hero-badge"><i class="fa-solid fa-wifi-slash"></i> <?= tx('home.badge') ?></div>
  <h1 class="hero-title"><?= tx('home.title_a') ?> <span class="grad"><?= tx('home.title_b') ?></span></h1>
  <p class="hero-sub"><?= tx('home.sub') ?></p>
  <div class="hero-join">
    <form class="join-form" id="joinForm" autocomplete="off">
      <label for="joinCode" class="sr-only"><?= tx('home.join_label') ?></label>
      <div class="join-input">
        <i class="fa-solid fa-tower-broadcast"></i>
        <input id="joinCode" name="code" type="text" inputmode="latin" maxlength="<?= (int) LD_CODE_MAX ?>" placeholder="<?= tx('home.join_placeholder') ?>" spellcheck="false" dir="ltr" autocapitalize="characters">
        <button type="submit" class="btn btn-primary"><span><?= tx('home.watch') ?></span><i class="fa-solid fa-arrow-left icon-forward"></i></button>
      </div>
    </form>
  </div>
</section>

<section class="tool-grid" aria-label="<?= tx('nav.tools') ?>">
  <?php foreach ($tools as $key => $t): ?>
  <a class="tool-card tool-<?= e($key) ?>" href="<?= e(route_url($key)) ?>">
    <div class="tool-card-icon"><i class="fa-solid <?= e($t['icon']) ?>"></i></div>
    <div class="tool-card-body">
      <h2><?= e($t['title']) ?></h2>
      <p><?= e($t['desc']) ?></p>
    </div>
    <span class="tool-card-arrow"><i class="fa-solid fa-arrow-left icon-forward"></i></span>
  </a>
  <?php endforeach; ?>
</section>

<section class="features">
  <div class="feature"><i class="fa-solid fa-shield-halved"></i><div><h3><?= tx('home.f1') ?></h3><p><?= tx('home.f1_desc') ?></p></div></div>
  <div class="feature"><i class="fa-solid fa-link"></i><div><h3><?= tx('home.f2') ?></h3><p><?= tx('home.f2_desc') ?></p></div></div>
  <div class="feature"><i class="fa-solid fa-tv"></i><div><h3><?= tx('home.f3') ?></h3><p><?= tx('home.f3_desc') ?></p></div></div>
  <div class="feature"><i class="fa-solid fa-user-minus"></i><div><h3><?= tx('home.f4') ?></h3><p><?= tx('home.f4_desc') ?></p></div></div>
</section>
