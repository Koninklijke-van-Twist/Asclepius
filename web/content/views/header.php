<header class="hero">
    <div class="brand">
        <img class="brand-logo" src="kvtlogo.png" alt="KVT logo">
        <div>
            <p class="eyebrow">Asclepius</p>
            <h1><?= h(__('header.title')) ?></h1>
            <p><?= $userIsAdmin ? h(__('header.subtitle_admin')) : h(__('header.subtitle_user')) ?></p>
            <?php if ($localRequester): ?>
                <p class="dev-note">Ontwikkelen/testen: gebruik eventueel
                    <code>?dev_user=naam@kvt.nl&amp;dev_admin=0</code> of <code>1</code> om rollen lokaal te
                    wisselen.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-actions" <?= $isBigscreen ? ' hidden' : '' ?>>
        <span class="user-chip"><?= h($userEmail) ?><?= $userIsAdmin ? h(__('header.admin_suffix')) : '' ?></span>
        <a class="nav-link <?= !$isAdminPortal ? 'active' : '' ?>" href="index.php"><?= h(__('nav.new_ticket')) ?></a>
        <?php if ($userIsAdmin): ?>
            <a class="nav-link <?= $isAdminPortal && $view === 'overview' ? 'active' : '' ?>"
                href="admin.php"><?= h(__('nav.ict_overview')) ?></a>
            <a class="nav-link <?= $isAdminPortal && $view === 'settings' ? 'active' : '' ?>"
                href="admin.php?view=settings"><?= h(__('nav.settings')) ?></a>
            <a class="nav-link <?= $isAdminPortal && $view === 'stats' ? 'active' : '' ?>"
                href="admin.php?view=stats"><?= h(__('nav.ict_stats')) ?></a>
        <?php endif; ?>

        <div class="lang-switcher" aria-label="Language">
            <?php $currentLang = getCurrentLanguage(); ?>
            <button class="lang-current" aria-haspopup="true" aria-expanded="false" id="lang-btn"
                title="<?= h(SUPPORTED_LANGUAGES[$currentLang]['label']) ?>">
                <?= SUPPORTED_LANGUAGES[$currentLang]['flag'] ?>
            </button>
            <ul class="lang-dropdown" role="menu" aria-labelledby="lang-btn" hidden>
                <?php foreach (SUPPORTED_LANGUAGES as $code => $info): ?>
                    <li role="none">
                        <a role="menuitem" href="?<?= h(http_build_query(array_merge($_GET, ['lang' => $code]))) ?>"
                            class="lang-option <?= $code === $currentLang ? 'is-active' : '' ?>">
                            <?= $info['flag'] ?>     <?= h($info['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</header>
<script>
    (function ()
    {
        var btn = document.getElementById('lang-btn');
        var dropdown = btn ? btn.nextElementSibling : null;
        if (!btn || !dropdown) { return; }
        btn.addEventListener('click', function (e)
        {
            e.stopPropagation();
            var open = !dropdown.hidden;
            dropdown.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
        });
        document.addEventListener('click', function ()
        {
            dropdown.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        });
    }());
</script>