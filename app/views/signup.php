<?php
/** @var array|null $signup  @var string|null $code  @var array $tools */
if ($signup === null): ?>
<section class="expired">
  <div class="expired-icon"><i class="fa-solid fa-hourglass-end"></i></div>
  <h1><?= tx('signup.expired_title') ?></h1>
  <p class="muted"><?= tx('signup.expired_desc') ?></p>
  <a class="btn btn-ghost" href="<?= e(route_url('/')) ?>"><i class="fa-solid fa-house"></i><span><?= tx('page.home') ?></span></a>
</section>
<?php else:
$meta = $tools[$signup['tool']];
$fields = $signup['fields'];
$open = !empty($signup['open']);
?>
<section class="signup-page" data-code="<?= e($signup['id']) ?>" data-fields="<?= e($fields) ?>">
  <div class="signup-card panel">
    <div class="signup-head">
      <span class="signup-icon"><i class="fa-solid fa-clipboard-user"></i></span>
      <h1><?= tx('signup.page_title') ?></h1>
      <?php if ($signup['title'] !== ''): ?><p class="signup-title"><?= e($signup['title']) ?></p><?php endif; ?>
      <p class="muted signup-tool"><i class="fa-solid <?= e($meta['icon']) ?>"></i> <?= tx('signup.for_tool', $meta['title']) ?></p>
    </div>

    <?php if (!$open): ?>
      <div class="signup-closed" id="signupClosed">
        <i class="fa-solid fa-lock"></i>
        <h2><?= tx('signup.closed_title') ?></h2>
        <p class="muted"><?= tx('signup.closed_desc') ?></p>
      </div>
    <?php else: ?>
      <form class="signup-form" id="signupForm" autocomplete="off" novalidate>
        <p class="muted"><?= tx('signup.intro') ?></p>
        <?php if ($fields === 'name' || $fields === 'both'): ?>
          <label class="field"><span><?= tx('signup.name') ?></span>
            <input type="text" id="signupName" name="name" maxlength="<?= (int) Signup::MAX_NAME ?>" placeholder="<?= tx('signup.name_placeholder') ?>" autocomplete="name" required>
          </label>
        <?php endif; ?>
        <?php if ($fields === 'code' || $fields === 'both'): ?>
          <label class="field"><span><?= tx('signup.code') ?></span>
            <input type="text" id="signupCode" name="code_value" maxlength="<?= (int) Signup::MAX_CODE ?>" placeholder="<?= tx('signup.code_placeholder') ?>" dir="ltr" inputmode="text" autocapitalize="characters" spellcheck="false" required>
          </label>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-xl btn-block" id="signupSubmit"><i class="fa-solid fa-user-plus"></i><span><?= tx('signup.submit') ?></span></button>
        <p class="hint signup-privacy"><i class="fa-solid fa-shield-halved"></i> <?= tx('signup.privacy') ?></p>
      </form>
      <div class="signup-done" id="signupDone" hidden>
        <div class="signup-check"><i class="fa-solid fa-circle-check"></i></div>
        <h2><?= tx('signup.done_title') ?></h2>
        <p class="muted" id="signupDoneMsg"></p>
        <p class="signup-entry" id="signupEntry"></p>
      </div>
      <div class="signup-closed" id="signupClosed" hidden>
        <i class="fa-solid fa-lock"></i>
        <h2><?= tx('signup.closed_title') ?></h2>
        <p class="muted"><?= tx('signup.closed_desc') ?></p>
      </div>
    <?php endif; ?>

    <div class="signup-meta">
      <span><i class="fa-solid fa-users"></i> <span id="signupCount"><?= str_replace('{0}', '<b class="num">' . (int) $signup['total'] . '</b>', tx('signup.registered_count')) ?></span></span>
      <span><i class="fa-regular fa-clock"></i> <?= tx('signup.open_until') ?> <b class="num" id="signupUntil" dir="ltr">--:--</b></span>
    </div>
  </div>
</section>
<script nonce="<?= e(csp_nonce()) ?>">window.LD_SIGNUP = <?= json_encode($signup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php endif; ?>
