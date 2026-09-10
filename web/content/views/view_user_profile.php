<?php
if (!empty($isBigscreen) || empty($isAdminPortal)) {
    return;
}
?>
<aside class="user-profile-sidebar" id="user-profile-sidebar" hidden
    aria-label="<?= h(__('user_profile.aria_label')) ?>">
    <div class="user-profile-sidebar-head">
        <div class="user-profile-identity">
            <h2 class="user-profile-name" data-role="user-profile-name"></h2>
            <p class="user-profile-email" data-role="user-profile-email"></p>
        </div>
        <button type="button" class="user-profile-close" data-role="user-profile-close"
            aria-label="<?= h(__('user_profile.close')) ?>"
            title="<?= h(__('user_profile.close')) ?>">&times;</button>
    </div>
    <p class="user-profile-loading" data-role="user-profile-loading" hidden><?= h(__('user_profile.loading')) ?></p>
    <ul class="user-profile-stats" data-role="user-profile-stats" hidden></ul>
    <p class="user-profile-error" data-role="user-profile-error" hidden><?= h(__('user_profile.error')) ?></p>
</aside>
