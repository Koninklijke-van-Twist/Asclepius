<?php if ($canManageTickets && $isLimitedIct && $view === 'settings'): ?>
    <section class="panel">
        <h2><?= h($ictRoleName !== '' ? $ictRoleName : __('roles.role_settings_heading')) ?></h2>
        <p class="panel-intro"><?= h(__('roles.role_settings_intro')) ?></p>
        <form method="post" action="admin.php?view=settings" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="form_action" value="save_role_member_settings">
            <input type="hidden" name="role_id" value="<?= (int) ($ictRole['role_id'] ?? 0) ?>">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th><?= h(__('settings.col_user')) ?></th>
                            <?php foreach ($roleSettingsCategories as $category): ?>
                                <th><?= h(translateCategory($category)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roleSettingsEmails as $roleUser):
                            $roleUser = strtolower((string) $roleUser);
                            $isAvailable = !empty($roleSettingsAvailability[$roleUser]);
                            if (isset($availabilityByIctUser[$roleUser])) {
                                $isAvailable = !empty($availabilityByIctUser[$roleUser]);
                            }
                            $janusLockReason = (string) ($janusLocksByUser[$roleUser] ?? '');
                            $isJanusLocked = $janusLockReason !== '';
                            $vacationTooltip = $isJanusLocked
                                ? janusAwayLockTooltip($janusLockReason, $janusWeekday ?? null)
                                : '';
                            ?>
                            <tr class="settings-row <?= $isAvailable ? '' : 'is-away' ?>" data-settings-row
                                data-settings-user="<?= h($roleUser) ?>"
                                <?= $isJanusLocked ? 'data-janus-locked="1"' : '' ?>>
                                <td class="user-color-cell settings-user-cell"
                                    style="--assignee-color: <?= h(emailToHexColor($roleUser)) ?>;">
                                    <label class="vacation-toggle"<?= $vacationTooltip !== '' ? ' title="' . h($vacationTooltip) . '"' : '' ?>>
                                        <span class="availability-slot">
                                            <input type="checkbox" class="availability-checkbox"
                                                name="availability[<?= h($roleUser) ?>]" value="1"
                                                <?= $isAvailable ? 'checked' : '' ?>
                                                <?= $isJanusLocked ? 'disabled aria-disabled="true"' : '' ?>>
                                        </span>
                                        <span class="assignee-badge vacation-badge <?= $isAvailable ? '' : 'is-away' ?>"
                                            style="--assignee-color: <?= h($isAvailable ? emailToHexColor($roleUser) : '#94a3b8') ?>;">
                                            <?= renderUserDisplayLabel($roleUser) ?>
                                        </span>
                                        <span class="vacation-indicator" <?= $isAvailable ? 'hidden' : '' ?>>🌴</span>
                                    </label>
                                </td>
                                <?php foreach ($roleSettingsCategories as $category): ?>
                                    <td class="setting-checkbox-cell">
                                        <input type="checkbox"
                                            name="settings[<?= h($roleUser) ?>][<?= h($category) ?>]"
                                            value="1"
                                            <?= !empty($roleSettingsMatrix[$roleUser][$category]) ? 'checked' : '' ?>>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="primary-button"><?= h(__('settings.btn_save')) ?></button>
        </form>
    </section>
<?php endif; ?>
