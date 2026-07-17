<?php if ($canManageTickets && $view === 'api'): ?>
    <?php
    $apiDocsMarkdown = loadApiDocsMarkdown();
    $apiDocsHtml = $apiDocsMarkdown !== '' ? formatApiDocsHtml($apiDocsMarkdown) : '';
    ?>
    <section class="panel api-docs-panel">
        <div class="api-docs-toolbar">
            <div>
                <h2><?= h(__('api_docs.heading')) ?></h2>
                <p class="panel-intro"><?= h(__('api_docs.intro')) ?></p>
            </div>
            <a class="secondary-button" href="admin.php?view=settings"><?= h(__('api_docs.back_to_settings')) ?></a>
        </div>

        <?php if ($apiDocsHtml === ''): ?>
            <p class="hint"><?= h(__('api_docs.missing')) ?></p>
        <?php else: ?>
            <div class="api-docs-body changelog-entry-body">
                <?= $apiDocsHtml ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
