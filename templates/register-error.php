<?php
/**
 * Generic invite error page (invalid / revoked / exhausted / expired).
 * Intentionally reveals nothing about valid tokens.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 *   - expired: bool
 */
\OCP\Util::addStyle('invite_registration', 'guest');
?>
<div class="guest-box invite-reg-box invite-reg-error-box">
    <?php if (!empty($_['expired'])): ?>
        <h2><?php p($l->t('Invitation expired')); ?></h2>
        <p><?php p($l->t('This invitation is no longer valid. Please request a new invitation.')); ?></p>
    <?php else: ?>
        <h2><?php p($l->t('Invitation invalid')); ?></h2>
        <p><?php p($l->t('This invitation link is invalid, expired or has already been used.')); ?></p>
    <?php endif; ?>
</div>
