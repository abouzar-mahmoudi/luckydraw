<?php
/** @var array|null $room  @var string|null $code  @var array $tools */
if ($room === null): ?>
<section class="expired">
  <div class="expired-icon"><i class="fa-solid fa-hourglass-end"></i></div>
  <h1><?= tx('live.expired_title') ?></h1>
  <p class="muted"><?= tx('live.expired_desc') ?></p>
  <form class="join-form" id="joinForm" autocomplete="off">
    <div class="join-input">
      <i class="fa-solid fa-tower-broadcast"></i>
      <input id="joinCode" name="code" type="text" inputmode="latin" maxlength="<?= (int) LD_CODE_MAX ?>" placeholder="<?= tx('live.code_placeholder') ?>" dir="ltr" spellcheck="false" autocapitalize="characters" value="">
      <button type="submit" class="btn btn-primary"><span><?= tx('home.watch') ?></span><i class="fa-solid fa-arrow-left icon-forward"></i></button>
    </div>
  </form>
  <a class="btn btn-ghost" href="<?= e(route_url('/')) ?>"><i class="fa-solid fa-house"></i><span><?= tx('page.home') ?></span></a>
</section>
<?php else:
$tool = $room['tool'];
$meta = $tools[$tool];
?>
<div class="live-page" data-tool="<?= e($tool) ?>" data-code="<?= e($room['id']) ?>">
  <div class="tool-header">
    <h1><i class="fa-solid <?= e($meta['icon']) ?>"></i> <span id="liveTitle"><?= e($room['title'] !== '' ? $room['title'] : $meta['title']) ?></span></h1>
    <div class="tool-header-actions">
      <div class="live-pill on">
        <span class="live-dot"></span>
        <span><?= tx('live.on_air') ?></span>
        <b class="num" dir="ltr"><?= e($room['id']) ?></b>
        <span class="live-timer num" id="liveTimer" dir="ltr">--:--</span>
        <span class="live-viewers" title="<?= tx('host.live_viewers') ?>"><i class="fa-solid fa-eye"></i> <b class="num" id="liveViewers">1</b></span>
      </div>
      <button type="button" class="btn btn-ghost" id="fullscreenBtn" title="<?= tx('host.fullscreen') ?>"><i class="fa-solid fa-expand"></i></button>
    </div>
  </div>

  <div class="tool-layout live-layout">
    <section class="panel stage-panel" id="stagePanel">
      <?php render('stage', ['tool' => $tool, 'mode' => 'live']); ?>
      <div class="live-status" id="liveStatus"><i class="fa-solid fa-satellite-dish fa-fade"></i> <?= tx('live.waiting') ?></div>
    </section>
    <aside class="panel settings-panel live-side">
      <div class="panel-title"><i class="fa-solid fa-users"></i> <?= tx('live.participants') ?> <b class="num badge" id="liveCount">0</b></div>
      <div class="live-list" id="liveList"></div>
      <div class="panel-title mt"><i class="fa-solid fa-clock-rotate-left"></i> <?= tx('host.history') ?></div>
      <ol class="history" id="historyList"><li class="empty"><?= tx('host.history_empty') ?></li></ol>
    </aside>
  </div>
</div>
<script nonce="<?= e(csp_nonce()) ?>">window.LD_ROOM = <?= json_encode((new Room(Store::make()))->publicView($room), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php endif; ?>
