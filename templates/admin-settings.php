<?php
/**
 * Mount point for the Vue 3 admin UI. The bundled script (invite_registration-admin)
 * mounts onto #invite_registration-admin-settings.
 */
?>
<div id="invite_registration-admin-settings" class="section">
    <!-- Vue app mounts here. Fallback content for no-JS: -->
    <noscript>
        <h2><?php p($l->t('Invite Registration')); ?></h2>
        <p><?php p($l->t('This settings page requires JavaScript to be enabled.')); ?></p>
    </noscript>
</div>
