<?php if (!$isAdminPortal): ?>
    <section class="panel">
        <h2>Nieuw ticket maken</h2>
        <p class="panel-intro">Een ticket krijgt automatisch een ICT-medewerker toegewezen op basis van
            categorie en actuele openstaande werkdruk.</p>
        <form method="post" action="<?= h($currentPage) ?>" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="form_action" value="create_ticket">
            <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">

            <div class="form-grid two-columns">
                <label>
                    Titel
                    <input type="text" name="title" maxlength="150" placeholder="Bijvoorbeeld: Nieuwe scanner nodig"
                        required>
                </label>
                <label>
                    Categorie
                    <select name="category" required>
                        <option value="">Kies een categorie</option>
                        <?php foreach (TICKET_CATEGORIES as $category): ?>
                            <option value="<?= h($category) ?>"><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label>
                Beschrijving
                <textarea name="description" placeholder="Beschrijf het probleem of de aanvraag zo duidelijk mogelijk."
                    required></textarea>
            </label>

            <div class="checkbox-stack">
                <label class="checkbox-line">
                    <input type="checkbox" name="priority_blocked" id="priority_blocked" value="1">
                    <span>Mijn werkzaamheden worden belemmerd</span>
                </label>
                <label class="checkbox-line" id="priority_fully_blocked_wrap" hidden>
                    <input type="checkbox" name="priority_fully_blocked" id="priority_fully_blocked" value="1">
                    <span>Ik kan niet verder werken tot dit opgelost is</span>
                </label>
            </div>

            <?php if ($userIsAdmin): ?>
                <label>
                    Gebruiker
                    <input type="email" name="requester_email" maxlength="200"
                        placeholder="naam@kvt.nl (optioneel, voor ticket namens iemand anders)">
                    <span class="hint">Alleen voor ICT: dit e-mailadres wordt als aanvrager gebruikt als je het
                        invult.</span>
                </label>
            <?php endif; ?>

            <label>
                Screenshots of documenten
                <input type="file" name="ticket_attachments[]" multiple>
                <span class="hint">Per bestand maximaal 20 MB.</span>
            </label>

            <div class="button-row">
                <button type="submit">Ticket indienen</button>
            </div>
        </form>
    </section>
<?php endif; ?>