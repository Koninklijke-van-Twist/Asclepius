<?php

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/constants.php';
require_once __DIR__ . '/content/localization.php';
require_once __DIR__ . '/content/helpers.php';
require_once __DIR__ . '/content/mail.php';
require_once __DIR__ . '/content/variables.php';
require_once __DIR__ . '/content/actions.php';
require_once __DIR__ . '/content/data.php';

$browserNotificationPollUrl = buildCurrentPageUrl($currentPage, ['_browser_notifications_poll' => '1'], ['_partial', '_tickets_poll', '_bigscreen_poll', '_browser_notifications_poll', 'reset_filters']);
$browserNotificationTargetPage = $userIsAdmin ? 'admin.php' : 'index.php';
$browserNotificationOpenUrlTemplate = $browserNotificationTargetPage . '?open=__TICKET_ID__';
$webPushSubscriptionUrl = buildCurrentPageUrl($currentPage, ['_webpush_subscription' => '1'], ['_partial', '_tickets_poll', '_bigscreen_poll', '_browser_notifications_poll', '_webpush_subscription', 'reset_filters']);
$webPushServiceWorkerUrl = 'sw.js';

?>
<!DOCTYPE html>
<html lang="<?= h(getCurrentLanguage()) ?>">

<?php require __DIR__ . '/content/views/head.php'; ?>


<body<?= $isBigscreen ? ' style="overflow:hidden;"' : '' ?>
    data-browser-notification-poll-url="<?= h($browserNotificationPollUrl) ?>"
    data-browser-notification-open-template="<?= h($browserNotificationOpenUrlTemplate) ?>"
    data-browser-notification-poll-interval="15000"
    data-webpush-subscribe-url="<?= h($webPushSubscriptionUrl) ?>"
    data-webpush-vapid-public-key="<?= h(WEB_PUSH_VAPID_PUBLIC_KEY) ?>"
    data-webpush-sw-url="<?= h($webPushServiceWorkerUrl) ?>"
    data-csrf-token="<?= h($csrfToken) ?>">
    <div class="page">
        <?php require __DIR__ . '/content/views/header.php'; ?>

        <?php if ($flashMessages !== []): ?>
            <div class="flash-stack">
                <?php foreach ($flashMessages as $flashMessage): ?>
                    <div class="flash <?= h((string) ($flashMessage['type'] ?? 'success')) ?>">
                        <?= h((string) ($flashMessage['message'] ?? '')) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($storeError !== null): ?>
            <div class="flash-stack">
                <div class="flash error"><?= h(__('flash.db_error_prefix')) ?>     <?= h($storeError) ?></div>
            </div>
        <?php endif; ?>

        <main class="layout">
            <?php require __DIR__ . '/content/views/view_new_ticket.php'; ?>

            <?php require __DIR__ . '/content/views/view_settings.php'; ?>

            <?php require __DIR__ . '/content/views/view_stats.php'; ?>

            <?php require __DIR__ . '/content/views/view_tickets.php'; ?>
        </main>
    </div>
    <?php require __DIR__ . '/content/views/page_js.php'; ?>
    <?php require __DIR__ . '/content/views/bigscreen_js.php'; ?>
    </body>

</html>