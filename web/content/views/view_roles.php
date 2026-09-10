<?php if ($canManageIctRoles && $view === 'roles'): ?>
    <section class="panel">
        <h2><?= h(__('roles.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('roles.intro')) ?></p>

        <form method="post" action="admin.php?view=roles" class="form-grid roles-add-member-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="form_action" value="add_ict_role_member">
            <div class="form-row">
                <label>
                    <?= h(__('roles.member_email')) ?>
                    <?php
                    $roleMemberEmailSuggestions = [];
                    $fullAdminLookup = array_fill_keys(extractIctUserEmails($ictUsers), true);
                    $alreadyMemberLookup = ($store instanceof TicketStore)
                        ? array_fill_keys($store->listIctRoleMemberEmails(), true)
                        : [];
                    foreach (getUserDisplayNameMap() as $suggestionEmail => $suggestionName) {
                        $suggestionEmail = strtolower(trim((string) $suggestionEmail));
                        if ($suggestionEmail === ''
                            || !filter_var($suggestionEmail, FILTER_VALIDATE_EMAIL)
                            || isset($fullAdminLookup[$suggestionEmail])
                            || isset($alreadyMemberLookup[$suggestionEmail])
                        ) {
                            continue;
                        }
                        $label = trim((string) $suggestionName);
                        $roleMemberEmailSuggestions[$suggestionEmail] = $label !== '' ? $label : $suggestionEmail;
                    }
                    asort($roleMemberEmailSuggestions, SORT_NATURAL | SORT_FLAG_CASE);
                    ?>
                    <input type="email" name="member_email" required placeholder="naam@kvt.nl"
                        list="role-member-email-suggestions" autocomplete="off" spellcheck="false">
                    <datalist id="role-member-email-suggestions">
                        <?php foreach ($roleMemberEmailSuggestions as $suggestionEmail => $suggestionLabel): ?>
                            <option value="<?= h($suggestionEmail) ?>"><?= h($suggestionLabel) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>
                <label>
                    <?= h(__('roles.member_role')) ?>
                    <select name="role_id" required>
                        <option value=""><?= h(__('roles.choose_role')) ?></option>
                        <?php foreach ($ictRolesList as $roleRow): ?>
                            <option value="<?= (int) $roleRow['id'] ?>"><?= h((string) $roleRow['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="primary-button"><?= h(__('roles.add_member')) ?></button>
            </div>
        </form>

        <h3 class="settings-section-heading"><?= h(__('roles.roles_heading')) ?></h3>
        <form method="post" action="admin.php?view=roles" class="form-grid roles-create-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="form_action" value="create_ict_role">
            <div class="form-row">
                <label>
                    <?= h(__('roles.new_role_name')) ?>
                    <input type="text" name="role_name" required maxlength="80">
                </label>
                <button type="submit" class="secondary-button"><?= h(__('roles.create_role')) ?></button>
            </div>
        </form>

        <div class="roles-list">
            <?php if ($ictRolesList === []): ?>
                <p class="hint"><?= h(__('roles.empty')) ?></p>
            <?php endif; ?>
            <?php foreach ($ictRolesList as $roleRow):
                $roleId = (int) ($roleRow['id'] ?? 0);
                $roleName = (string) ($roleRow['name'] ?? '');
                $roleColor = (string) ($roleRow['color'] ?? '#64748b');
                $roleCategories = is_array($roleRow['categories'] ?? null) ? $roleRow['categories'] : [];
                $roleMembers = $store instanceof TicketStore ? $store->listIctRoleMembers($roleId) : [];
                $memberEmails = array_map(static fn(array $m): string => (string) ($m['user_email'] ?? ''), $roleMembers);
                $memberMatrix = $store instanceof TicketStore
                    ? $store->getCategorySettingsForEmails($memberEmails, $roleCategories)
                    : [];
                $memberAvailability = $store instanceof TicketStore
                    ? $store->getAvailabilityForEmails($memberEmails)
                    : [];
                foreach ($memberEmails as $memberEmail) {
                    if (isset($availabilityByIctUser[$memberEmail])) {
                        $memberAvailability[$memberEmail] = !empty($availabilityByIctUser[$memberEmail]);
                    }
                }
                ?>
                <article class="role-card" style="--role-color: <?= h($roleColor) ?>;" data-role-id="<?= $roleId ?>">
                    <div class="role-card-head">
                        <span class="role-color-chip" aria-hidden="true"></span>
                        <strong class="role-card-name"><?= h($roleName) ?></strong>
                        <span class="hint"><?= (int) ($roleRow['member_count'] ?? 0) ?> <?= h(__('roles.members_count')) ?></span>
                        <div class="role-card-actions">
                            <button type="button" class="secondary-button" data-role="role-categories-open"
                                title="<?= h(__('roles.edit_categories')) ?>" aria-label="<?= h(__('roles.edit_categories')) ?>">✏️</button>
                            <button type="button" class="secondary-button" data-role="role-members-open"
                                title="<?= h(__('roles.edit_members')) ?>" aria-label="<?= h(__('roles.edit_members')) ?>">👤</button>
                            <?php if ((int) ($roleRow['member_count'] ?? 0) === 0): ?>
                                <button type="button" class="secondary-button" data-role="role-delete-open"
                                    title="<?= h(__('roles.delete_role')) ?>" aria-label="<?= h(__('roles.delete_role')) ?>">🗑️</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="role-categories-summary">
                        <?php if ($roleCategories === []): ?>
                            <?= h(__('roles.no_categories')) ?>
                        <?php else: ?>
                            <?= h(implode(', ', array_map('translateCategory', $roleCategories))) ?>
                        <?php endif; ?>
                    </p>

                    <?php if ((int) ($roleRow['member_count'] ?? 0) === 0): ?>
                        <div class="ticket-participants-modal" data-role="role-delete-modal" hidden>
                            <div class="ticket-participants-modal-card role-confirm-card">
                                <div class="ticket-participants-modal-head">
                                    <h3><?= h(__('roles.delete_role')) ?></h3>
                                    <button type="button" class="secondary-button" data-role="role-modal-close">✕</button>
                                </div>
                                <p class="role-confirm-copy"><?= h(__('roles.delete_confirm')) ?></p>
                                <div class="role-modal-actions">
                                    <button type="button" class="secondary-button" data-role="role-modal-close"><?= h(__('roles.cancel')) ?></button>
                                    <form method="post" action="admin.php?view=roles" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="form_action" value="delete_ict_role">
                                        <input type="hidden" name="role_id" value="<?= $roleId ?>">
                                        <button type="submit" class="danger-button"><?= h(__('roles.delete_role')) ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="ticket-participants-modal" data-role="role-categories-modal" hidden>
                        <div class="ticket-participants-modal-card">
                            <div class="ticket-participants-modal-head">
                                <h3><?= h(__('roles.edit_categories')) ?> — <?= h($roleName) ?></h3>
                                <button type="button" class="secondary-button" data-role="role-modal-close">✕</button>
                            </div>
                            <form method="post" action="admin.php?view=roles" class="form-grid">
                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                <input type="hidden" name="form_action" value="save_ict_role_categories">
                                <input type="hidden" name="role_id" value="<?= $roleId ?>">
                                <div class="role-category-checks">
                                    <?php foreach (TICKET_CATEGORIES as $category): ?>
                                        <label class="role-check-line">
                                            <input type="checkbox" name="categories[]" value="<?= h($category) ?>"
                                                <?= in_array($category, $roleCategories, true) ? 'checked' : '' ?>>
                                            <span><?= h(translateCategory($category)) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="primary-button"><?= h(__('roles.save')) ?></button>
                            </form>
                        </div>
                    </div>

                    <div class="ticket-participants-modal" data-role="role-members-modal" hidden>
                        <div class="ticket-participants-modal-card">
                            <div class="ticket-participants-modal-head">
                                <h3><?= h(__('roles.edit_members')) ?> — <?= h($roleName) ?></h3>
                                <button type="button" class="secondary-button" data-role="role-modal-close">✕</button>
                            </div>
                            <?php if ($memberEmails === []): ?>
                                <p class="hint"><?= h(__('roles.no_members')) ?></p>
                            <?php else: ?>
                                <form method="post" action="admin.php?view=roles" class="form-grid">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                    <input type="hidden" name="form_action" value="save_role_member_settings">
                                    <input type="hidden" name="role_id" value="<?= $roleId ?>">
                                    <div class="table-wrap">
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th><?= h(__('settings.col_user')) ?></th>
                                                    <?php foreach ($roleCategories as $category): ?>
                                                        <th><?= h(translateCategory($category)) ?></th>
                                                    <?php endforeach; ?>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($memberEmails as $memberEmail):
                                                    $isAvailable = !empty($memberAvailability[$memberEmail]);
                                                    $janusLockReason = (string) ($janusLocksByUser[$memberEmail] ?? '');
                                                    $isJanusLocked = $janusLockReason !== '';
                                                    $vacationTooltip = $isJanusLocked
                                                        ? janusAwayLockTooltip($janusLockReason, $janusWeekday ?? null)
                                                        : '';
                                                    ?>
                                                    <tr class="settings-row <?= $isAvailable ? '' : 'is-away' ?>" data-settings-row
                                                        data-settings-user="<?= h($memberEmail) ?>"
                                                        <?= $isJanusLocked ? 'data-janus-locked="1"' : '' ?>>
                                                        <td class="user-color-cell settings-user-cell"
                                                            style="--assignee-color: <?= h(emailToHexColor($memberEmail)) ?>;">
                                                            <label class="vacation-toggle"<?= $vacationTooltip !== '' ? ' title="' . h($vacationTooltip) . '"' : '' ?>>
                                                                <span class="availability-slot">
                                                                    <input type="checkbox" class="availability-checkbox"
                                                                        name="availability[<?= h($memberEmail) ?>]" value="1"
                                                                        <?= $isAvailable ? 'checked' : '' ?>
                                                                        <?= $isJanusLocked ? 'disabled aria-disabled="true"' : '' ?>
                                                                        <?= $vacationTooltip !== '' ? ' title="' . h($vacationTooltip) . '" aria-label="' . h($vacationTooltip) . '"' : '' ?>>
                                                                </span>
                                                                <span class="assignee-badge vacation-badge <?= $isAvailable ? '' : 'is-away' ?>"
                                                                    style="--assignee-color: <?= h($isAvailable ? emailToHexColor($memberEmail) : '#94a3b8') ?>;">
                                                                    <?= renderUserDisplayLabel($memberEmail) ?>
                                                                </span>
                                                                <span class="vacation-indicator" <?= $isAvailable ? 'hidden' : '' ?>>🌴</span>
                                                            </label>
                                                        </td>
                                                        <?php foreach ($roleCategories as $category): ?>
                                                            <td class="setting-checkbox-cell">
                                                                <input type="checkbox"
                                                                    name="settings[<?= h($memberEmail) ?>][<?= h($category) ?>]"
                                                                    value="1"
                                                                    <?= !empty($memberMatrix[$memberEmail][$category]) ? 'checked' : '' ?>>
                                                            </td>
                                                        <?php endforeach; ?>
                                                        <td>
                                                            <button type="button" class="secondary-button"
                                                                data-role="role-remove-member-open"
                                                                data-member-email="<?= h($memberEmail) ?>">
                                                                <?= h(__('roles.remove_member')) ?>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <button type="submit" class="primary-button"><?= h(__('roles.save')) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php foreach ($memberEmails as $memberEmail): ?>
                        <div class="ticket-participants-modal" data-role="role-remove-member-modal"
                            data-member-email="<?= h($memberEmail) ?>" hidden>
                            <div class="ticket-participants-modal-card role-confirm-card">
                                <div class="ticket-participants-modal-head">
                                    <h3><?= h(__('roles.remove_member')) ?></h3>
                                    <button type="button" class="secondary-button" data-role="role-modal-close">✕</button>
                                </div>
                                <p class="role-confirm-copy"><?= h(__('roles.remove_member_confirm')) ?></p>
                                <p class="hint"><?= h(formatUserDisplayName($memberEmail)) ?> (<?= h($memberEmail) ?>)</p>
                                <div class="role-modal-actions">
                                    <button type="button" class="secondary-button" data-role="role-modal-close"><?= h(__('roles.cancel')) ?></button>
                                    <form method="post" action="admin.php?view=roles" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="form_action" value="remove_ict_role_member">
                                        <input type="hidden" name="remove_member_email" value="<?= h($memberEmail) ?>">
                                        <button type="submit" class="danger-button"><?= h(__('roles.remove_member')) ?></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <script>
        (function () {
            function openRoleModal(modal) {
                if (!modal) {
                    return;
                }
                modal.hidden = false;
                modal.classList.add('is-open');
                document.documentElement.style.overflow = 'hidden';
            }

            function closeRoleModal(modal) {
                if (!modal) {
                    return;
                }
                modal.hidden = true;
                modal.classList.remove('is-open');
                if (!document.querySelector('.ticket-participants-modal.is-open')) {
                    document.documentElement.style.overflow = '';
                }
            }

            document.querySelectorAll('[data-role="role-categories-open"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var card = btn.closest('.role-card');
                    openRoleModal(card ? card.querySelector('[data-role="role-categories-modal"]') : null);
                });
            });
            document.querySelectorAll('[data-role="role-members-open"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var card = btn.closest('.role-card');
                    openRoleModal(card ? card.querySelector('[data-role="role-members-modal"]') : null);
                });
            });
            document.querySelectorAll('[data-role="role-delete-open"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var card = btn.closest('.role-card');
                    openRoleModal(card ? card.querySelector('[data-role="role-delete-modal"]') : null);
                });
            });
            document.querySelectorAll('[data-role="role-remove-member-open"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var card = btn.closest('.role-card');
                    var email = btn.getAttribute('data-member-email') || '';
                    if (!card || email === '') {
                        return;
                    }
                    var escapeCss = (window.CSS && typeof window.CSS.escape === 'function')
                        ? window.CSS.escape
                        : function (value) { return String(value).replace(/["\\]/g, '\\$&'); };
                    var confirmModal = card.querySelector('[data-role="role-remove-member-modal"][data-member-email="' + escapeCss(email) + '"]');
                    openRoleModal(confirmModal);
                });
            });
            document.querySelectorAll('[data-role="role-modal-close"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    closeRoleModal(btn.closest('.ticket-participants-modal'));
                });
            });
            document.querySelectorAll('.ticket-participants-modal').forEach(function (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeRoleModal(modal);
                    }
                });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') {
                    return;
                }
                var openModals = document.querySelectorAll('.roles-list .ticket-participants-modal.is-open');
                if (!openModals.length) {
                    return;
                }
                closeRoleModal(openModals[openModals.length - 1]);
            });
        })();
    </script>
<?php endif; ?>
