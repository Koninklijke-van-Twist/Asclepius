<header class="hero">
    <div class="brand">
        <img class="brand-logo" src="kvtlogo.png" alt="KVT logo">
        <div>
            <p class="eyebrow">Asclepius</p>
            <h1>ICT ticketsysteem</h1>
            <p><?= $userIsAdmin ? 'Beheer alle tickets, behandel reacties en verdeel werk slim over ICT.' : 'Maak eenvoudig een ICT-ticket aan en volg je meldingen.' ?>
            </p>
            <?php if ($localRequester): ?>
                <p class="dev-note">Ontwikkelen/testen: gebruik eventueel
                    <code>?dev_user=naam@kvt.nl&amp;dev_admin=0</code> of <code>1</code> om rollen lokaal te
                    wisselen.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-actions" <?= $isBigscreen ? ' hidden' : '' ?>>
        <span class="user-chip"><?= h($userEmail) ?><?= $userIsAdmin ? ' · admin' : '' ?></span>
        <a class="nav-link <?= !$isAdminPortal ? 'active' : '' ?>" href="index.php">Nieuw ticket</a>
        <?php if ($userIsAdmin): ?>
            <a class="nav-link <?= $isAdminPortal && $view === 'overview' ? 'active' : '' ?>"
                href="admin.php">ICT-overzicht</a>
            <a class="nav-link <?= $isAdminPortal && $view === 'settings' ? 'active' : '' ?>"
                href="admin.php?view=settings">Instellingen</a>
            <a class="nav-link <?= $isAdminPortal && $view === 'stats' ? 'active' : '' ?>"
                href="admin.php?view=stats">ICT-stats</a>
        <?php endif; ?>
    </div>
</header>