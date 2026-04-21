<?php
$flagSvgs = [
    'nl' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#AE1C28"/><rect width="900" height="400" fill="#fff"/><rect width="900" height="200" fill="#fff"/><rect width="900" height="200" y="0" fill="#AE1C28"/><rect width="900" height="200" y="200" fill="#fff"/><rect width="900" height="200" y="400" fill="#21468B"/></svg>',
    'en' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 60 40"><clipPath id="a"><path d="M0 0v40h60V0z"/></clipPath><clipPath id="b"><path d="M30 20h30v20zv20H0zH0V0zV0h30z"/></clipPath><g clip-path="url(#a)"><path d="M0 0v40h60V0z" fill="#012169"/><path d="M0 0l60 40m0-40L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0l60 40m0-40L0 40" clip-path="url(#b)" stroke="#C8102E" stroke-width="5"/><path d="M30 0v40M0 20h60" stroke="#fff" stroke-width="13"/><path d="M30 0v40M0 20h60" stroke="#C8102E" stroke-width="8"/></g></svg>',
    'de' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 5 3"><rect width="5" height="3" y="0" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>',
    'fr' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>',
];
$currentLang = getCurrentLanguage();
?>
<header class="hero">

    <?php /* Taalkiezer — absoluut rechtsboven, buiten de hero-actions stroom */ ?>
    <div class="lang-switcher" aria-label="Language" id="lang-switcher">
        <button class="lang-current" aria-haspopup="true" aria-expanded="false" id="lang-btn"
            title="<?= h(SUPPORTED_LANGUAGES[$currentLang]['label']) ?>">
            <img src="data:image/svg+xml,<?= rawurlencode($flagSvgs[$currentLang]) ?>"
                 alt="<?= h(SUPPORTED_LANGUAGES[$currentLang]['label']) ?>"
                 width="28" height="20">
        </button>
        <ul class="lang-dropdown" role="menu" aria-labelledby="lang-btn" hidden>
            <?php foreach (SUPPORTED_LANGUAGES as $code => $info): ?>
                <li role="none">
                    <a role="menuitem"
                        href="?<?= h(http_build_query(array_merge($_GET, ['lang' => $code]))) ?>"
                        class="lang-option <?= $code === $currentLang ? 'is-active' : '' ?>">
                        <img src="data:image/svg+xml,<?= rawurlencode($flagSvgs[$code]) ?>"
                             alt="<?= h($info['label']) ?>"
                             width="22" height="15">
                        <?= h($info['label']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

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
    </div>
</header>
<script>
(function () {
    var btn = document.getElementById('lang-btn');
    var dropdown = btn ? btn.nextElementSibling : null;
    if (!btn || !dropdown) { return; }
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = !dropdown.hidden;
        dropdown.hidden = open;
        btn.setAttribute('aria-expanded', String(!open));
    });
    document.addEventListener('click', function () {
        dropdown.hidden = true;
        btn.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            dropdown.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        }
    });
}());
</script>