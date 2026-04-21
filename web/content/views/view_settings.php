            <?php if ($canManageTickets && $view === 'settings'): ?>
                <section class="panel">
                    <h2>Instellingen per ICT-gebruiker</h2>
                    <p class="panel-intro">Zet per ICT-collega categorieën aan of uit en markeer medewerkers als afwezig.
                        Nieuwe tickets worden automatisch toegewezen aan de minst belaste beschikbare collega.</p>
                    <?php if ($localRequester): ?>
                        <p class="hint">
                            DB: <code><?= h($storageDiagnostics['database_path']) ?></code><br>
                            Bestand: <?= $storageDiagnostics['database_exists'] ? 'bestaat' : 'ontbreekt' ?> ·
                            map schrijfbaar: <?= $storageDiagnostics['database_directory_writable'] ? 'ja' : 'nee' ?> ·
                            bestand schrijfbaar: <?= $storageDiagnostics['database_writable'] ? 'ja' : 'nee' ?>
                        </p>
                    <?php endif; ?>
                    <form method="post" action="admin.php?view=settings" class="form-grid">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ICT-gebruiker</th>
                                        <th>Open tickets</th>
                                        <?php foreach (TICKET_CATEGORIES as $category): ?>
                                            <th><?= h($category) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ictUsers as $ictUser):
                                        $ictUser = strtolower($ictUser);
                                        $isAvailable = !empty($availabilityByIctUser[$ictUser]); ?>
                                        <tr class="settings-row <?= $isAvailable ? '' : 'is-away' ?>" data-settings-row>
                                            <td class="user-color-cell settings-user-cell"
                                                style="--assignee-color: <?= h(emailToHexColor($ictUser)) ?>;">
                                                <label class="vacation-toggle">
                                                    <span class="availability-slot">
                                                        <input type="checkbox" class="availability-checkbox"
                                                            name="availability[<?= h($ictUser) ?>]" value="1" <?= $isAvailable ? 'checked' : '' ?>>
                                                    </span>
                                                    <span
                                                        class="assignee-badge vacation-badge <?= $isAvailable ? '' : 'is-away' ?>"
                                                        style="--assignee-color: <?= h($isAvailable ? emailToHexColor($ictUser) : '#94a3b8') ?>;">
                                                        <?= h($ictUser) ?>
                                                    </span>
                                                    <span class="vacation-indicator" <?= $isAvailable ? 'hidden' : '' ?>>🌴</span>
                                                </label>
                                            </td>
                                            <td class="open-load-cell"><?= (int) ($loadByIctUser[$ictUser] ?? 0) ?></td>
                                            <?php foreach (TICKET_CATEGORIES as $category): ?>
                                                <td class="setting-checkbox-cell">
                                                    <input type="checkbox" name="settings[<?= h($ictUser) ?>][<?= h($category) ?>]"
                                                        value="1" <?= !empty($settingsMatrix[$ictUser][$category]) ? 'checked' : '' ?>>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="button-row">
                            <button type="submit" name="form_action" value="save_settings">Instellingen
                                opslaan</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
