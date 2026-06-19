<?php if ($canManageTickets && $view === 'email_prefs'): ?>
    <section class="panel" data-email-prefs-section
        data-viewer-email="<?= h($userEmail) ?>"
        data-user-is-admin="<?= $userIsAdmin ? '1' : '0' ?>">        <h2><?= h(__('email_prefs.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('email_prefs.intro')) ?></p>
        <p class="hint email-prefs-feedback" data-email-prefs-feedback hidden></p>
        <ul class="email-prefs-list">
            <?php foreach (ADMIN_EMAIL_NOTIFICATION_TYPES as $notificationType): ?>
                <li class="email-prefs-item">
                    <label class="email-prefs-label">
                        <input type="checkbox" data-email-pref-type="<?= h($notificationType) ?>"
                            <?= !empty($adminEmailPreferences[$notificationType]) ? 'checked' : '' ?>>
                        <span><?= h(__('email_prefs.type_' . $notificationType)) ?></span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
