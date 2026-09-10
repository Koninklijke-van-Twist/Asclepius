<?php if ($view === 'api'): ?>
    <?php
    $apiDocsMarkdown = loadApiDocsMarkdown();
    $apiDocsHtml = $apiDocsMarkdown !== '' ? formatApiDocsHtml($apiDocsMarkdown) : '';
    $apiDocsRawUrl = 'docs/api.md';
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <section class="panel api-docs-panel">
        <h2><?= h(__('api_docs.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('api_docs.intro')) ?></p>

        <a hidden href="<?= h($apiDocsRawUrl) ?>" rel="alternate" type="text/markdown"
            data-api-spec="<?= h($apiDocsRawUrl) ?>"><?= h(__('api_docs.raw_label')) ?></a>

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
