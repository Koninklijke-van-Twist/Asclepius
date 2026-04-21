<?php

require_once __DIR__ . '/content/bootstrap.php';
require_once __DIR__ . '/content/constants.php';
require_once __DIR__ . '/content/localization.php';
require_once __DIR__ . '/content/helpers.php';
require_once __DIR__ . '/content/mail.php';
require_once __DIR__ . '/content/variables.php';
require_once __DIR__ . '/content/actions.php';
require_once __DIR__ . '/content/data.php';

?>
<!DOCTYPE html>
<html lang="<?= h(getCurrentLanguage()) ?>">

<?php require __DIR__ . '/content/views/head.php'; ?>


<body<?= $isBigscreen ? ' style="overflow:hidden;"' : '' ?>>
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
                <div class="flash error"><?= h(__('flash.db_error_prefix')) ?> <?= h($storeError) ?></div>
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
