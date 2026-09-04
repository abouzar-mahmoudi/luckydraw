<?php
/** @var string $tool  @var array $tools */
$meta = $tools[$tool];
?>
<div class="tool-page" data-tool="<?= e($tool) ?>">

  <div class="tool-header">
    <h1><i class="fa-solid <?= e($meta['icon']) ?>"></i> <?= e($meta['title']) ?></h1>
    <div class="tool-header-actions">
      <div class="live-pill" id="livePill" hidden>
        <span class="live-dot"></span>
        <span><?= tx('host.live') ?></span>
        <b class="num" id="liveCode" dir="ltr"></b>
        <span class="live-timer num" id="liveTimer" dir="ltr">--:--</span>
        <span class="live-viewers" title="<?= tx('host.live_viewers') ?>"><i class="fa-solid fa-eye"></i> <b class="num" id="liveViewers">0</b></span>
      </div>
      <button type="button" class="btn btn-ghost" id="shareBtn"><i class="fa-solid fa-share-nodes"></i><span><?= tx('host.create_live') ?></span></button>
      <button type="button" class="btn btn-ghost" id="fullscreenBtn" title="<?= tx('host.fullscreen') ?>"><i class="fa-solid fa-expand"></i></button>
    </div>
  </div>

  <div class="tool-layout">

    <!-- ============ SETTINGS PANEL ============ -->
    <aside class="panel settings-panel" id="settingsPanel">
      <div class="panel-title"><i class="fa-solid fa-sliders"></i> <?= tx('host.settings') ?></div>

      <?php if ($tool === 'coin'): ?>
        <div class="field-row">
          <label class="field"><span><?= tx('host.coin_heads') ?></span><input type="text" id="coinHeads" value="<?= tx('draw.heads') ?>" maxlength="24"></label>
          <label class="field"><span><?= tx('host.coin_tails') ?></span><input type="text" id="coinTails" value="<?= tx('draw.tails') ?>" maxlength="24"></label>
        </div>
        <label class="field"><span><?= tx('host.coin_count') ?></span>
          <div class="stepper"><button type="button" data-step="-1"><i class="fa-solid fa-minus"></i></button><input type="number" id="coinCount" value="1" min="1" max="10"><button type="button" data-step="1"><i class="fa-solid fa-plus"></i></button></div>
        </label>
        <p class="hint"><?= tx('host.coin_hint') ?></p>

      <?php elseif ($tool === 'number'): ?>
        <div class="field-row">
          <label class="field"><span><?= tx('host.from') ?></span><input type="number" id="numMin" value="1" dir="ltr"></label>
          <label class="field"><span><?= tx('host.to') ?></span><input type="number" id="numMax" value="100" dir="ltr"></label>
        </div>
        <label class="field"><span><?= tx('host.num_count') ?></span>
          <div class="stepper"><button type="button" data-step="-1"><i class="fa-solid fa-minus"></i></button><input type="number" id="numCount" value="1" min="1" max="100"><button type="button" data-step="1"><i class="fa-solid fa-plus"></i></button></div>
        </label>
        <label class="switch"><input type="checkbox" id="numUnique" checked><span class="switch-ui"></span><span><?= tx('host.unique') ?></span></label>
        <label class="switch"><input type="checkbox" id="numSort"><span class="switch-ui"></span><span><?= tx('host.sort') ?></span></label>
        <div class="quick-ranges">
          <button type="button" class="chip" data-range="1,6"><?= tx('host.range_dice') ?></button>
          <button type="button" class="chip num" data-range="1,10" data-raw="1–10">1–10</button>
          <button type="button" class="chip num" data-range="1,100" data-raw="1–100">1–100</button>
          <button type="button" class="chip num" data-range="1,1000" data-raw="1–1000">1–1000</button>
        </div>

      <?php else: ?>
        <label class="field">
          <span><?= tx('host.list_label') ?> <small class="muted"><?= tx('host.list_hint') ?></small></span>
          <textarea id="listInput" rows="10" placeholder="<?= $tool === 'teams' ? tx('host.list_placeholder_teams') : tx('host.list_placeholder') ?>" spellcheck="false"></textarea>
        </label>
        <div class="list-tools">
          <span class="list-count"><i class="fa-solid fa-users"></i> <b class="num" id="listCount">0</b> <?= tx('host.persons') ?></span>
          <button type="button" class="chip" id="listDedupe" title="<?= tx('host.dedupe_title') ?>"><i class="fa-solid fa-clone"></i> <?= tx('host.dedupe') ?></button>
          <button type="button" class="chip" id="listShuffle" title="<?= tx('host.shuffle_title') ?>"><i class="fa-solid fa-shuffle"></i> <?= tx('host.shuffle') ?></button>
          <button type="button" class="chip" id="listSort" title="<?= tx('host.sort_title') ?>"><i class="fa-solid fa-arrow-down-a-z"></i> <?= tx('host.sort_btn') ?></button>
          <button type="button" class="chip" id="listNumbers" title="<?= tx('host.numbers_title') ?>"><i class="fa-solid fa-1"></i><i class="fa-solid fa-ellipsis"></i> <?= tx('host.numbers') ?></button>
          <button type="button" class="chip" id="listSample" title="<?= tx('host.sample') ?>"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= tx('host.sample') ?></button>
          <button type="button" class="chip danger" id="listClear" title="<?= tx('host.clear') ?>"><i class="fa-solid fa-trash"></i></button>
        </div>
        <?php if ($tool === 'pick'): ?>
          <label class="field"><span><?= tx('host.pick_count') ?></span>
            <div class="stepper"><button type="button" data-step="-1"><i class="fa-solid fa-minus"></i></button><input type="number" id="pickCount" value="1" min="1" max="100"><button type="button" data-step="1"><i class="fa-solid fa-plus"></i></button></div>
          </label>
        <?php elseif ($tool === 'wheel'): ?>
          <label class="field"><span><?= tx('host.wheel_duration') ?></span>
            <input type="range" id="wheelDuration" min="3" max="15" value="7"><output class="num" id="wheelDurationOut">7</output>
          </label>
        <?php elseif ($tool === 'teams'): ?>
          <div class="field"><span><?= tx('host.teams_by') ?></span>
            <div class="seg" id="teamsBy">
              <button type="button" class="active" data-by="groups"><i class="fa-solid fa-people-group"></i> <?= tx('host.teams_by_groups') ?></button>
              <button type="button" data-by="size"><i class="fa-solid fa-user-group"></i> <?= tx('host.teams_by_size') ?></button>
            </div>
          </div>
          <label class="field"><span id="teamsNLabel"><?= tx('host.teams_n_groups') ?></span>
            <div class="stepper"><button type="button" data-step="-1"><i class="fa-solid fa-minus"></i></button><input type="number" id="teamsN" value="2" min="1" max="100"><button type="button" data-step="1"><i class="fa-solid fa-plus"></i></button></div>
          </label>
          <label class="field"><span><?= tx('host.teams_names') ?> <small class="muted"><?= tx('host.teams_names_hint') ?></small></span>
            <input type="text" id="teamsNames" placeholder="<?= tx('host.teams_names_placeholder') ?>" maxlength="600">
          </label>
          <p class="hint"><i class="fa-solid fa-circle-info"></i> <?= tx('host.teams_hint') ?></p>
        <?php endif; ?>
        <?php if ($tool !== 'teams'): ?>
        <label class="switch highlight"><input type="checkbox" id="removeWinner" <?= $tool === 'wheel' ? 'checked' : '' ?>><span class="switch-ui"></span><span><?= $tool === 'wheel' ? tx('host.remove_winner') : tx('host.remove_picked') ?></span></label>
        <p class="hint"><i class="fa-solid fa-circle-info"></i> <?= str_replace('{0}', '<code dir="ltr">' . tx('host.weight_example') . '</code>', tx('host.weight_hint')) ?></p>
        <?php endif; ?>

        <!-- ============ REGISTRATION (ثبت‌نام جهت قرعه‌کشی) ============ -->
        <div class="reg-box" id="regBox">
          <button type="button" class="reg-toggle" id="regToggle" aria-expanded="false" aria-controls="regBody">
            <span class="reg-toggle-title"><i class="fa-solid fa-clipboard-user"></i> <?= tx('reg.title') ?></span>
            <span class="reg-badge" id="regBadge" hidden><span class="live-dot"></span> <b class="num" id="regBadgeCount">0</b></span>
            <i class="fa-solid fa-chevron-down reg-chevron"></i>
          </button>
          <div class="reg-body" id="regBody" hidden>
            <div class="reg-step" data-step="create">
              <p class="hint"><?= tx('reg.desc') ?></p>
              <div class="field"><span><?= tx('reg.fields') ?></span>
                <div class="seg" id="regFields">
                  <button type="button" class="active" data-fields="name"><i class="fa-solid fa-user"></i> <?= tx('reg.fields_name') ?></button>
                  <button type="button" data-fields="code"><i class="fa-solid fa-hashtag"></i> <?= tx('reg.fields_code') ?></button>
                  <button type="button" data-fields="both"><i class="fa-solid fa-id-card"></i> <?= tx('reg.fields_both') ?></button>
                </div>
              </div>
              <label class="switch"><input type="checkbox" id="regAuto"><span class="switch-ui"></span><span><?= tx('reg.auto') ?></span></label>
              <div class="field"><span><?= tx('reg.ttl') ?></span>
                <div class="ttl-options" id="regTtlOptions"></div>
              </div>
              <div class="field">
                <span><?= tx('reg.code') ?></span>
                <div class="seg" id="regCodeMode">
                  <button type="button" class="active" data-mode="auto"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= tx('share.code_auto') ?></button>
                  <button type="button" data-mode="custom"><i class="fa-solid fa-pen"></i> <?= tx('share.code_custom') ?></button>
                </div>
                <div class="custom-code" id="regCustomWrap" hidden>
                  <div class="custom-code-input" dir="ltr">
                    <span class="custom-code-prefix" id="regCustomPrefix">/signup/</span>
                    <input type="text" id="regCustomCode" inputmode="latin" autocapitalize="characters" spellcheck="false" maxlength="<?= (int) LD_CODE_MAX ?>" placeholder="SABT-1404">
                  </div>
                  <small class="hint"><?= tx('share.code_hint', LD_CODE_MIN, LD_CODE_MAX) ?></small>
                </div>
              </div>
              <button type="button" class="btn btn-primary btn-block" id="regCreate"><i class="fa-solid fa-link"></i><span><?= tx('reg.create') ?></span></button>
            </div>

            <div class="reg-step" data-step="ready" hidden>
              <div class="reg-status">
                <span class="reg-state" id="regState"><span class="live-dot"></span> <b id="regStateText"><?= tx('reg.status_open') ?></b></span>
                <span class="muted"><i class="fa-regular fa-clock"></i> <?= tx('reg.valid_until') ?> <b class="num" id="regExpires" dir="ltr">--:--</b></span>
              </div>
              <div class="share-link">
                <input type="text" id="regUrl" readonly dir="ltr">
                <button type="button" class="btn btn-primary" id="regCopy" title="<?= tx('reg.copy') ?>"><i class="fa-solid fa-copy"></i><span><?= tx('host.copy') ?></span></button>
              </div>
              <div class="reg-qr-row">
                <div class="share-qr reg-qr" id="regQr"></div>
                <a class="btn btn-ghost btn-sm" id="regOpen" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i><span><?= tx('reg.open_page') ?></span></a>
              </div>
              <label class="switch"><input type="checkbox" id="regAutoLive"><span class="switch-ui"></span><span><?= tx('reg.auto') ?></span></label>
              <div class="extend-group reg-extend">
                <select id="regExtendMinutes" class="select-sm"></select>
                <button type="button" class="btn btn-ghost btn-sm" id="regExtend"><i class="fa-solid fa-plus"></i><span><?= tx('reg.extend') ?></span></button>
              </div>

              <div class="reg-entries-head">
                <span class="panel-title"><i class="fa-solid fa-users"></i> <?= tx('reg.entries') ?></span>
                <div class="seg seg-sm" id="regFilter">
                  <button type="button" class="active" data-filter="all"><?= tx('reg.filter_all') ?> <b class="num" data-count="total">0</b></button>
                  <button type="button" data-filter="pending"><?= tx('reg.pending') ?> <b class="num" data-count="pending">0</b></button>
                  <button type="button" data-filter="approved"><?= tx('reg.approved') ?> <b class="num" data-count="approved">0</b></button>
                  <button type="button" data-filter="rejected"><?= tx('reg.rejected') ?> <b class="num" data-count="rejected">0</b></button>
                </div>
              </div>
              <ul class="reg-entries" id="regEntries"><li class="empty"><?= tx('reg.empty') ?></li></ul>
              <div class="reg-bulk" id="regBulk" hidden>
                <button type="button" class="chip ok" id="regApproveAll"><i class="fa-solid fa-check-double"></i> <?= tx('reg.approve_all') ?></button>
                <button type="button" class="chip danger" id="regRejectAll"><i class="fa-solid fa-xmark"></i> <?= tx('reg.reject_all') ?></button>
              </div>
              <button type="button" class="btn btn-primary btn-block" id="regImport"><i class="fa-solid fa-file-import"></i><span><?= tx('reg.import') ?></span> <b class="num" id="regImportCount">0</b></button>
              <p class="hint"><?= tx('reg.import_hint') ?></p>
              <div class="reg-actions">
                <button type="button" class="btn btn-ghost btn-sm" id="regClose"><i class="fa-solid fa-lock"></i><span><?= tx('reg.close') ?></span></button>
                <button type="button" class="btn btn-danger btn-sm" id="regEnd"><i class="fa-solid fa-trash"></i><span><?= tx('reg.end') ?></span></button>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="panel-title mt"><i class="fa-solid fa-clock-rotate-left"></i> <?= tx('host.history') ?>
        <button type="button" class="chip small" id="historyCopy" title="<?= tx('host.copy') ?>"><i class="fa-solid fa-copy"></i></button>
        <button type="button" class="chip small" id="historyDownload" title="<?= tx('host.download_csv') ?>"><i class="fa-solid fa-download"></i></button>
        <button type="button" class="chip small danger" id="historyClear" title="<?= tx('host.clear_history') ?>"><i class="fa-solid fa-trash"></i></button>
      </div>
      <ol class="history" id="historyList"><li class="empty"><?= tx('host.history_empty') ?></li></ol>
    </aside>

    <!-- ============ STAGE ============ -->
    <section class="panel stage-panel" id="stagePanel">
      <?php render('stage', ['tool' => $tool, 'mode' => 'host']); ?>
      <div class="stage-actions">
        <button type="button" class="btn btn-primary btn-xl" id="goBtn">
          <i class="fa-solid <?= ['coin' => 'fa-coins', 'number' => 'fa-dice', 'pick' => 'fa-hand-pointer', 'wheel' => 'fa-rotate', 'teams' => 'fa-shuffle'][$tool] ?>"></i>
          <span><?= tx('host.go.' . $tool) ?></span>
        </button>
        <button type="button" class="btn btn-ghost" id="resetBtn" title="<?= tx('host.reset') ?>"><i class="fa-solid fa-rotate-left"></i></button>
      </div>
    </section>
  </div>
</div>

<!-- ============ SHARE MODAL ============ -->
<template id="shareTemplate">
  <div class="modal-card share-modal">
    <button type="button" class="modal-close" data-close aria-label="<?= tx('share.close') ?>"><i class="fa-solid fa-xmark"></i></button>
    <div class="share-step" data-step="create">
      <h2><i class="fa-solid fa-tower-broadcast"></i> <?= tx('share.title') ?></h2>
      <p class="muted"><?= tx('share.desc') ?></p>
      <label class="field"><span><?= tx('share.name') ?></span><input type="text" id="shareTitle" maxlength="60" placeholder="<?= tx('share.name_placeholder') ?>"></label>
      <div class="field">
        <span><?= tx('share.code') ?></span>
        <div class="seg" id="shareCodeMode" role="tablist">
          <button type="button" class="active" data-mode="auto" role="tab"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= tx('share.code_auto') ?></button>
          <button type="button" data-mode="custom" role="tab"><i class="fa-solid fa-pen"></i> <?= tx('share.code_custom') ?></button>
        </div>
        <div class="custom-code" id="shareCustomWrap" hidden>
          <div class="custom-code-input" dir="ltr">
            <span class="custom-code-prefix" id="shareCustomPrefix">/live/</span>
            <input type="text" id="shareCustomCode" inputmode="latin" autocapitalize="characters" spellcheck="false" maxlength="<?= (int) LD_CODE_MAX ?>" placeholder="JASHN-1404">
          </div>
          <small class="hint" id="shareCustomHint"><?= tx('share.code_hint', LD_CODE_MIN, LD_CODE_MAX) ?></small>
        </div>
      </div>
      <div class="field"><span><?= tx('share.ttl') ?></span>
        <div class="ttl-options" id="ttlOptions"></div>
      </div>
      <button type="button" class="btn btn-primary btn-block" id="shareCreate"><i class="fa-solid fa-link"></i><span><?= tx('share.create') ?></span></button>
    </div>
    <div class="share-step" data-step="ready" hidden>
      <h2><i class="fa-solid fa-satellite-dish"></i> <?= tx('share.ready') ?></h2>
      <div class="share-code">
        <small><?= tx('share.code') ?></small>
        <b class="num" id="shareCode" dir="ltr">------</b>
        <small class="muted" id="shareCodeNote" hidden><i class="fa-solid fa-pen"></i> <?= tx('share.custom_note') ?></small>
      </div>
      <div class="share-link">
        <input type="text" id="shareUrl" readonly dir="ltr">
        <button type="button" class="btn btn-primary" id="shareCopy"><i class="fa-solid fa-copy"></i><span><?= tx('host.copy') ?></span></button>
      </div>
      <div class="share-host-note" id="shareHostNote" hidden>
        <i class="fa-solid fa-network-wired"></i>
        <span><?= tx('share.lan_note') ?></span> <select id="shareHostSelect" dir="ltr"></select>
      </div>
      <div class="share-qr" id="shareQr"></div>
      <div class="share-meta">
        <span><i class="fa-regular fa-clock"></i> <?= tx('share.valid_until') ?> <b class="num" id="shareExpires" dir="ltr">--:--</b></span>
        <span><i class="fa-solid fa-eye"></i> <b class="num" id="shareViewers">0</b> <?= tx('share.viewers') ?></span>
      </div>
      <div class="share-actions">
        <div class="extend-group">
          <select id="extendMinutes" class="select-sm"></select>
          <button type="button" class="btn btn-ghost" id="shareExtend"><i class="fa-solid fa-plus"></i><span><?= tx('share.extend') ?></span></button>
        </div>
        <a class="btn btn-ghost" id="shareOpen" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i><span><?= tx('share.open_viewer') ?></span></a>
        <button type="button" class="btn btn-danger" id="shareEnd"><i class="fa-solid fa-stop"></i><span><?= tx('share.end') ?></span></button>
      </div>
    </div>
  </div>
</template>
