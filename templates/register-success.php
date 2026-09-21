<?php
/**
 * Shown after a successful registration submit. The account exists but is
 * disabled until the user confirms the email we just sent.
 *
 * @var \OCP\IL10N $l
 * @var array $_
 *   - email: string
 */
\OCP\Util::addStyle('invite_registration', 'guest');
?>
<div class="guest-box invite-reg-box invite-reg-success-box">
    <h2><?php p($l->t('Almost done')); ?></h2>
    <p>
        <?php print_unescaped($l->t(
            'We sent a confirmation email to <strong>%s</strong>.',
            [\OCP\Util::sanitizeHTML($_['email'])]
        )); ?>
    </p>
    <p><?php p($l->t('Please click the link in that email to activate your account. The link is valid for 24 hours.')); ?></p>
</div>
