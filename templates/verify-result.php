<?php
/**
 * Result of clicking an email confirmation link.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 *   - success: bool
 *   - message: string|null  (on failure)
 *   - loginUrl: string|null  (on success)
 */
\OCP\Util::addStyle('invite_registration', 'guest');
?>
<div class="guest-box invite-reg-box">
    <?php if (!empty($_['success'])): ?>
        <h2><?php p($l->t('Account activated')); ?></h2>
        <p><?php p($l->t('Your email address has been confirmed and your account is now active.')); ?></p>
        <?php if (!empty($_['loginUrl'])): ?>
            <p>
                <a class="button primary" href="<?php p($_['loginUrl']); ?>">
                    <?php p($l->t('Go to login')); ?>
                </a>
            </p>
        <?php endif; ?>
    <?php else: ?>
        <h2><?php p($l->t('Confirmation failed')); ?></h2>
        <p><?php p($_['message'] ?? $l->t('This confirmation link is invalid or has expired.')); ?></p>
    <?php endif; ?>
</div>
