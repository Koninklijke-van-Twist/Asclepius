<?php if ($canManageTickets && $view === 'api'): ?>
    <?php
    $apiDocsMarkdown = loadApiDocsMarkdown();
    $apiDocsHtml = $apiDocsMarkdown !== '' ? formatApiDocsHtml($apiDocsMarkdown) : '';
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        (function ()
        {
            if (typeof hljs === 'undefined')
            {
                return;
            }

            document.querySelectorAll('.api-docs-body pre.api-docs-code code').forEach(function (block)
            {
                hljs.highlightElement(block);
            });
        }());
    </script>
<?php endif; ?>
