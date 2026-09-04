<?php
/**
 * Shared stage markup for a tool (used by host page and live viewer page).
 * @var string $tool
 * @var string $mode  'host' | 'live'
 */
$isHost = $mode === 'host';
?>
<div class="stage stage-<?= e($tool) ?>" id="stage" data-tool="<?= e($tool) ?>" data-mode="<?= e($mode) ?>">

<?php if ($tool === 'coin'): ?>
  <div class="coin-arena" id="coinArena">
    <!-- coins are rendered by JS: one .coin3d per flip -->
  </div>
  <div class="coin-stats" id="coinStats" hidden>
    <span class="stat"><b class="num" data-side="0">0</b><small id="statHeads"><?= tx('stage.heads') ?></small></span>
    <span class="stat"><b class="num" data-side="1">0</b><small id="statTails"><?= tx('stage.tails') ?></small></span>
  </div>

<?php elseif ($tool === 'number'): ?>
  <div class="number-arena" id="numberArena">
    <div class="number-range num" id="numberRange"></div>
    <div class="number-slots" id="numberSlots">
      <div class="slot num placeholder">?</div>
    </div>
  </div>

<?php elseif ($tool === 'pick'): ?>
  <div class="pick-arena" id="pickArena">
    <div class="pick-cloud" id="pickCloud"></div>
    <div class="pick-winners" id="pickWinners" hidden></div>
  </div>

<?php elseif ($tool === 'wheel'): ?>
  <div class="wheel-arena" id="wheelArena">
    <div class="wheel-wrap" id="wheelWrap">
      <div class="wheel-glow"></div>
      <canvas id="wheelCanvas" width="900" height="900" aria-label="<?= tx('stage.wheel_aria') ?>"></canvas>
      <div class="wheel-lights" aria-hidden="true"></div>
      <button type="button" class="wheel-hub<?= $isHost ? '' : ' is-static' ?>" id="wheelHub" <?= $isHost ? '' : 'disabled' ?>>
        <span class="wheel-hub-text"><?= $isHost ? tx('stage.hub_spin') : tx('stage.hub_live') ?></span>
      </button>
      <div class="wheel-pointer" id="wheelPointer" aria-hidden="true"><i class="fa-solid fa-caret-left"></i></div>
    </div>
    <div class="wheel-current" id="wheelCurrent" aria-live="off"></div>
  </div>

<?php elseif ($tool === 'teams'): ?>
  <div class="teams-arena" id="teamsArena">
    <div class="teams-pool" id="teamsPool"></div>
    <div class="teams-grid" id="teamsGrid"></div>
  </div>
<?php endif; ?>

</div>

<div class="result-banner" id="resultBanner" hidden>
  <div class="result-kicker" id="resultKicker"></div>
  <div class="result-main" id="resultMain"></div>
  <div class="result-sub" id="resultSub"></div>
</div>
