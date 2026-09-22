<?php
/**
 * Public registration form. Rendered as a guest page.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 *   - token: string
 *   - submitUrl: string (absolute-path URL the form posts to)
 *   - minPasswordLength: int
 *   - usernamePattern: string (PHP regex incl. delimiters)
 *   - error: string|null
 *   - username: string|null
 *   - email: string|null
 */

\OCP\Util::addStyle('invite_registration', 'guest');

/** Convert a PHP regex like /.../ into a bare pattern for the HTML5 pattern attribute. */
$rawPattern = (string)($_['usernamePattern'] ?? '');
$htmlPattern = $rawPattern;
if (strlen($rawPattern) >= 2 && $rawPattern[0] === '/') {
    $lastSlash = strrpos($rawPattern, '/');
    if ($lastSlash > 0) {
        $htmlPattern = substr($rawPattern, 1, $lastSlash - 1);
    }
}

// The submit URL is computed in the controller (via IURLGenerator) and passed in.
$submitUrl = (string)($_['submitUrl'] ?? '');
?>
<div class="guest-box invite-reg-box">
    <h2><?php p($l->t('Create account')); ?></h2>
    <p class="invite-reg-intro">
        <?php p($l->t('You have received an invitation. Please create your account below.')); ?>
    </p>

    <?php if (!empty($_['error'])): ?>
        <div class="invite-reg-error" role="alert">
            <?php p($_['error']); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php p($submitUrl); ?>" class="invite-reg-form">
        <label for="ir-username"><?php p($l->t('Username')); ?></label>
        <input type="text" id="ir-username" name="username" autocomplete="username"
               value="<?php p($_['username'] ?? ''); ?>"
               pattern="<?php p($htmlPattern); ?>"
               required autofocus
               placeholder="<?php p($l->t('e.g. max.mustermann')); ?>">

        <label for="ir-email"><?php p($l->t('Email address')); ?></label>
        <input type="email" id="ir-email" name="email" autocomplete="email"
               value="<?php p($_['email'] ?? ''); ?>"
               required
               placeholder="<?php p($l->t('you@example.com')); ?>">

        <label for="ir-password"><?php p($l->t('Password')); ?></label>
        <input type="password" id="ir-password" name="password" autocomplete="new-password"
               minlength="<?php p((int)$_['minPasswordLength']); ?>"
               required>
        <p class="invite-reg-hint">
            <?php p($l->t('At least %d characters.', [(int)$_['minPasswordLength']])); ?>
        </p>

        <input type="submit" class="primary invite-reg-submit"
               value="<?php p($l->t('Create account')); ?>">
    </form>
</div>
