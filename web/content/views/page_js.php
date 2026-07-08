<script>
    document.addEventListener('DOMContentLoaded', function ()
    {
        var blockedCheckbox = document.getElementById('priority_blocked');
        var fullyBlockedCheckbox = document.getElementById('priority_fully_blocked');
        var fullyBlockedWrap = document.getElementById('priority_fully_blocked_wrap');

        var syncPriorityVisibility = function ()
        {
            if (!blockedCheckbox || !fullyBlockedWrap || !fullyBlockedCheckbox)
            {
                return;
            }

            var wasHidden = fullyBlockedWrap.hidden;
            var showFullyBlocked = blockedCheckbox.checked;
            fullyBlockedWrap.hidden = !showFullyBlocked;

            if (showFullyBlocked && wasHidden)
            {
                fullyBlockedWrap.classList.remove('flash-blue');
                void fullyBlockedWrap.offsetWidth;
                fullyBlockedWrap.classList.add('flash-blue');
            }

            if (!showFullyBlocked)
            {
                fullyBlockedCheckbox.checked = false;
                fullyBlockedWrap.classList.remove('flash-blue');
            }
        };

        if (blockedCheckbox)
        {
            blockedCheckbox.addEventListener('change', syncPriorityVisibility);
            syncPriorityVisibility();
        }

        var escapeHtml = function (value)
        {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        var userDisplayNames = {};
        try
        {
            userDisplayNames = JSON.parse(document.body.getAttribute('data-user-display-names') || '{}') || {};
        } catch (parseError)
        {
            userDisplayNames = {};
        }

        var resolveUserDisplayName = function (email)
        {
            var normalized = String(email || '').trim().toLowerCase();
            if (!normalized)
            {
                return '';
            }

            return userDisplayNames[normalized] || normalized;
        };

        var SHORTCUT_KEY_DEFINITIONS = <?= json_encode(getShortcutKeyDefinitions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        var SHORTCUT_KEY_ALIAS_MAP = {};

        var normalizeShortcutKeyAlias = function (value)
        {
            return String(value || '').trim().toLowerCase().replace(/[^a-z0-9]/g, '');
        };

        var registerShortcutKeyAlias = function (alias, definition)
        {
            var rawAlias = String(alias || '').trim().toLowerCase();
            if (rawAlias !== '')
            {
                SHORTCUT_KEY_ALIAS_MAP[rawAlias] = definition;
            }

            var normalizedAlias = normalizeShortcutKeyAlias(alias);
            if (normalizedAlias !== '')
            {
                SHORTCUT_KEY_ALIAS_MAP[normalizedAlias] = definition;
            }
        };

        SHORTCUT_KEY_DEFINITIONS.forEach(function (definition)
        {
            (definition.aliases || []).forEach(function (alias)
            {
                registerShortcutKeyAlias(alias, definition);
            });
        });

        var getShortcutKeyDefinition = function (token)
        {
            var rawToken = String(token || '').trim().toLowerCase();
            var normalizedToken = normalizeShortcutKeyAlias(token);
            var definition = SHORTCUT_KEY_ALIAS_MAP[rawToken] || SHORTCUT_KEY_ALIAS_MAP[normalizedToken] || null;
            if (definition)
            {
                return definition;
            }

            if (/^[a-z0-9]$/.test(normalizedToken))
            {
                return { label: normalizedToken.toUpperCase(), icon: null };
            }

            if (/^f([1-9]|1[0-2])$/.test(normalizedToken))
            {
                return { label: normalizedToken.toUpperCase(), icon: null };
            }

            return null;
        };

        var KEY_PICKER_ICONS = {
            'windows': '<svg class="shortcut-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 3.5L11 2v9H2v-7.5zm11 7.5V2l11-1.5V11H13zM2 13h9v9L2 20.5V13zm11 0h11v10.5L13 22v-9z"/></svg>',
            'arrow-up': '<svg class="shortcut-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 4l6 6h-4v10h-4V10H6l6-6z"/></svg>',
            'arrow-down': '<svg class="shortcut-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 20l-6-6h4V4h4v10h4l-6 6z"/></svg>',
            'arrow-left': '<svg class="shortcut-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 12l6-6v4h10v4H10v4l-6-6z"/></svg>',
            'arrow-right': '<svg class="shortcut-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 12l-6 6v-4H4v-4h10V6l6 6z"/></svg>'
        };

        var renderShortcutKeyTokenHtml = function (token)
        {
            var definition = getShortcutKeyDefinition(token);
            if (!definition)
            {
                return null;
            }

            var iconHtml = KEY_PICKER_ICONS[definition.icon || ''] || '';
            var label = String(definition.label || '').trim();
            if (iconHtml === '' && label === '')
            {
                return null;
            }

            var labelHtml = label !== '' ? '<span class="shortcut-key-label">' + escapeHtml(label) + '</span>' : '';
            return '<span class="shortcut-key">' + iconHtml + labelHtml + '</span>';
        };

        var renderShortcutMarkup = function (escapedText)
        {
            return String(escapedText || '').replace(/\[([^\[\]\r\n]{1,24})\]/g, function (match, token)
            {
                return renderShortcutKeyTokenHtml(token) || match;
            }).replace(/(<span class="shortcut-key"(?:\s[^>]*)?>.*?<\/span>)\s*\+\s*(?=<span class="shortcut-key"(?:\s[^>]*)?>)/g, '$1<span class="shortcut-plus">+</span>');
        };

        var initializeEmailChipInputs = function (scope)
        {
            (scope || document).querySelectorAll('input[data-email-chip-input="1"]').forEach(function (input)
            {
                if (input.dataset.emailChipReady === '1')
                {
                    return;
                }

                var originalName = input.getAttribute('name') || '';
                var initialValue = input.value || '';
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = originalName;

                var chipField = document.createElement('div');
                chipField.className = 'email-chip-field';

                var chipInput = document.createElement('input');
                chipInput.type = 'text';
                chipInput.className = 'email-chip-input';
                chipInput.placeholder = input.getAttribute('placeholder') || '';
                chipInput.setAttribute('aria-label', input.getAttribute('aria-label') || chipInput.placeholder || 'Email');

                var chips = [];
                var addChip = function (value)
                {
                    var normalized = String(value || '').trim().toLowerCase();
                    if (!normalized)
                    {
                        return false;
                    }

                    var validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized);
                    if (!validEmail || chips.indexOf(normalized) !== -1)
                    {
                        return false;
                    }

                    chips.push(normalized);
                    return true;
                };

                var syncHiddenValue = function ()
                {
                    hiddenInput.value = chips.join(', ');
                };

                var renderChips = function ()
                {
                    chipField.querySelectorAll('.email-chip-item').forEach(function (node)
                    {
                        node.remove();
                    });

                    chips.forEach(function (email, index)
                    {
                        var chip = document.createElement('span');
                        chip.className = 'email-chip-item';
                        chip.textContent = email;

                        var removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'email-chip-remove';
                        removeButton.setAttribute('aria-label', 'Remove ' + email);
                        removeButton.textContent = '×';
                        removeButton.addEventListener('click', function ()
                        {
                            chips.splice(index, 1);
                            renderChips();
                        });

                        chip.appendChild(removeButton);
                        chipField.insertBefore(chip, chipInput);
                    });

                    syncHiddenValue();
                };

                var flushTypedEmailToChip = function ()
                {
                    var candidate = chipInput.value;
                    if (!candidate)
                    {
                        return;
                    }

                    if (addChip(candidate))
                    {
                        chipInput.value = '';
                        renderChips();
                    }
                };

                initialValue.split(/[;,\n\r]+/).forEach(function (token)
                {
                    addChip(token);
                });

                chipInput.addEventListener('keydown', function (event)
                {
                    if (event.key === ',' || event.key === 'Enter' || event.key === 'Tab')
                    {
                        if (chipInput.value.trim() !== '')
                        {
                            event.preventDefault();
                            flushTypedEmailToChip();
                        }
                    }
                });

                chipInput.addEventListener('blur', function ()
                {
                    flushTypedEmailToChip();
                });

                var parentForm = input.closest('form');
                if (parentForm)
                {
                    parentForm.addEventListener('submit', function ()
                    {
                        flushTypedEmailToChip();
                        syncHiddenValue();
                    });
                }

                input.removeAttribute('name');
                input.style.display = 'none';
                input.dataset.emailChipReady = '1';

                chipField.appendChild(chipInput);
                input.parentNode.insertBefore(hiddenInput, input);
                input.parentNode.insertBefore(chipField, input.nextSibling);

                renderChips();
            });
        };

        var accumulatedFileMap = new WeakMap();
        var DRAFT_ATTACHMENT_REMOVE_LABEL = <?= json_encode(__('ticket.draft_attachment_remove'), JSON_UNESCAPED_UNICODE) ?>;
        var DRAFT_ATTACHMENT_INSERT_LABEL = <?= json_encode(__('ticket.draft_attachment_insert'), JSON_UNESCAPED_UNICODE) ?>;
        var DRAFT_ATTACHMENT_IN_MESSAGE_LABEL = <?= json_encode(__('ticket.draft_attachment_in_message'), JSON_UNESCAPED_UNICODE) ?>;
        var EMAIL_PREFS_SAVED_LABEL = <?= json_encode(__('email_prefs.saved'), JSON_UNESCAPED_UNICODE) ?>;
        var EMAIL_PREFS_SAVE_FAILED_LABEL = <?= json_encode(__('email_prefs.save_failed'), JSON_UNESCAPED_UNICODE) ?>;
        var CHANGELOG_SAVED_LABEL = <?= json_encode(__('changelog.saved'), JSON_UNESCAPED_UNICODE) ?>;
        var CHANGELOG_SAVE_FAILED_LABEL = <?= json_encode(__('changelog.save_failed'), JSON_UNESCAPED_UNICODE) ?>;

        var buildAttachmentMarker = function (filename)
        {
            return '[[attachment:' + String(filename || '').replace(/[\[\]]/g, '') + ']]';
        };

        var getFileIdentity = function (file)
        {
            return String(file.name) + '|' + String(file.size) + '|' + String(file.lastModified);
        };

        var findMessageTextareaInForm = function (form)
        {
            if (!form)
            {
                return null;
            }

            var messageField = form.querySelector('textarea[name="message"]');
            if (messageField)
            {
                return messageField;
            }

            return form.querySelector('textarea[name="description"]');
        };

        var textareaContainsAttachmentMarker = function (textarea, filename)
        {
            if (!textarea || !filename)
            {
                return false;
            }

            return String(textarea.value || '').indexOf(buildAttachmentMarker(filename)) !== -1;
        };

        var insertAttachmentMarkerInTextarea = function (textarea, filename)
        {
            if (!textarea || !filename || textareaContainsAttachmentMarker(textarea, filename))
            {
                return;
            }

            var marker = buildAttachmentMarker(filename);
            var value = String(textarea.value || '');
            if (value !== '' && !value.endsWith('\n'))
            {
                value += '\n';
            }

            textarea.value = value + marker + '\n';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        var removeAttachmentMarkerFromTextarea = function (textarea, filename)
        {
            if (!textarea || !filename)
            {
                return;
            }

            var marker = buildAttachmentMarker(filename);
            var lines = String(textarea.value || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
            var changed = false;
            var filtered = lines.filter(function (line)
            {
                if (line.trim() === marker)
                {
                    changed = true;
                    return false;
                }

                return true;
            });

            if (!changed)
            {
                return;
            }

            textarea.value = filtered.join('\n').replace(/\n{3,}/g, '\n\n');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        };

        var getDraftAttachmentsList = function (input)
        {
            return input && input.parentElement
                ? input.parentElement.querySelector('[data-draft-attachments-list]')
                : null;
        };

        var syncAccumulatedFileInput = function (input, files)
        {
            var dataTransfer = new DataTransfer();
            files.forEach(function (file)
            {
                dataTransfer.items.add(file);
            });
            input.files = dataTransfer.files;

            var list = getDraftAttachmentsList(input);
            if (!list)
            {
                return;
            }

            var form = input.closest('form');
            var textarea = findMessageTextareaInForm(form);
            list.innerHTML = '';

            if (!files.length)
            {
                list.hidden = true;
                return;
            }

            list.hidden = false;
            files.forEach(function (file)
            {
                var item = document.createElement('li');
                item.className = 'draft-attachment-item';
                item.setAttribute('data-file-identity', getFileIdentity(file));

                var name = document.createElement('span');
                name.className = 'draft-attachment-name';
                name.textContent = file.name;
                item.appendChild(name);

                var actions = document.createElement('span');
                actions.className = 'draft-attachment-actions';

                if (!textareaContainsAttachmentMarker(textarea, file.name))
                {
                    var insertButton = document.createElement('button');
                    insertButton.type = 'button';
                    insertButton.className = 'draft-attachment-insert';
                    insertButton.setAttribute('data-draft-attachment-insert', '1');
                    insertButton.textContent = DRAFT_ATTACHMENT_INSERT_LABEL;
                    actions.appendChild(insertButton);
                }
                else
                {
                    var inMessage = document.createElement('span');
                    inMessage.className = 'draft-attachment-in-message';
                    inMessage.textContent = DRAFT_ATTACHMENT_IN_MESSAGE_LABEL;
                    actions.appendChild(inMessage);
                }

                var removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'draft-attachment-remove';
                removeButton.setAttribute('data-draft-attachment-remove', '1');
                removeButton.textContent = DRAFT_ATTACHMENT_REMOVE_LABEL;
                actions.appendChild(removeButton);

                item.appendChild(actions);
                list.appendChild(item);
            });
        };

        var removeFileFromAccumulatingInput = function (fileInput, fileIdentity)
        {
            var collected = accumulatedFileMap.get(fileInput) || [];
            var removedFile = null;
            var nextFiles = collected.filter(function (file)
            {
                if (getFileIdentity(file) === fileIdentity)
                {
                    removedFile = file;
                    return false;
                }

                return true;
            });

            accumulatedFileMap.set(fileInput, nextFiles);
            syncAccumulatedFileInput(fileInput, nextFiles);

            if (removedFile)
            {
                var form = fileInput.closest('form');
                var textarea = findMessageTextareaInForm(form);
                removeAttachmentMarkerFromTextarea(textarea, removedFile.name);
            }
        };

        var addFilesToAccumulatingInput = function (fileInput, newFiles)
        {
            var collected = accumulatedFileMap.get(fileInput) || [];
            var added = 0;

            newFiles.forEach(function (file)
            {
                var isDuplicate = collected.some(function (existing)
                {
                    return existing.name === file.name
                        && existing.size === file.size
                        && existing.lastModified === file.lastModified;
                });
                if (!isDuplicate)
                {
                    collected.push(file);
                    added++;
                }
            });

            if (added > 0)
            {
                accumulatedFileMap.set(fileInput, collected);
                syncAccumulatedFileInput(fileInput, collected);
            }

            return added;
        };

        var initializeAccumulatingFileInputs = function (scope)
        {
            (scope || document).querySelectorAll('input[type="file"][data-accumulate-files="1"]').forEach(function (input)
            {
                if (input.dataset.accumulateFilesReady === '1')
                {
                    return;
                }

                input.dataset.accumulateFilesReady = '1';
                accumulatedFileMap.set(input, []);

                input.addEventListener('change', function ()
                {
                    addFilesToAccumulatingInput(input, Array.from(input.files || []));
                });
            });
        };

        initializeEmailChipInputs(document);
        initializeAccumulatingFileInputs(document);

        document.addEventListener('click', function (event)
        {
            var target = event.target;
            if (!(target instanceof HTMLElement))
            {
                return;
            }

            var removeButton = target.closest('[data-draft-attachment-remove]');
            if (removeButton)
            {
                var item = removeButton.closest('.draft-attachment-item');
                var list = removeButton.closest('[data-draft-attachments-list]');
                var fileInput = list && list.parentElement
                    ? list.parentElement.querySelector('input[type="file"][data-accumulate-files="1"]')
                    : null;
                var fileIdentity = item ? item.getAttribute('data-file-identity') : '';
                if (fileInput && fileIdentity)
                {
                    removeFileFromAccumulatingInput(fileInput, fileIdentity);
                }
                return;
            }

            var insertButton = target.closest('[data-draft-attachment-insert]');
            if (insertButton)
            {
                var insertItem = insertButton.closest('.draft-attachment-item');
                var insertList = insertButton.closest('[data-draft-attachments-list]');
                var insertInput = insertList && insertList.parentElement
                    ? insertList.parentElement.querySelector('input[type="file"][data-accumulate-files="1"]')
                    : null;
                var insertIdentity = insertItem ? insertItem.getAttribute('data-file-identity') : '';
                if (!insertInput || !insertIdentity)
                {
                    return;
                }

                var collected = accumulatedFileMap.get(insertInput) || [];
                var selectedFile = collected.find(function (file)
                {
                    return getFileIdentity(file) === insertIdentity;
                });
                if (!selectedFile)
                {
                    return;
                }

                var form = insertInput.closest('form');
                var textarea = findMessageTextareaInForm(form);
                insertAttachmentMarkerInTextarea(textarea, selectedFile.name);
                syncAccumulatedFileInput(insertInput, collected);
            }
        });

        document.addEventListener('paste', function (event)
        {
            var items = event.clipboardData ? Array.from(event.clipboardData.items) : [];
            var imageItems = items.filter(function (item)
            {
                return item.kind === 'file' && item.type.indexOf('image/') === 0;
            });
            if (!imageItems.length)
            {
                return;
            }

            var target = event.target;
            if (!(target instanceof HTMLElement))
            {
                return;
            }

            var form = target.closest('form');
            if (!form)
            {
                return;
            }

            var fileInput = form.querySelector('input[type="file"][data-accumulate-files="1"]');
            if (!fileInput)
            {
                return;
            }

            var pad = function (n) { return String(n).padStart(2, '0'); };
            var pasteIndex = 0;
            var namedFiles = imageItems.map(function (item)
            {
                var file = item.getAsFile();
                if (!file)
                {
                    return null;
                }

                var ext = (file.type.split('/')[1] || 'png').split(';')[0].split('+')[0];
                var now = new Date();
                var timestamp = now.getFullYear()
                    + '-' + pad(now.getMonth() + 1)
                    + '-' + pad(now.getDate())
                    + '_' + pad(now.getHours())
                    + '-' + pad(now.getMinutes())
                    + '-' + pad(now.getSeconds());
                var suffix = pasteIndex > 0 ? '-' + (pasteIndex + 1) : '';
                pasteIndex++;
                return new File([file], 'attachment-' + timestamp + suffix + '.' + ext, {
                    type: file.type,
                    lastModified: file.lastModified || Date.now()
                });
            }).filter(Boolean);

            var textarea = findMessageTextareaInForm(form);
            var addedAny = false;
            namedFiles.forEach(function (file)
            {
                if (addFilesToAccumulatingInput(fileInput, [file]) > 0)
                {
                    addedAny = true;
                    insertAttachmentMarkerInTextarea(textarea, file.name);
                }
            });

            if (addedAny)
            {
                fileInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });

        var templateTicketCreateForm = document.getElementById('template-ticket-create-form');
        if (templateTicketCreateForm)
        {
            var templateTitleInput = document.getElementById('template_ticket_title');
            var templatePreviewRendered = document.getElementById('template_ticket_preview_rendered');
            var selectedTemplateIdsInput = document.getElementById('selected_template_ids');
            var renderTemplatePreviewKeyMarkup = function (value)
            {
                return renderShortcutMarkup(value);
            };

            var renderTemplatePreviewLine = function (line)
            {
                var checkboxMatch = String(line || '').match(/^(\s*)\[( |x|X)\]\s*(.*)$/);
                if (!checkboxMatch)
                {
                    return '<div class="template-preview-line">' + renderTemplatePreviewKeyMarkup(escapeHtml(line)) + '</div>';
                }

                var isChecked = String(checkboxMatch[2] || '').toLowerCase() === 'x';
                var checkboxText = String(checkboxMatch[3] || '');
                return '<label class="template-preview-checkbox-line">'
                    + '<input type="checkbox" disabled' + (isChecked ? ' checked' : '') + '>'
                    + '<span>' + (checkboxText !== '' ? renderTemplatePreviewKeyMarkup(escapeHtml(checkboxText)) : '&nbsp;') + '</span>'
                    + '</label>';
            };

            var renderTemplatePreviewHtml = function (value)
            {
                var normalized = String(value || '').replace(/\r\n?/g, '\n');
                if (normalized === '')
                {
                    return '';
                }

                return normalized.split('\n').map(renderTemplatePreviewLine).join('');
            };

            var updateTemplatePreview = function ()
            {
                var selectedIds = [];
                var selectedBodies = [];

                document.querySelectorAll('.template-fragment-checkbox').forEach(function (checkbox)
                {
                    if (!checkbox.checked)
                    {
                        return;
                    }

                    selectedIds.push(String(checkbox.value || ''));

                    var templateBody = '';
                    try
                    {
                        templateBody = JSON.parse(String(checkbox.getAttribute('data-template-body') || '""'));
                    }
                    catch (error)
                    {
                        templateBody = '';
                    }

                    if (String(templateBody).trim() !== '')
                    {
                        selectedBodies.push(String(templateBody));
                    }
                });

                if (selectedTemplateIdsInput)
                {
                    selectedTemplateIdsInput.value = selectedIds.join(',');
                }

                var title = templateTitleInput ? String(templateTitleInput.value || '').trim() : '';
                var body = selectedBodies.join('\n\n');
                var previewValue = title !== '' && body !== ''
                    ? title + '\n\n' + body
                    : (title !== '' ? title : body);

                if (templatePreviewRendered)
                {
                    templatePreviewRendered.innerHTML = renderTemplatePreviewHtml(previewValue);
                }
            };

            document.addEventListener('change', function (e)
            {
                if (e.target && e.target.matches && e.target.matches('.template-fragment-checkbox'))
                {
                    updateTemplatePreview();
                }
            });

            if (templateTitleInput)
            {
                templateTitleInput.addEventListener('input', updateTemplatePreview);
            }

            templateTicketCreateForm.addEventListener('submit', function (event)
            {
                updateTemplatePreview();

                var hasSelection = (selectedTemplateIdsInput && selectedTemplateIdsInput.value.trim() !== '');
                if (!hasSelection)
                {
                    event.preventDefault();
                    alert('<?= addslashes(__('template_ticket.select_template_error')) ?>');
                }
            });

            updateTemplatePreview();
        }

        var initializeTemplateCheckboxSync = function () { };

        var parseParticipantEmails = function (value, fallbackEmail)
        {
            if (Array.isArray(value))
            {
                return value.map(function (item)
                {
                    return String(item || '').trim().toLowerCase();
                }).filter(function (email)
                {
                    return email !== '';
                });
            }

            if (typeof value === 'string' && value.trim() !== '')
            {
                try
                {
                    var parsed = JSON.parse(value);
                    if (Array.isArray(parsed))
                    {
                        return parseParticipantEmails(parsed, fallbackEmail);
                    }
                } catch (error)
                {
                    // Ignore invalid serialized value and fallback below.
                }
            }

            var fallback = String(fallbackEmail || '').trim().toLowerCase();
            return fallback ? [fallback] : [];
        };

        var refreshPendingRemoveConstraints = function (ticketCard)
        {
            if (!ticketCard)
            {
                return;
            }

            var toggles = Array.prototype.slice.call(ticketCard.querySelectorAll('[data-role="participant-remove-toggle"]'));
            if (toggles.length <= 1)
            {
                toggles.forEach(function (toggle)
                {
                    toggle.classList.add('is-lock-protected');
                });
                return;
            }

            var pending = toggles.filter(function (toggle)
            {
                return toggle.classList.contains('is-pending-remove');
            });

            toggles.forEach(function (toggle)
            {
                var lockProtected = !toggle.classList.contains('is-pending-remove') && pending.length >= toggles.length - 1;
                toggle.classList.toggle('is-lock-protected', lockProtected);
            });
        };

        var renderParticipantManagerList = function (ticketCard, participantEmails, creatorEmail)
        {
            if (!ticketCard)
            {
                return;
            }

            var list = ticketCard.querySelector('[data-role="participant-chip-list"]');
            if (!list)
            {
                return;
            }

            var ticketId = Number(ticketCard.getAttribute('data-ticket-id') || 0);
            var normalizedCreator = String(creatorEmail || '').trim().toLowerCase();
            var emails = parseParticipantEmails(participantEmails, normalizedCreator);
            list.innerHTML = '';

            emails.forEach(function (email)
            {
                var removeToggle = document.createElement('button');
                removeToggle.type = 'button';
                removeToggle.className = 'participant-chip-form';
                removeToggle.setAttribute('data-role', 'participant-remove-toggle');
                removeToggle.setAttribute('data-ticket-id', String(ticketId));
                removeToggle.setAttribute('data-participant-email', email);

                var chip = document.createElement('span');
                chip.className = 'participant-chip' + (normalizedCreator !== '' && email === normalizedCreator ? ' is-requester' : '');

                var chipLabel = document.createElement('span');
                chipLabel.className = 'participant-chip-label';
                var displayName = resolveUserDisplayName(email);
                chipLabel.textContent = displayName;
                chip.appendChild(chipLabel);
                if (displayName !== email)
                {
                    chip.title = email;
                }

                var chipRemoveLabel = document.createElement('span');
                chipRemoveLabel.className = 'participant-chip-remove-text';
                chipRemoveLabel.textContent = '<?= addslashes(__('ticket.participant_remove')) ?>';
                chip.appendChild(chipRemoveLabel);

                removeToggle.appendChild(chip);
                list.appendChild(removeToggle);
            });

            refreshPendingRemoveConstraints(ticketCard);
        };

        var applyParticipantSummaryToCard = function (ticketCard, participantEmails, requesterLabel, requesterTooltip, creatorEmail)
        {
            if (!ticketCard)
            {
                return;
            }

            var requesterElement = ticketCard.querySelector('[data-role="requester-email"]');
            var normalizedCreator = String(creatorEmail || '').trim().toLowerCase();
            var emails = parseParticipantEmails(participantEmails, normalizedCreator);
            var label = String(requesterLabel || '');
            var tooltip = String(requesterTooltip || '');
            if (!label)
            {
                label = emails.length > 0 ? emails[0] + (emails.length > 1 ? ' +' + String(emails.length - 1) : '') : normalizedCreator;
            }
            if (!tooltip)
            {
                tooltip = emails.join('\n');
            }

            if (requesterElement)
            {
                requesterElement.textContent = label;
                requesterElement.title = tooltip || (emails.length === 1 && label !== emails[0] ? emails[0] : (emails.length > 1 ? tooltip : ''));
                requesterElement.setAttribute('data-user-emails', JSON.stringify(emails));
                requesterElement.setAttribute('data-ticket-users-trigger', emails.length > 1 ? '1' : '0');
                requesterElement.classList.toggle('requester-multi', emails.length > 1);
            }

            var usersPopoverList = ticketCard.querySelector('[data-role="ticket-users-popover-list"]');
            if (usersPopoverList)
            {
                usersPopoverList.innerHTML = '';
                emails.forEach(function (email)
                {
                    var listItem = document.createElement('li');
                    listItem.textContent = email;
                    usersPopoverList.appendChild(listItem);
                });
            }

            renderParticipantManagerList(ticketCard, emails, normalizedCreator);
        };

        document.querySelectorAll('[data-settings-row]').forEach(function (row)
        {
            var availabilityCheckbox = row.querySelector('.availability-checkbox');
            var settingsUser = row.getAttribute('data-settings-user') || '';
            var relatedRows = settingsUser !== ''
                ? Array.prototype.filter.call(document.querySelectorAll('[data-settings-row]'), function (candidateRow)
                {
                    return (candidateRow.getAttribute('data-settings-user') || '') === settingsUser;
                })
                : [row];

            if (!availabilityCheckbox)
            {
                return;
            }

            var syncAvailabilityState = function ()
            {
                var isAvailable = availabilityCheckbox.checked;
                relatedRows.forEach(function (relatedRow)
                {
                    var vacationIndicator = relatedRow.querySelector('.vacation-indicator');
                    var vacationBadge = relatedRow.querySelector('.vacation-badge');

                    relatedRow.classList.toggle('is-away', !isAvailable);
                    if (vacationIndicator)
                    {
                        vacationIndicator.hidden = isAvailable;
                    }
                    if (vacationBadge)
                    {
                        vacationBadge.classList.toggle('is-away', !isAvailable);
                    }
                });
            };

            availabilityCheckbox.addEventListener('change', syncAvailabilityState);
            syncAvailabilityState();
        });

        var liveTicketSection = document.querySelector('[data-live-ticket-section]');
        var liveTicketPollTimer = null;
        var liveTicketRefreshInFlight = false;
        var browserNotificationPollTimer = null;
        var browserNotificationInFlight = false;
        var browserNotificationRequestAttempted = false;
        var apiUrl = document.body ? (document.body.getAttribute('data-api-url') || 'api.php') : 'api.php';
        var apiKey = document.body ? (document.body.getAttribute('data-api-key') || '') : '';
        var browserNotificationPollUrl = apiUrl;
        var browserNotificationOpenTemplate = document.body ? (document.body.getAttribute('data-browser-notification-open-template') || '') : '';
        var webPushSubscribeUrl = apiUrl;
        var webPushVapidPublicKey = document.body ? (document.body.getAttribute('data-webpush-vapid-public-key') || '') : '';
        var webPushServiceWorkerUrl = document.body ? (document.body.getAttribute('data-webpush-sw-url') || '') : '';
        var csrfToken = document.body ? (document.body.getAttribute('data-csrf-token') || '') : '';
        var sessionKeepaliveUrl = document.body ? (document.body.getAttribute('data-session-keepalive-url') || 'session_keepalive.php') : 'session_keepalive.php';

            var emailPrefsSection = document.querySelector('[data-email-prefs-section]');
        if (emailPrefsSection)
        {
            var emailPrefsViewerEmail = emailPrefsSection.getAttribute('data-viewer-email') || '';
            var emailPrefsUserIsAdmin = emailPrefsSection.getAttribute('data-user-is-admin') === '1';
            var emailPrefsFeedback = emailPrefsSection.querySelector('[data-email-prefs-feedback]');
            var emailPrefsFeedbackTimer = null;
            var showEmailPrefsFeedback = function (message, isError)
            {
                if (!emailPrefsFeedback)
                {
                    return;
                }

                emailPrefsFeedback.textContent = message;
                emailPrefsFeedback.hidden = false;
                emailPrefsFeedback.classList.toggle('is-error', !!isError);
                if (emailPrefsFeedbackTimer)
                {
                    clearTimeout(emailPrefsFeedbackTimer);
                }

                emailPrefsFeedbackTimer = setTimeout(function ()
                {
                    if (emailPrefsFeedback)
                    {
                        emailPrefsFeedback.hidden = true;
                    }
                }, 2200);
            };

            emailPrefsSection.querySelectorAll('[data-email-pref-type]').forEach(function (checkbox)
            {
                checkbox.addEventListener('change', function ()
                {
                    var notificationType = checkbox.getAttribute('data-email-pref-type') || '';
                    if (!notificationType)
                    {
                        return;
                    }

                    fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-Key': apiKey
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            action: 'save_admin_email_preferences',
                            csrf_token: csrfToken,
                            viewer_email: emailPrefsViewerEmail,
                            user_is_admin: emailPrefsUserIsAdmin,
                            is_admin_portal: true,
                            notification_type: notificationType,
                            enabled: checkbox.checked ? 1 : 0
                        })
                    }).then(function (response)
                    {
                        if (!response.ok)
                        {
                            throw new Error('email-prefs-request-failed');
                        }

                        return response.json();
                    }).then(function (data)
                    {
                        if (!data || !data.success)
                        {
                            checkbox.checked = !checkbox.checked;
                            showEmailPrefsFeedback(EMAIL_PREFS_SAVE_FAILED_LABEL, true);
                            return;
                        }

                        showEmailPrefsFeedback(EMAIL_PREFS_SAVED_LABEL, false);
                    }).catch(function ()
                    {
                        checkbox.checked = !checkbox.checked;
                        showEmailPrefsFeedback(EMAIL_PREFS_SAVE_FAILED_LABEL, true);
                    });
                });
            });
        }

        var changelogSection = document.querySelector('[data-changelog-section]');
        if (changelogSection)
        {
            var changelogUnreadList = changelogSection.querySelector('[data-changelog-unread-list]');
            var changelogReadList = changelogSection.querySelector('[data-changelog-read-list]');
            var changelogReadSection = changelogSection.querySelector('[data-changelog-read-section]');
            var changelogEmpty = changelogSection.querySelector('[data-changelog-empty]');
            var changelogMarkAllButton = changelogSection.querySelector('[data-changelog-mark-all]');
            var changelogToggleReadButton = changelogSection.querySelector('[data-changelog-toggle-read]');
            var changelogFooterActions = changelogSection.querySelector('[data-changelog-footer-actions]');
            var changelogFeedback = changelogSection.querySelector('[data-changelog-feedback]');
            var changelogFeedbackTimer = null;
            var changelogEntryIds = [];

            try
            {
                changelogEntryIds = JSON.parse(changelogSection.getAttribute('data-changelog-ids') || '[]');
            } catch (error)
            {
                changelogEntryIds = [];
            }

            var showChangelogFeedback = function (message, isError)
            {
                if (!changelogFeedback)
                {
                    return;
                }

                changelogFeedback.textContent = message;
                changelogFeedback.hidden = false;
                changelogFeedback.classList.toggle('is-error', !!isError);
                if (changelogFeedbackTimer)
                {
                    clearTimeout(changelogFeedbackTimer);
                }

                changelogFeedbackTimer = setTimeout(function ()
                {
                    if (changelogFeedback)
                    {
                        changelogFeedback.hidden = true;
                    }
                }, 2200);
            };

            var syncChangelogNavPulse = function ()
            {
                var navLink = document.querySelector('[data-changelog-nav-link]');
                if (!navLink)
                {
                    return;
                }

                var hasUnread = false;
                if (changelogUnreadList)
                {
                    hasUnread = changelogUnreadList.querySelectorAll('[data-changelog-entry][data-changelog-read="0"]').length > 0;
                }
                else
                {
                    hasUnread = navLink.classList.contains('has-unread-changelog');
                }

                navLink.classList.toggle('has-unread-changelog', hasUnread);
            };

            var syncChangelogEmptyState = function ()
            {
                if (!changelogUnreadList)
                {
                    return;
                }

                var unreadEntries = changelogUnreadList.querySelectorAll('[data-changelog-entry][data-changelog-read="0"]');
                if (changelogMarkAllButton)
                {
                    changelogMarkAllButton.hidden = unreadEntries.length === 0;
                }

                var hasVisibleEntries = changelogUnreadList.querySelectorAll('[data-changelog-entry]').length > 0;
                if (changelogEmpty)
                {
                    changelogEmpty.hidden = hasVisibleEntries;
                }
            };

            var markChangelogEntryReadInPlace = function (entry)
            {
                if (!entry)
                {
                    return;
                }

                entry.setAttribute('data-changelog-read', '1');
                entry.classList.remove('is-unread');
                entry.classList.add('is-read');

                var badge = entry.querySelector('.changelog-entry-badge');
                if (badge)
                {
                    badge.remove();
                }

                syncChangelogEmptyState();
                syncChangelogNavPulse();
            };

            var changelogViewerEmail = changelogSection.getAttribute('data-viewer-email') || '';
            var changelogUserIsAdmin = changelogSection.getAttribute('data-user-is-admin') === '1';

            var postChangelogAction = function (action, body)
            {
                return fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-Key': apiKey
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(Object.assign({
                        action: action,
                        csrf_token: csrfToken,
                        viewer_email: changelogViewerEmail,
                        user_is_admin: changelogUserIsAdmin,
                        is_admin_portal: true
                    }, body || {}))
                }).then(function (response)
                {
                    if (!response.ok)
                    {
                        throw new Error('changelog-request-failed');
                    }

                    return response.json();
                });
            };

            changelogSection.querySelectorAll('[data-changelog-entry]').forEach(function (entry)
            {
                entry.addEventListener('toggle', function ()
                {
                    if (!entry.open || entry.getAttribute('data-changelog-read') === '1')
                    {
                        return;
                    }

                    var entryId = entry.getAttribute('data-changelog-id') || '';
                    if (!entryId)
                    {
                        return;
                    }

                    postChangelogAction('mark_changelog_read', { entry_id: entryId }).then(function (data)
                    {
                        if (!data || !data.success)
                        {
                            showChangelogFeedback(CHANGELOG_SAVE_FAILED_LABEL, true);
                            return;
                        }

                        markChangelogEntryReadInPlace(entry);
                        showChangelogFeedback(CHANGELOG_SAVED_LABEL, false);
                    }).catch(function ()
                    {
                        showChangelogFeedback(CHANGELOG_SAVE_FAILED_LABEL, true);
                    });
                });
            });

            if (changelogMarkAllButton)
            {
                changelogMarkAllButton.addEventListener('click', function ()
                {
                    var unreadEntries = changelogUnreadList
                        ? Array.prototype.slice.call(changelogUnreadList.querySelectorAll('[data-changelog-entry][data-changelog-read="0"]'))
                        : [];
                    if (!unreadEntries.length)
                    {
                        return;
                    }

                    postChangelogAction('mark_all_changelogs_read', { entry_ids: changelogEntryIds }).then(function (data)
                    {
                        if (!data || !data.success)
                        {
                            showChangelogFeedback(CHANGELOG_SAVE_FAILED_LABEL, true);
                            return;
                        }

                        unreadEntries.forEach(function (entry)
                        {
                            markChangelogEntryReadInPlace(entry);
                        });
                        showChangelogFeedback(CHANGELOG_SAVED_LABEL, false);
                    }).catch(function ()
                    {
                        showChangelogFeedback(CHANGELOG_SAVE_FAILED_LABEL, true);
                    });
                });
            }

            if (changelogToggleReadButton && changelogReadSection)
            {
                changelogToggleReadButton.addEventListener('click', function ()
                {
                    var isHidden = changelogReadSection.hidden;
                    changelogReadSection.hidden = !isHidden;
                    changelogToggleReadButton.textContent = isHidden
                        ? (changelogToggleReadButton.getAttribute('data-label-hide') || '')
                        : (changelogToggleReadButton.getAttribute('data-label-show') || '');
                });
            }

            syncChangelogEmptyState();
            syncChangelogNavPulse();
        }

        var sessionKeepaliveTimer = null;
        var sessionKeepaliveInFlight = false;
        var lastSessionKeepaliveOkAt = Date.now();
        var webPushSyncInFlight = false;
        var sessionExpiredHandled = false;
        var sessionExpiredCountdownTimer = null;
        var ticketPollPayload = {};
        if (liveTicketSection)
        {
            try
            {
                ticketPollPayload = JSON.parse(liveTicketSection.getAttribute('data-ticket-poll-payload') || '{}');
            } catch (error)
            {
                ticketPollPayload = {};
            }
        }

        var ticketShareModal = document.querySelector('[data-role="ticket-share-modal"]');
        var ticketShareUrlInput = ticketShareModal ? ticketShareModal.querySelector('[data-role="ticket-share-url-input"]') : null;

        var closeTicketShareModal = function ()
        {
            if (!ticketShareModal)
            {
                return;
            }

            ticketShareModal.hidden = true;
            ticketShareModal.classList.remove('is-open');
            document.documentElement.style.overflow = '';
        };

        var openTicketShareModal = function (shareUrl)
        {
            if (!ticketShareModal || !ticketShareUrlInput)
            {
                return;
            }

            ticketShareUrlInput.value = shareUrl;
            ticketShareModal.hidden = false;
            ticketShareModal.classList.add('is-open');
            document.documentElement.style.overflow = 'hidden';
            window.setTimeout(function ()
            {
                ticketShareUrlInput.focus();
                ticketShareUrlInput.select();
            }, 0);
        };

        var copyTextToClipboard = function (text)
        {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function')
            {
                return navigator.clipboard.writeText(text);
            }

            return new Promise(function (resolve, reject)
            {
                var helperInput = document.createElement('textarea');
                helperInput.value = text;
                helperInput.setAttribute('readonly', 'readonly');
                helperInput.style.position = 'fixed';
                helperInput.style.left = '-9999px';
                document.body.appendChild(helperInput);
                helperInput.select();

                try
                {
                    var copied = document.execCommand('copy');
                    document.body.removeChild(helperInput);
                    if (copied)
                    {
                        resolve();
                        return;
                    }
                    reject(new Error('copy_failed'));
                }
                catch (error)
                {
                    document.body.removeChild(helperInput);
                    reject(error);
                }
            });
        };

        var ticketSearchInput = liveTicketSection ? liveTicketSection.querySelector('input[name="search"]') : null;
        var ticketSearchSubmitTimer = null;
        var ticketSearchRefreshInFlight = false;

        var applyLiveTicketSearch = function ()
        {
            if (!ticketSearchInput || !liveTicketSection)
            {
                return;
            }

            var searchValue = ticketSearchInput.value || '';
            ticketPollPayload.search_query = searchValue;
            ticketPollPayload.last_signature = '';

            try
            {
                var searchUrl = new URL(window.location.href);
                if (searchValue.trim() !== '')
                {
                    searchUrl.searchParams.set('search', searchValue);
                }
                else
                {
                    searchUrl.searchParams.delete('search');
                }
                history.replaceState(null, '', searchUrl.toString());
            }
            catch (error)
            {
                // URL update is optional; search refresh should still work.
            }

            if (ticketSearchRefreshInFlight)
            {
                return;
            }

            ticketSearchRefreshInFlight = true;
            apiFetchJson('ticket_poll', ticketPollPayload)
                .then(function (data)
                {
                    if (data)
                    {
                        applyIncrementalTicketUpdate(data);
                    }
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }
                })
                .finally(function ()
                {
                    ticketSearchRefreshInFlight = false;
                });
        };

        if (ticketSearchInput && ticketSearchInput.form)
        {
            ticketSearchInput.form.addEventListener('submit', function (event)
            {
                event.preventDefault();
                if (ticketSearchSubmitTimer)
                {
                    clearTimeout(ticketSearchSubmitTimer);
                    ticketSearchSubmitTimer = null;
                }
                applyLiveTicketSearch();
            });

            ticketSearchInput.addEventListener('input', function ()
            {
                if (ticketSearchSubmitTimer)
                {
                    clearTimeout(ticketSearchSubmitTimer);
                }

                ticketSearchSubmitTimer = setTimeout(function ()
                {
                    applyLiveTicketSearch();
                }, 300);
            });
        }

        var imagePreviewModal = document.createElement('div');
        imagePreviewModal.className = 'image-preview-modal';

        imagePreviewModal.setAttribute('aria-hidden', 'true');
        imagePreviewModal.innerHTML = '' +
            '<div class="image-preview-content" role="dialog" aria-modal="true">' +
            '<button type="button" class="image-preview-close" data-image-preview-close aria-label="<?= addslashes(__('ticket.preview_close')) ?>">&times;</button>' +
            '<img class="image-preview-full" src="" alt="">' +
            '</div>';

        if (document.body)
        {
            document.body.appendChild(imagePreviewModal);
        }

        var imagePreviewFull = imagePreviewModal.querySelector('.image-preview-full');
        var imagePreviewCloseButton = imagePreviewModal.querySelector('[data-image-preview-close]');

        var sessionExpiredModal = document.createElement('div');
        sessionExpiredModal.className = 'session-expired-modal';
        sessionExpiredModal.setAttribute('aria-hidden', 'true');
        sessionExpiredModal.innerHTML = '' +
            '<div class="session-expired-card" role="alertdialog" aria-modal="true">' +
            '<h2 class="session-expired-title"><?= addslashes(__('session.expired_popup')) ?></h2>' +
            '<p class="session-expired-countdown" data-session-expired-countdown></p>' +
            '</div>';

        if (document.body)
        {
            document.body.appendChild(sessionExpiredModal);
        }

        var sessionExpiredCountdown = sessionExpiredModal.querySelector('[data-session-expired-countdown]');

        var closeImagePreview = function ()
        {
            if (!imagePreviewModal.classList.contains('is-open'))
            {
                return;
            }

            imagePreviewModal.classList.remove('is-open');
            imagePreviewModal.setAttribute('aria-hidden', 'true');
            document.documentElement.style.overflow = '';

            window.setTimeout(function ()
            {
                if (!imagePreviewModal.classList.contains('is-open') && imagePreviewFull)
                {
                    imagePreviewFull.src = '';
                    imagePreviewFull.alt = '';
                }
            }, 250);
        };

        var openImagePreview = function (previewSrc, previewAlt)
        {
            if (!imagePreviewFull || !previewSrc)
            {
                return;
            }

            imagePreviewFull.src = previewSrc;
            imagePreviewFull.alt = previewAlt || '';
            imagePreviewModal.classList.add('is-open');
            imagePreviewModal.setAttribute('aria-hidden', 'false');
            document.documentElement.style.overflow = 'hidden';
        };

        document.addEventListener('click', function (event)
        {
            var shareLinkButton = event.target.closest('[data-role="ticket-share-link"]');
            if (shareLinkButton)
            {
                event.preventDefault();
                event.stopPropagation();

                var shareUrl = shareLinkButton.getAttribute('data-share-url') || '';
                if (shareUrl === '')
                {
                    return;
                }

                copyTextToClipboard(shareUrl).catch(function () { /* modal still shows fallback field */ });
                openTicketShareModal(shareUrl);
                return;
            }

            var closeShareButton = event.target.closest('[data-role="ticket-share-close"]');
            if (closeShareButton)
            {
                event.preventDefault();
                closeTicketShareModal();
                return;
            }

            if (ticketShareModal && event.target === ticketShareModal)
            {
                closeTicketShareModal();
                return;
            }

            var openParticipantsButton = event.target.closest('[data-role="manage-participants-open"]');
            if (openParticipantsButton)
            {
                event.preventDefault();
                event.stopPropagation();
                var openCard = openParticipantsButton.closest('details.ticket-card');
                var openModal = openCard ? openCard.querySelector('[data-role="ticket-participants-modal"]') : null;
                if (openModal)
                {
                    openModal.hidden = false;
                    openModal.classList.add('is-open');
                    document.documentElement.style.overflow = 'hidden';
                    initializeEmailChipInputs(openModal);
                }
                return;
            }

            var closeParticipantsButton = event.target.closest('[data-role="manage-participants-close"]');
            if (closeParticipantsButton)
            {
                event.preventDefault();
                var closeModal = closeParticipantsButton.closest('[data-role="ticket-participants-modal"]');
                if (closeModal)
                {
                    closeModal.hidden = true;
                    closeModal.classList.remove('is-open');
                    document.documentElement.style.overflow = '';
                }
                return;
            }

            var openCategoryButton = event.target.closest('[data-role="change-category-open"]');
            if (openCategoryButton)
            {
                event.preventDefault();
                event.stopPropagation();
                var categoryCard = openCategoryButton.closest('details.ticket-card');
                var categoryModal = categoryCard ? categoryCard.querySelector('[data-role="ticket-category-modal"]') : null;
                if (categoryModal)
                {
                    var categorySelect = categoryModal.querySelector('[data-role="change-category-select"]');
                    var reassignCheckbox = categoryModal.querySelector('[data-role="change-category-reassign"]');
                    var currentCategory = openCategoryButton.getAttribute('data-current-category') || '';
                    if (categorySelect && currentCategory !== '')
                    {
                        categorySelect.value = currentCategory;
                    }
                    if (reassignCheckbox)
                    {
                        reassignCheckbox.checked = false;
                    }
                    setCategoryFeedback(categoryCard, '', false);
                    categoryModal.hidden = false;
                    categoryModal.classList.add('is-open');
                    document.documentElement.style.overflow = 'hidden';
                }
                return;
            }

            var openTitleButton = event.target.closest('[data-role="change-title-open"]');
            if (openTitleButton)
            {
                event.preventDefault();
                event.stopPropagation();
                var titleCard = openTitleButton.closest('details.ticket-card');
                var titleModal = titleCard ? titleCard.querySelector('[data-role="ticket-title-modal"]') : null;
                if (titleModal)
                {
                    var titleInput = titleModal.querySelector('[data-role="change-title-input"]');
                    var currentTitle = openTitleButton.getAttribute('data-current-title') || '';
                    if (titleInput)
                    {
                        titleInput.value = currentTitle;
                    }
                    setTitleFeedback(titleCard, '', false);
                    titleModal.hidden = false;
                    titleModal.classList.add('is-open');
                    document.documentElement.style.overflow = 'hidden';
                    if (titleInput)
                    {
                        titleInput.focus();
                        titleInput.select();
                    }
                }
                return;
            }

            var closeTitleButton = event.target.closest('[data-role="change-title-close"], [data-role="change-title-cancel"]');
            if (closeTitleButton)
            {
                event.preventDefault();
                closeTitleModal(closeTitleButton.closest('details.ticket-card'));
                return;
            }

            if (event.target.matches('[data-role="ticket-title-modal"]'))
            {
                event.preventDefault();
                closeTitleModal(event.target.closest('details.ticket-card'));
                return;
            }

            var saveTitleButton = event.target.closest('[data-role="change-title-save"]');
            if (saveTitleButton)
            {
                event.preventDefault();
                var saveTitleCard = saveTitleButton.closest('details.ticket-card');
                var saveTitleModal = saveTitleCard ? saveTitleCard.querySelector('[data-role="ticket-title-modal"]') : null;
                if (!saveTitleCard || !saveTitleModal)
                {
                    return;
                }

                var titleInputField = saveTitleModal.querySelector('[data-role="change-title-input"]');
                saveTitleButton.disabled = true;
                syncTitleChangeViaApi(saveTitleCard, {
                    title: titleInputField ? String(titleInputField.value || '').trim() : ''
                }).finally(function ()
                {
                    saveTitleButton.disabled = false;
                });
                return;
            }

            var closeCategoryButton = event.target.closest('[data-role="change-category-close"], [data-role="change-category-cancel"]');
            if (closeCategoryButton)
            {
                event.preventDefault();
                closeCategoryModal(closeCategoryButton.closest('details.ticket-card'));
                return;
            }

            if (event.target.matches('[data-role="ticket-category-modal"]'))
            {
                event.preventDefault();
                closeCategoryModal(event.target.closest('details.ticket-card'));
                return;
            }

            var saveCategoryButton = event.target.closest('[data-role="change-category-save"]');
            if (saveCategoryButton)
            {
                event.preventDefault();
                var saveCard = saveCategoryButton.closest('details.ticket-card');
                var saveModal = saveCard ? saveCard.querySelector('[data-role="ticket-category-modal"]') : null;
                if (!saveCard || !saveModal)
                {
                    return;
                }

                var selectedCategory = saveModal.querySelector('[data-role="change-category-select"]');
                var reassignInput = saveModal.querySelector('[data-role="change-category-reassign"]');
                saveCategoryButton.disabled = true;
                syncCategoryChangeViaApi(saveCard, {
                    category: selectedCategory ? selectedCategory.value : '',
                    reassign: !!(reassignInput && reassignInput.checked)
                }).finally(function ()
                {
                    saveCategoryButton.disabled = false;
                });
                return;
            }

            if (event.target.matches('[data-role="ticket-participants-modal"]'))
            {
                event.preventDefault();
                event.target.hidden = true;
                event.target.classList.remove('is-open');
                document.documentElement.style.overflow = '';
                return;
            }

            var removeToggle = event.target.closest('[data-role="participant-remove-toggle"]');
            if (removeToggle)
            {
                event.preventDefault();
                event.stopPropagation();

                if (removeToggle.classList.contains('is-lock-protected'))
                {
                    return;
                }

                if (removeToggle.classList.contains('is-pending-remove'))
                {
                    removeToggle.classList.remove('is-pending-remove');
                    refreshPendingRemoveConstraints(removeToggle.closest('details.ticket-card'));
                    return;
                }

                removeToggle.classList.add('is-pending-remove');
                refreshPendingRemoveConstraints(removeToggle.closest('details.ticket-card'));
                return;
            }

            var usersTrigger = event.target.closest('[data-ticket-users-trigger="1"]');
            if (usersTrigger)
            {
                var ticketCard = usersTrigger.closest('details.ticket-card');
                if (!ticketCard || !ticketCard.open)
                {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                var popover = ticketCard.querySelector('[data-role="ticket-users-popover"]');
                if (!popover)
                {
                    return;
                }

                popover.hidden = !popover.hidden;
                return;
            }

            if (!event.target.closest('[data-role="ticket-users-popover"]'))
            {
                document.querySelectorAll('[data-role="ticket-users-popover"]').forEach(function (popover)
                {
                    popover.hidden = true;
                });
            }

            var trigger = event.target.closest('[data-image-preview-trigger]');
            if (trigger)
            {
                event.preventDefault();
                openImagePreview(trigger.getAttribute('data-preview-src') || '', trigger.getAttribute('data-preview-alt') || '');
                return;
            }

            if (!imagePreviewModal.classList.contains('is-open'))
            {
                return;
            }

            if ((imagePreviewCloseButton && event.target === imagePreviewCloseButton) || event.target === imagePreviewModal)
            {
                event.preventDefault();
                closeImagePreview();
            }
        });

        document.addEventListener('keydown', function (event)
        {
            if (event.key === 'Escape')
            {
                document.querySelectorAll('[data-role="ticket-participants-modal"].is-open, [data-role="ticket-category-modal"].is-open, [data-role="ticket-share-modal"].is-open').forEach(function (modal)
                {
                    modal.hidden = true;
                    modal.classList.remove('is-open');
                });
                document.documentElement.style.overflow = '';
                closeImagePreview();
            }
        });

        var base64UrlToUint8Array = function (base64String)
        {
            var normalized = String(base64String || '').replace(/-/g, '+').replace(/_/g, '/');
            var padding = normalized.length % 4;
            if (padding)
            {
                normalized += '='.repeat(4 - padding);
            }

            var rawData = window.atob(normalized);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i)
            {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        };

        var updateSessionExpiredCountdown = function (secondsRemaining)
        {
            if (!sessionExpiredCountdown)
            {
                return;
            }

            sessionExpiredCountdown.textContent = '<?= addslashes(__('session.auto_refresh_in', 0)) ?>'.replace('0', String(Math.max(0, secondsRemaining)));
        };

        var clearTicketHtml = function ()
        {
            if (!liveTicketSection)
            {
                return;
            }

            liveTicketSection.querySelectorAll('.ticket-list, .empty-state').forEach(function (node)
            {
                node.remove();
            });
            liveTicketSection.setAttribute('data-ticket-signature', '');
        };

        var handleSessionExpired = function ()
        {
            if (sessionExpiredHandled)
            {
                return;
            }

            sessionExpiredHandled = true;
            closeImagePreview();
            clearTicketHtml();

            if (liveTicketPollTimer !== null)
            {
                window.clearInterval(liveTicketPollTimer);
                liveTicketPollTimer = null;
            }

            if (browserNotificationPollTimer !== null)
            {
                window.clearInterval(browserNotificationPollTimer);
                browserNotificationPollTimer = null;
            }

            if (sessionKeepaliveTimer !== null)
            {
                window.clearInterval(sessionKeepaliveTimer);
                sessionKeepaliveTimer = null;
            }

            if (sessionExpiredModal)
            {
                sessionExpiredModal.classList.add('is-open');
                sessionExpiredModal.setAttribute('aria-hidden', 'false');
            }

            document.documentElement.style.overflow = 'hidden';

            var secondsRemaining = 60;
            updateSessionExpiredCountdown(secondsRemaining);
            sessionExpiredCountdownTimer = window.setInterval(function ()
            {
                secondsRemaining -= 1;
                updateSessionExpiredCountdown(secondsRemaining);
                if (secondsRemaining <= 0)
                {
                    window.clearInterval(sessionExpiredCountdownTimer);
                    window.location.replace(window.location.href);
                }
            }, 1000);
        };

        window.asclepiusHandleSessionExpired = handleSessionExpired;

        var refreshSessionKeepalive = function (forceCheck)
        {
            if (!sessionKeepaliveUrl || sessionExpiredHandled)
            {
                return Promise.resolve(false);
            }

            if (sessionKeepaliveInFlight && !forceCheck)
            {
                return Promise.resolve(true);
            }

            sessionKeepaliveInFlight = true;
            return fetch(sessionKeepaliveUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'fetch'
                }
            }).then(function (response)
            {
                if (response.status === 401)
                {
                    handleSessionExpired();
                    return false;
                }

                if (!response.ok)
                {
                    return false;
                }

                return response.json();
            }).then(function (data)
            {
                if (data === false)
                {
                    return false;
                }

                if (!data || !data.ok)
                {
                    return false;
                }

                lastSessionKeepaliveOkAt = Date.now();

                if (data.api_key && data.api_key !== apiKey)
                {
                    apiKey = data.api_key;
                    if (document.body)
                    {
                        document.body.setAttribute('data-api-key', apiKey);
                    }
                }

                return true;
            }).catch(function ()
            {
                return false;
            }).finally(function ()
            {
                sessionKeepaliveInFlight = false;
            });
        };

        document.addEventListener('submit', function (event)
        {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.querySelector('input[name="csrf_token"]'))
            {
                return;
            }

            if (form.dataset.sessionSubmitVerified === '1')
            {
                delete form.dataset.sessionSubmitVerified;
                return;
            }

            if (sessionExpiredHandled)
            {
                event.preventDefault();
                return;
            }

            event.preventDefault();
            var submitter = event.submitter || null;

            refreshSessionKeepalive(true).then(function (sessionOk)
            {
                if (!sessionOk)
                {
                    if (!sessionExpiredHandled)
                    {
                        handleSessionExpired();
                    }
                    return;
                }

                form.dataset.sessionSubmitVerified = '1';
                if (typeof form.requestSubmit === 'function')
                {
                    form.requestSubmit(submitter);
                } else
                {
                    form.submit();
                }
            });
        });

        document.addEventListener('focusin', function (event)
        {
            if (sessionExpiredHandled || !sessionKeepaliveUrl)
            {
                return;
            }

            var target = event.target;
            if (!(target instanceof HTMLElement))
            {
                return;
            }

            if (!/^(INPUT|TEXTAREA|SELECT)$/i.test(target.tagName))
            {
                return;
            }

            var form = target.closest('form');
            if (!form || !form.querySelector('input[name="csrf_token"]'))
            {
                return;
            }

            if ((Date.now() - lastSessionKeepaliveOkAt) < 60000)
            {
                return;
            }

            refreshSessionKeepalive(true).then(function (sessionOk)
            {
                if (!sessionOk && !sessionExpiredHandled)
                {
                    handleSessionExpired();
                }
            });
        });

        var apiFetchJson = function (action, payload)
        {
            var requestPayload = Object.assign({}, payload || {});
            requestPayload.action = action;

            var headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'fetch'
            };
            if (apiKey)
            {
                headers['X-API-Key'] = apiKey;
            }

            return fetch(apiUrl, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify(requestPayload)
            }).then(function (response)
            {
                if (response.status === 401)
                {
                    return response.json().catch(function ()
                    {
                        return {};
                    }).then(function (data)
                    {
                        if (data && data.reason === 'session_expired_refresh_required')
                        {
                            handleSessionExpired();
                            throw new Error('refresh-required');
                        }

                        throw new Error('unauthorized');
                    });
                }

                if (!response.ok)
                {
                    throw new Error('api-request-failed');
                }

                return response.json();
            });
        };

        /**
         * Template-fragment modal
         */
        var templateModal = document.getElementById('template_fragment_modal');
        var templateModalTitle = document.getElementById('template_fragment_modal_title');
        var templateModalName = document.getElementById('template_fragment_modal_name');
        var templateModalBody = document.getElementById('template_fragment_modal_body');
        var templateModalSave = document.getElementById('template_fragment_modal_save');
        var templateModalDelete = document.getElementById('template_fragment_modal_delete');
        var templateModalClose = document.getElementById('template_fragment_modal_close');
        var templateModalError = document.getElementById('template_fragment_modal_error');
        var templateModalEditId = 0;

        var setTemplateModalError = function (msg)
        {
            if (!templateModalError) { return; }
            templateModalError.textContent = msg || '';
            templateModalError.hidden = !msg;
        };

        var openTemplateModal = function (mode, id, name, body)
        {
            if (!templateModal) { return; }
            templateModalEditId = (mode === 'edit') ? (id || 0) : 0;
            if (templateModalTitle)
            {
                templateModalTitle.textContent = (mode === 'edit')
                    ? (templateModalSave ? (templateModalSave.getAttribute('data-label-save') || '') : '')
                    : (templateModalSave ? (templateModalSave.getAttribute('data-label-create') || '') : '');
            }
            if (templateModalSave)
            {
                templateModalSave.textContent = (mode === 'edit')
                    ? (templateModalSave.getAttribute('data-label-save') || '')
                    : (templateModalSave.getAttribute('data-label-create') || '');
            }
            if (templateModalName) { templateModalName.value = name || ''; }
            if (templateModalBody) { templateModalBody.value = body || ''; }
            if (templateModalDelete) { templateModalDelete.hidden = (mode !== 'edit'); }
            setTemplateModalError('');
            templateModal.hidden = false;
            if (templateModalBody)
            {
                templateModalBody.dispatchEvent(new Event('input', { bubbles: true }));
            }
            if (templateModalName) { templateModalName.focus(); }
        };

        var closeTemplateModal = function ()
        {
            if (!templateModal) { return; }
            templateModal.hidden = true;
            templateModalEditId = 0;
            setTemplateModalError('');
        };

        var rebuildTemplateList = function (templates)
        {
            var list = document.getElementById('template_fragment_list');
            var empty = document.getElementById('template_fragment_empty');
            if (!list) { return; }

            list.innerHTML = '';
            if (!templates || templates.length === 0)
            {
                if (empty) { empty.hidden = false; }
                return;
            }

            if (empty) { empty.hidden = true; }
            templates.forEach(function (tpl)
            {
                var label = document.createElement('label');
                label.className = 'template-fragment-item';

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'template-fragment-checkbox';
                cb.value = String(tpl.id || 0);
                var bodyJson = JSON.stringify(tpl.body || '');
                cb.setAttribute('data-template-body', bodyJson);
                cb.setAttribute('data-template-name', tpl.name || '');

                var nameSpan = document.createElement('span');
                nameSpan.className = 'template-fragment-name';
                nameSpan.textContent = tpl.name || '';

                var editBtn = document.createElement('button');
                editBtn.type = 'button';
                editBtn.className = 'secondary-button template-fragment-edit-btn';
                editBtn.setAttribute('data-template-id', String(tpl.id || 0));
                editBtn.setAttribute('data-template-name', tpl.name || '');
                editBtn.setAttribute('data-template-body', tpl.body || '');
                editBtn.textContent = '<?= addslashes(__('template_ticket.edit_template_button')) ?>';

                var handle = document.createElement('span');
                handle.className = 'template-drag-handle';
                handle.setAttribute('aria-hidden', 'true');
                handle.innerHTML = '&#8597;';

                label.appendChild(handle);
                label.appendChild(cb);
                label.appendChild(nameSpan);
                label.appendChild(editBtn);
                list.appendChild(label);
            });

            initializeTemplateCheckboxSync(list);
            initializeTemplateDragDrop(list);
        };

        var templateDragSrc = null;

        var initializeTemplateDragDrop = function (list)
        {
            if (!list) { return; }
            list.querySelectorAll('.template-fragment-item').forEach(attachTemplateDrag);
        };

        var attachTemplateDrag = function (item)
        {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', function (e)
            {
                templateDragSrc = item;
                item.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', function ()
            {
                item.classList.remove('is-dragging');
                var list = document.getElementById('template_fragment_list');
                if (list)
                {
                    list.querySelectorAll('.template-fragment-item').forEach(function (el)
                    {
                        el.classList.remove('drag-over');
                    });
                }
                templateDragSrc = null;
                persistTemplateOrder();
            });

            item.addEventListener('dragover', function (e)
            {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!templateDragSrc || templateDragSrc === item) { return; }
                var list = item.parentNode;
                if (!list) { return; }
                list.querySelectorAll('.template-fragment-item').forEach(function (el)
                {
                    el.classList.remove('drag-over');
                });
                item.classList.add('drag-over');
                var rect = item.getBoundingClientRect();
                var midY = rect.top + rect.height / 2;
                if (e.clientY < midY)
                {
                    list.insertBefore(templateDragSrc, item);
                }
                else
                {
                    list.insertBefore(templateDragSrc, item.nextSibling);
                }
            });

            item.addEventListener('dragleave', function ()
            {
                item.classList.remove('drag-over');
            });

            item.addEventListener('drop', function (e)
            {
                e.preventDefault();
                item.classList.remove('drag-over');
            });
        };

        initializeTemplateDragDrop(document.getElementById('template_fragment_list'));

        var persistTemplateOrder = function ()
        {
            var list = document.getElementById('template_fragment_list');
            if (!list) { return; }
            var orderedIds = [];
            list.querySelectorAll('.template-fragment-checkbox').forEach(function (cb)
            {
                var id = parseInt(cb.value || '0', 10);
                if (id > 0) { orderedIds.push(id); }
            });
            if (orderedIds.length === 0) { return; }
            var csrfToken = templateModalSave ? (templateModalSave.getAttribute('data-csrf') || '') : '';
            apiFetchJson('manage_ticket_template', { operation: 'reorder', ordered_ids: orderedIds, csrf_token: csrfToken });
        };

        var templateApiCall = function (operation, extraPayload)
        {
            if (templateModalSave) { templateModalSave.disabled = true; }
            setTemplateModalError('');

            var csrfToken = templateModalSave ? (templateModalSave.getAttribute('data-csrf') || '') : '';
            return apiFetchJson('manage_ticket_template', Object.assign({
                operation: operation,
                csrf_token: csrfToken
            }, extraPayload || {})).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    setTemplateModalError(data && data.error ? data.error : '<?= addslashes(__('flash.db_error_prefix')) ?>');
                    return;
                }

                closeTemplateModal();
                rebuildTemplateList(data.templates || []);
            }).catch(function ()
            {
                setTemplateModalError('<?= addslashes(__('flash.db_error_prefix')) ?>');
            }).finally(function ()
            {
                if (templateModalSave) { templateModalSave.disabled = false; }
            });
        };

        if (templateModalClose)
        {
            templateModalClose.addEventListener('click', closeTemplateModal);
        }

        if (templateModal)
        {
            templateModal.addEventListener('click', function (e)
            {
                if (e.target === templateModal) { closeTemplateModal(); }
            });
            document.addEventListener('keydown', function (e)
            {
                if (e.key === 'Escape' && !templateModal.hidden) { closeTemplateModal(); }
            });
        }

        var newBtn = document.getElementById('template_fragment_new_btn');
        if (newBtn)
        {
            newBtn.addEventListener('click', function ()
            {
                openTemplateModal('create', 0, '', '');
            });
        }

        document.addEventListener('click', function (e)
        {
            var editBtn = e.target && e.target.closest ? e.target.closest('.template-fragment-edit-btn') : null;
            if (!editBtn) { return; }
            var id = parseInt(editBtn.getAttribute('data-template-id') || '0', 10);
            var name = editBtn.getAttribute('data-template-name') || '';
            var body = editBtn.getAttribute('data-template-body') || '';
            openTemplateModal('edit', id, name, body);
        });

        if (templateModalSave)
        {
            templateModalSave.addEventListener('click', function ()
            {
                var name = templateModalName ? templateModalName.value.trim() : '';
                var body = templateModalBody ? templateModalBody.value.trim() : '';
                if (!name) { setTemplateModalError('<?= addslashes(__('flash.template_name_required')) ?>'); return; }
                if (!body) { setTemplateModalError('<?= addslashes(__('flash.template_body_required')) ?>'); return; }

                if (templateModalEditId > 0)
                {
                    templateApiCall('update', { id: templateModalEditId, name: name, body: body });
                }
                else
                {
                    templateApiCall('create', { name: name, body: body });
                }
            });
        }

        if (templateModalDelete)
        {
            templateModalDelete.addEventListener('click', function ()
            {
                var confirmMsg = templateModalDelete.getAttribute('data-confirm') || '';
                if (confirmMsg && !confirm(confirmMsg)) { return; }
                templateApiCall('delete', { id: templateModalEditId });
            });
        }

        var setParticipantFeedback = function (ticketCard, message, isError)
        {
            if (!ticketCard)
            {
                return;
            }

            var feedbackNode = ticketCard.querySelector('[data-role="manage-participants-feedback"]');
            if (!feedbackNode)
            {
                return;
            }

            feedbackNode.textContent = String(message || '');
            feedbackNode.classList.toggle('is-error', !!isError);
            feedbackNode.classList.toggle('is-success', !isError && String(message || '') !== '');
        };

        var setCategoryFeedback = function (ticketCard, message, isError)
        {
            if (!ticketCard)
            {
                return;
            }

            var feedbackNode = ticketCard.querySelector('[data-role="change-category-feedback"]');
            if (!feedbackNode)
            {
                return;
            }

            feedbackNode.textContent = String(message || '');
            feedbackNode.classList.toggle('is-error', !!isError);
            feedbackNode.classList.toggle('is-success', !isError && String(message || '') !== '');
        };

        var setTitleFeedback = function (ticketCard, message, isError)
        {
            if (!ticketCard)
            {
                return;
            }

            var feedbackNode = ticketCard.querySelector('[data-role="change-title-feedback"]');
            if (!feedbackNode)
            {
                return;
            }

            feedbackNode.textContent = String(message || '');
            feedbackNode.classList.toggle('is-error', !!isError);
            feedbackNode.classList.toggle('is-success', !isError && String(message || '') !== '');
        };

        var closeCategoryModal = function (ticketCard)
        {
            if (!ticketCard)
            {
                return;
            }

            var modal = ticketCard.querySelector('[data-role="ticket-category-modal"]');
            if (!modal)
            {
                return;
            }

            modal.hidden = true;
            modal.classList.remove('is-open');
            document.documentElement.style.overflow = '';
        };

        var closeTitleModal = function (ticketCard)
        {
            if (!ticketCard)
            {
                return;
            }

            var modal = ticketCard.querySelector('[data-role="ticket-title-modal"]');
            if (!modal)
            {
                return;
            }

            modal.hidden = true;
            modal.classList.remove('is-open');
            document.documentElement.style.overflow = '';
        };

        var appendCategoryChangeMessage = function (ticketCard, messageHtml, messageId)
        {
            if (!ticketCard || !messageHtml)
            {
                return;
            }

            var messagesWrap = ticketCard.querySelector('[data-role="messages-wrap"]');
            var thread = ticketCard.querySelector('[data-role="thread"]');
            if (!messagesWrap || !thread)
            {
                return;
            }

            if (messageId)
            {
                var existingMessage = thread.querySelector('[data-message-id="' + String(messageId) + '"]');
                if (existingMessage)
                {
                    messagesWrap.hidden = false;
                    return;
                }
            }

            var loadingHint = thread.querySelector('[data-role="thread-loading-hint"]');
            if (loadingHint)
            {
                loadingHint.hidden = true;
            }

            var template = document.createElement('template');
            template.innerHTML = messageHtml.trim();
            var messageNode = template.content.firstElementChild;
            if (messageNode)
            {
                thread.appendChild(messageNode);
            }

            messagesWrap.hidden = false;
            ticketCard.dataset.threadLoaded = '1';
        };

        var applyCategoryChangeToCard = function (ticketCard, data)
        {
            if (!ticketCard || !data)
            {
                return;
            }

            setText(ticketCard.querySelector('[data-role="ticket-category"]'), data.category_label || '');
            setText(ticketCard.querySelector('[data-role="meta-category-value"]'), data.category_label || '');

            var openCategoryButton = ticketCard.querySelector('[data-role="change-category-open"]');
            if (openCategoryButton && data.category)
            {
                openCategoryButton.setAttribute('data-current-category', data.category);
            }

            var categorySelect = ticketCard.querySelector('[data-role="change-category-select"]');
            if (categorySelect && data.category)
            {
                categorySelect.value = data.category;
            }

            setValue(ticketCard.querySelector('[data-role="assigned-select"]'), data.assigned_email || '');

            var assigneeBadge = ticketCard.querySelector('[data-role="assignee-badge"]');
            if (assigneeBadge)
            {
                setText(assigneeBadge, data.assigned_label || '');
                assigneeBadge.style.setProperty('--assignee-color', data.assigned_color || '');
            }

            appendCategoryChangeMessage(ticketCard, data.message_html || '', data.message_id || 0);
        };

        var applyTitleChangeToCard = function (ticketCard, data)
        {
            if (!ticketCard || !data || !data.title)
            {
                return;
            }

            var encodedTitle = JSON.stringify(data.title);
            var titleNode = ticketCard.querySelector('[data-role="ticket-title"]');
            if (titleNode)
            {
                titleNode.textContent = data.title;
                titleNode.setAttribute('data-original-text', encodedTitle);
                titleNode.setAttribute('data-translated-text', encodedTitle);
                titleNode.setAttribute('data-showing', 'translated');
            }

            var titleToggle = ticketCard.querySelector('[data-role="title-translation-toggle"]');
            if (titleToggle)
            {
                titleToggle.remove();
            }

            setText(ticketCard.querySelector('[data-role="meta-title-value"]'), data.title);

            var openTitleButton = ticketCard.querySelector('[data-role="change-title-open"]');
            if (openTitleButton)
            {
                openTitleButton.setAttribute('data-current-title', data.title);
            }

            var titleInput = ticketCard.querySelector('[data-role="change-title-input"]');
            if (titleInput)
            {
                titleInput.value = data.title;
            }

            ticketCard.dataset.needsTranslation = '0';
        };

        var syncCategoryChangeViaApi = function (ticketCard, payload)
        {
            if (!ticketCard)
            {
                return Promise.resolve();
            }

            setCategoryFeedback(ticketCard, '', false);
            return apiFetchJson('change_ticket_category', Object.assign({
                ticket_id: Number(ticketCard.getAttribute('data-ticket-id') || 0),
                current_page: ticketPollPayload.current_page || 'admin.php',
                viewer_email: ticketPollPayload.viewer_email || '',
                user_is_admin: !!ticketPollPayload.user_is_admin
            }, payload || {})).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    setCategoryFeedback(ticketCard, data && data.error ? data.error : '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                    return data || null;
                }

                applyCategoryChangeToCard(ticketCard, data);
                closeCategoryModal(ticketCard);
                return data;
            }).catch(function ()
            {
                setCategoryFeedback(ticketCard, '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                return null;
            });
        };

        var syncTitleChangeViaApi = function (ticketCard, payload)
        {
            if (!ticketCard)
            {
                return Promise.resolve();
            }

            setTitleFeedback(ticketCard, '', false);
            return apiFetchJson('change_ticket_title', Object.assign({
                ticket_id: Number(ticketCard.getAttribute('data-ticket-id') || 0),
                current_page: ticketPollPayload.current_page || 'admin.php',
                viewer_email: ticketPollPayload.viewer_email || '',
                user_is_admin: !!ticketPollPayload.user_is_admin
            }, payload || {})).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    setTitleFeedback(ticketCard, data && data.error ? data.error : '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                    return data || null;
                }

                applyTitleChangeToCard(ticketCard, data);
                closeTitleModal(ticketCard);
                return data;
            }).catch(function ()
            {
                setTitleFeedback(ticketCard, '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                return null;
            });
        };

        var syncParticipantsViaApi = function (ticketCard, operation, payload)
        {
            if (!ticketCard)
            {
                return Promise.resolve();
            }

            setParticipantFeedback(ticketCard, '', false);
            return apiFetchJson('manage_ticket_participants', Object.assign({
                operation: operation,
                ticket_id: Number(ticketCard.getAttribute('data-ticket-id') || 0),
                viewer_email: ticketPollPayload.viewer_email || '',
                user_is_admin: !!ticketPollPayload.user_is_admin
            }, payload || {})).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    setParticipantFeedback(ticketCard, data && data.error ? data.error : '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                    return data || null;
                }

                applyParticipantSummaryToCard(
                    ticketCard,
                    data.participant_emails,
                    data.requester_label,
                    data.requester_tooltip,
                    data.creator_email || ''
                );
                setParticipantFeedback(ticketCard, data.message || '', false);
                return data;
            }).catch(function ()
            {
                setParticipantFeedback(ticketCard, '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                return null;
            });
        };

        var collectPendingRemoveEmails = function (ticketCard)
        {
            if (!ticketCard)
            {
                return [];
            }

            return Array.prototype.map.call(
                ticketCard.querySelectorAll('[data-role="participant-remove-toggle"].is-pending-remove'),
                function (node)
                {
                    return String(node.getAttribute('data-participant-email') || '').trim().toLowerCase();
                }
            ).filter(function (email)
            {
                return email !== '';
            });
        };

        var clearParticipantInputChips = function (form)
        {
            if (!form)
            {
                return;
            }

            var participantInput = form.querySelector('input[name="participant_emails"]');
            if (participantInput)
            {
                participantInput.value = '';
            }

            var chipContainer = form.querySelector('.email-chip-field');
            if (chipContainer)
            {
                chipContainer.querySelectorAll('.email-chip-item').forEach(function (chip)
                {
                    chip.remove();
                });
            }
        };

        var closeParticipantsModal = function (ticketCard)
        {
            if (!ticketCard)
            {
                return;
            }

            var modal = ticketCard.querySelector('[data-role="ticket-participants-modal"]');
            if (!modal)
            {
                return;
            }

            var modalCard = modal.querySelector('.ticket-participants-modal-card');
            if (modalCard)
            {
                modalCard.classList.remove('is-save-hover');
            }
            modal.hidden = true;
            modal.classList.remove('is-open');
            document.documentElement.style.overflow = '';
        };

        document.addEventListener('submit', function (event)
        {
            var addForm = event.target.closest('[data-role="participant-add-form"]');
            if (addForm)
            {
                event.preventDefault();
                var addCard = addForm.closest('details.ticket-card');
                var participantInput = addForm.querySelector('input[name="participant_emails"]');
                var participantsRaw = participantInput ? participantInput.value : '';
                var pendingRemovals = collectPendingRemoveEmails(addCard);

                syncParticipantsViaApi(addCard, 'apply', {
                    participant_emails: participantsRaw,
                    remove_participant_emails: pendingRemovals
                }).then(function (data)
                {
                    if (!data || data.success !== true)
                    {
                        return;
                    }
                    clearParticipantInputChips(addForm);
                });
                return;
            }

        });

        document.addEventListener('change', function (event)
        {
            var checkbox = event.target && event.target.matches && event.target.matches('[data-role="message-checkbox"]')
                ? event.target
                : null;
            if (!checkbox)
            {
                return;
            }

            var messageNode = checkbox.closest ? checkbox.closest('.message[data-message-id]') : null;
            var ticketCard = checkbox.closest ? checkbox.closest('details.ticket-card[data-ticket-id]') : null;
            if (!messageNode || !ticketCard)
            {
                checkbox.checked = !checkbox.checked;
                return;
            }

            var ticketId = parseInt(ticketCard.getAttribute('data-ticket-id') || '0', 10);
            var messageId = parseInt(messageNode.getAttribute('data-message-id') || '0', 10);
            var lineIndex = parseInt(checkbox.getAttribute('data-line-index') || '-1', 10);
            if (ticketId <= 0 || messageId <= 0 || lineIndex < 0)
            {
                checkbox.checked = !checkbox.checked;
                return;
            }

            var previousChecked = !checkbox.checked;
            checkbox.disabled = true;

            apiFetchJson('update_ticket_message_checkbox', {
                csrf_token: csrfToken,
                ticket_id: ticketId,
                message_id: messageId,
                line_index: lineIndex,
                checked: !!checkbox.checked,
                viewer_email: ticketPollPayload.viewer_email || '',
                user_is_admin: !!ticketPollPayload.user_is_admin
            }).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    checkbox.checked = previousChecked;
                    return;
                }

                if (typeof data.message_text === 'string')
                {
                    try
                    {
                        messageNode.setAttribute('data-message-text', JSON.stringify(data.message_text));
                    }
                    catch (error)
                    {
                        // UI state already reflects the intended change.
                    }
                }
            }).catch(function ()
            {
                checkbox.checked = previousChecked;
            }).finally(function ()
            {
                checkbox.disabled = false;
            });
        });

        var syncPrivateToggleState = function (toggleLabel, isPrivate)
        {
            if (!toggleLabel)
            {
                return;
            }

            toggleLabel.classList.toggle('is-active', !!isPrivate);

            var pill = toggleLabel.querySelector('[data-role="private-ticket-pill"]');
            if (pill)
            {
                var privateLabel = pill.getAttribute('data-label-private') || '';
                var publicLabel = pill.getAttribute('data-label-public') || '';
                pill.textContent = isPrivate ? privateLabel : publicLabel;
            }
        };

        document.addEventListener('click', function (event)
        {
            var privateToggleLabel = event.target.closest('.private-ticket-toggle');
            if (privateToggleLabel)
            {
                event.stopPropagation();
            }
        });

        document.addEventListener('change', function (event)
        {
            var privateToggle = event.target && event.target.matches && event.target.matches('[data-role="ticket-private-toggle"]')
                ? event.target
                : null;
            if (!privateToggle)
            {
                return;
            }

            var ticketCard = privateToggle.closest ? privateToggle.closest('details.ticket-card[data-ticket-id]') : null;
            if (!ticketCard)
            {
                privateToggle.checked = !privateToggle.checked;
                return;
            }

            var ticketId = parseInt(ticketCard.getAttribute('data-ticket-id') || '0', 10);
            if (ticketId <= 0)
            {
                privateToggle.checked = !privateToggle.checked;
                return;
            }

            var previousChecked = !privateToggle.checked;
            var privateChip = privateToggle.closest('.private-ticket-toggle');
            syncPrivateToggleState(privateChip, privateToggle.checked);
            privateToggle.disabled = true;

            apiFetchJson('update_ticket_private', {
                csrf_token: csrfToken,
                ticket_id: ticketId,
                is_private: !!privateToggle.checked,
                viewer_email: ticketPollPayload.viewer_email || '',
                user_is_admin: !!ticketPollPayload.user_is_admin,
                is_admin_portal: !!ticketPollPayload.is_admin_portal
            }).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    privateToggle.checked = previousChecked;
                    syncPrivateToggleState(privateChip, privateToggle.checked);
                    return;
                }

                syncPrivateToggleState(privateChip, !!privateToggle.checked);
            }).catch(function ()
            {
                privateToggle.checked = previousChecked;
                syncPrivateToggleState(privateChip, privateToggle.checked);
            }).finally(function ()
            {
                privateToggle.disabled = false;
            });
        });

        var parseJsonString = function (value)
        {
            try
            {
                return JSON.parse(String(value || '""'));
            }
            catch (error)
            {
                return '';
            }
        };

        var formatTicketMessageTextForToggle = function (text, messageId)
        {
            var normalized = String(text || '').replace(/\r\n?/g, '\n').trim();
            if (normalized === '')
            {
                return '';
            }

            return normalized.split('\n').map(function (line, lineIndex)
            {
                if (line.trim() === '')
                {
                    return '';
                }

                var checkboxMatch = line.match(/^(\s*)\[( |x|X)\]\s*(.*)$/);
                if (checkboxMatch)
                {
                    var isChecked = String(checkboxMatch[2] || '').toLowerCase() === 'x';
                    var label = String(checkboxMatch[3] || '');
                    return '<label class="message-checkbox-line">'
                        + '<input type="checkbox" data-role="message-checkbox" data-message-id="' + parseInt(messageId || 0, 10) + '" data-line-index="' + lineIndex + '"' + (isChecked ? ' checked' : '') + '>'
                        + '<span>' + (label !== '' ? renderShortcutMarkup(escapeHtml(label)) : '&nbsp;') + '</span>'
                        + '</label>';
                }

                return renderShortcutMarkup(escapeHtml(line));
            }).join('<br>');
        };

        document.addEventListener('click', function (event)
        {
            var messageToggle = event.target.closest('[data-role="message-translation-toggle"]');
            if (messageToggle)
            {
                var messageNode = messageToggle.closest('.message');
                var messageContent = messageNode ? messageNode.querySelector('[data-role="message-text-content"]') : null;
                if (!messageContent)
                {
                    return;
                }

                var currentlyShowing = String(messageContent.getAttribute('data-showing') || 'translated');
                var nextShowing = currentlyShowing === 'translated' ? 'original' : 'translated';
                var translatedText = parseJsonString(messageContent.getAttribute('data-translated-text'));
                var originalText = parseJsonString(messageContent.getAttribute('data-original-text'));
                var nextText = nextShowing === 'translated' ? translatedText : originalText;

                messageContent.innerHTML = formatTicketMessageTextForToggle(String(nextText || ''), parseInt(messageNode.getAttribute('data-message-id') || '0', 10));
                messageContent.setAttribute('data-showing', nextShowing);
                messageToggle.setAttribute('data-showing', nextShowing);
                messageToggle.textContent = nextShowing === 'translated'
                    ? String(messageToggle.getAttribute('data-label-original') || 'Show original')
                    : String(messageToggle.getAttribute('data-label-translated') || 'Show translation');
                return;
            }

            var titleToggle = event.target.closest('[data-role="title-translation-toggle"]');
            if (!titleToggle)
            {
                return;
            }

            var ticketCard = titleToggle.closest('details.ticket-card');
            var titleNode = ticketCard ? ticketCard.querySelector('[data-role="ticket-title"]') : null;
            if (!titleNode)
            {
                return;
            }

            var currentlyShowingTitle = String(titleNode.getAttribute('data-showing') || 'translated');
            var nextShowingTitle = currentlyShowingTitle === 'translated' ? 'original' : 'translated';
            var translatedTitle = parseJsonString(titleNode.getAttribute('data-translated-text'));
            var originalTitle = parseJsonString(titleNode.getAttribute('data-original-text'));
            titleNode.textContent = nextShowingTitle === 'translated' ? String(translatedTitle || '') : String(originalTitle || '');
            titleNode.setAttribute('data-showing', nextShowingTitle);

            titleToggle.setAttribute('data-showing', nextShowingTitle);
            titleToggle.textContent = nextShowingTitle === 'translated'
                ? String(titleToggle.getAttribute('data-label-original') || 'Show original')
                : String(titleToggle.getAttribute('data-label-translated') || 'Show translation');
        });

        document.addEventListener('click', function (event)
        {
            var errorBtn = event.target.closest('[data-role="translation-status"][data-status="error"]');
            if (!errorBtn)
            {
                return;
            }

            var errorMessage = errorBtn.getAttribute('data-error-message') || '<?= addslashes(__('translation.error_fallback')) ?>';
            var modal = document.createElement('div');
            modal.className = 'modal-overlay';
            modal.addEventListener('click', function (e)
            {
                if (e.target === modal)
                {
                    modal.remove();
                }
            });

            var modalContent = document.createElement('div');
            modalContent.className = 'modal-content';
            modalContent.innerHTML = '<div class="modal-header"><h3><?= addslashes(__('translation.error_title')) ?></h3><button class="modal-close" type="button" aria-label="Close">&times;</button></div><div class="modal-body"><p>' + escapeHtml(errorMessage) + '</p></div>';

            var closeBtn = modalContent.querySelector('.modal-close');
            closeBtn.addEventListener('click', function ()
            {
                modal.remove();
            });

            modal.appendChild(modalContent);
            document.body.appendChild(modal);
        });

        document.addEventListener('mouseover', function (event)
        {
            var applyButton = event.target.closest('[data-role="participants-apply-button"]');
            if (!applyButton)
            {
                return;
            }

            var modalCard = applyButton.closest('.ticket-participants-modal-card');
            if (modalCard)
            {
                modalCard.classList.add('is-save-hover');
            }
        });

        document.addEventListener('mouseout', function (event)
        {
            var applyButton = event.target.closest('[data-role="participants-apply-button"]');
            if (!applyButton)
            {
                return;
            }

            if (event.relatedTarget && applyButton.contains(event.relatedTarget))
            {
                return;
            }

            var modalCard = applyButton.closest('.ticket-participants-modal-card');
            if (modalCard)
            {
                modalCard.classList.remove('is-save-hover');
            }
        });

        var TRANSLATION_LABEL_ORIGINAL = <?= json_encode(__('ticket.show_original'), JSON_UNESCAPED_UNICODE) ?>;
        var TRANSLATION_LABEL_TRANSLATED = <?= json_encode(__('ticket.show_translation'), JSON_UNESCAPED_UNICODE) ?>;
        var TRANSLATION_ERROR_TOOLTIP = <?= json_encode(__('translation.error_tooltip'), JSON_UNESCAPED_UNICODE) ?>;
        var TRANSLATION_ERROR_FALLBACK = <?= json_encode(__('translation.error_fallback'), JSON_UNESCAPED_UNICODE) ?>;

        var applyTranslationErrorIndicator = function (messageNode, errorCode)
        {
            if (!messageNode)
            {
                return;
            }

            messageNode.setAttribute('data-translation-status', 'error');
            var statusIndicator = messageNode.querySelector('[data-role="translation-status"]');
            if (!statusIndicator)
            {
                var messageMeta = messageNode.querySelector('.message-meta');
                if (!messageMeta)
                {
                    return;
                }

                statusIndicator = document.createElement('button');
                statusIndicator.type = 'button';
                statusIndicator.className = 'translation-status-indicator';
                statusIndicator.setAttribute('data-role', 'translation-status');
                messageMeta.appendChild(statusIndicator);
            }

            statusIndicator.setAttribute('data-status', 'error');
            statusIndicator.title = TRANSLATION_ERROR_TOOLTIP;
            statusIndicator.setAttribute('data-error-message', errorCode ? (TRANSLATION_ERROR_FALLBACK + ' (' + String(errorCode) + ')') : TRANSLATION_ERROR_FALLBACK);
            statusIndicator.innerHTML = '';

            var errorIcon = document.createElement('svg');
            errorIcon.className = 'translation-error-icon';
            errorIcon.setAttribute('viewBox', '0 0 24 24');
            errorIcon.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            errorIcon.setAttribute('aria-hidden', 'true');
            errorIcon.innerHTML = '<path d="M12 3L2 21h20L12 3z" fill="none" stroke="currentColor" stroke-width="1.8"/><line x1="12" y1="9" x2="12" y2="14" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="17" r="1" fill="currentColor"/>';
            statusIndicator.appendChild(errorIcon);
        };

        var applyTranslationToCard = function (card, data)
        {
            var ticketId = parseInt(card.getAttribute('data-ticket-id') || '0', 10);
            if (!data || !data.success || data.ticket_id !== ticketId)
            {
                return;
            }

            var titleNode = card.querySelector('[data-role="ticket-title"]');
            if (titleNode)
            {
                var translatedTitle = String(data.title || '');
                var rawTitle = String(data.title_raw || data.title || '');
                titleNode.setAttribute('data-translated-text', JSON.stringify(translatedTitle));
                titleNode.setAttribute('data-original-text', JSON.stringify(rawTitle));
                titleNode.setAttribute('data-showing', 'translated');
                titleNode.textContent = translatedTitle;

                var existingTitleToggle = card.querySelector('[data-role="title-translation-toggle"]');
                if (data.title_is_translated)
                {
                    if (!existingTitleToggle)
                    {
                        var titleToggleBtn = document.createElement('button');
                        titleToggleBtn.type = 'button';
                        titleToggleBtn.className = 'translation-toggle-button';
                        titleToggleBtn.setAttribute('data-role', 'title-translation-toggle');
                        titleToggleBtn.setAttribute('data-label-original', TRANSLATION_LABEL_ORIGINAL);
                        titleToggleBtn.setAttribute('data-label-translated', TRANSLATION_LABEL_TRANSLATED);
                        titleToggleBtn.setAttribute('data-showing', 'translated');
                        titleToggleBtn.textContent = TRANSLATION_LABEL_ORIGINAL;
                        var mainTitle = card.querySelector('.ticket-main-title');
                        if (mainTitle)
                        {
                            mainTitle.appendChild(titleToggleBtn);
                        }
                    }
                    else
                    {
                        existingTitleToggle.setAttribute('data-showing', 'translated');
                        existingTitleToggle.textContent = TRANSLATION_LABEL_ORIGINAL;
                    }
                }
                else if (existingTitleToggle)
                {
                    existingTitleToggle.remove();
                }
            }

            (data.messages || []).forEach(function (msg)
            {
                var messageNode = card.querySelector('[data-message-id="' + parseInt(msg.id, 10) + '"]');
                if (!messageNode)
                {
                    return;
                }

                if (msg.translation_error)
                {
                    console.warn('[translation] Error for message #' + msg.id + ' in ticket #' + ticketId + ':', msg.translation_error, msg.translation_error_detail || '');
                    applyTranslationErrorIndicator(messageNode, msg.translation_error);
                    return;
                }

                var displayText = String(msg.message_text || '');
                var rawText = String(msg.message_text_raw || msg.message_text || '');

                var messageContent = messageNode.querySelector('[data-role="message-text-content"]');
                if (messageContent)
                {
                    messageContent.setAttribute('data-translated-text', JSON.stringify(displayText));
                    messageContent.setAttribute('data-original-text', JSON.stringify(rawText));
                    messageContent.setAttribute('data-showing', 'translated');
                    messageContent.innerHTML = formatTicketMessageTextForToggle(displayText, msg.id);
                }

                messageNode.setAttribute('data-translation-status', 'loaded');

                var statusIndicator = messageNode.querySelector('[data-role="translation-status"]');
                if (statusIndicator)
                {
                    statusIndicator.remove();
                }

                var existingMsgToggle = messageNode.querySelector('[data-role="message-translation-toggle"]');
                if (msg.message_is_translated)
                {
                    if (!existingMsgToggle)
                    {
                        var msgToggleBtn = document.createElement('button');
                        msgToggleBtn.type = 'button';
                        msgToggleBtn.className = 'translation-toggle-button';
                        msgToggleBtn.setAttribute('data-role', 'message-translation-toggle');
                        msgToggleBtn.setAttribute('data-label-original', TRANSLATION_LABEL_ORIGINAL);
                        msgToggleBtn.setAttribute('data-label-translated', TRANSLATION_LABEL_TRANSLATED);
                        msgToggleBtn.setAttribute('data-showing', 'translated');
                        msgToggleBtn.textContent = TRANSLATION_LABEL_ORIGINAL;
                        var messageMeta = messageNode.querySelector('.message-meta');
                        if (messageMeta)
                        {
                            messageMeta.appendChild(msgToggleBtn);
                        }
                    }
                    else
                    {
                        existingMsgToggle.setAttribute('data-showing', 'translated');
                        existingMsgToggle.textContent = TRANSLATION_LABEL_ORIGINAL;
                    }
                }
                else if (existingMsgToggle)
                {
                    existingMsgToggle.remove();
                }
            });
        };

        var triggerLazyTranslations = function (container)
        {
            var pendingCards = (container || document).querySelectorAll('[data-needs-translation="1"]');
            pendingCards.forEach(function (card)
            {
                var ticketId = parseInt(card.getAttribute('data-ticket-id') || '0', 10);
                if (!ticketId)
                {
                    return;
                }

                card.setAttribute('data-needs-translation', 'loading');

                var translateParams = {
                    ticket_id: ticketId,
                    language: ticketPollPayload.current_language || 'nl',
                    viewer_email: ticketPollPayload.viewer_email || '',
                    user_is_admin: !!ticketPollPayload.user_is_admin,
                    is_admin_portal: !!ticketPollPayload.is_admin_portal,
                };
                console.log('[translation] Requesting ticket #' + ticketId, translateParams);

                apiFetchJson('translate_ticket', translateParams).then(function (data)
                {
                    console.log('[translation] Response for ticket #' + ticketId, data);
                    card.setAttribute('data-needs-translation', '0');
                    applyTranslationToCard(card, data);
                }).catch(function (error)
                {
                    console.error('[translation] Fetch error for ticket #' + ticketId, error);
                    card.setAttribute('data-needs-translation', '1');

                    card.querySelectorAll('[data-translation-status="pending"]').forEach(function (msg)
                    {
                        applyTranslationErrorIndicator(msg, String(error && error.message ? error.message : 'network_error'));
                    });
                });
            });
        };

        var postWebPushSubscription = function (action, subscription)
        {
            if (!webPushSubscribeUrl)
            {
                return Promise.resolve();
            }

            return apiFetchJson('webpush_subscription', {
                csrf_token: csrfToken,
                subscription_action: action,
                subscription: subscription || null
            }).catch(function ()
            {
                // Retry happens on next refresh/poll cycle.
            });
        };

        var syncWebPushSubscription = function ()
        {
            if (webPushSyncInFlight)
            {
                return;
            }

            if (!webPushSubscribeUrl || !webPushVapidPublicKey || !webPushServiceWorkerUrl)
            {
                return;
            }

            if (!('serviceWorker' in navigator) || !('PushManager' in window))
            {
                return;
            }

            webPushSyncInFlight = true;

            navigator.serviceWorker.register(webPushServiceWorkerUrl)
                .then(function (registration)
                {
                    return registration.pushManager.getSubscription().then(function (existingSubscription)
                    {
                        return {
                            registration: registration,
                            subscription: existingSubscription
                        };
                    });
                })
                .then(function (context)
                {
                    if (Notification.permission !== 'granted')
                    {
                        if (!context.subscription)
                        {
                            return Promise.resolve();
                        }

                        var existingPayload = context.subscription.toJSON ? context.subscription.toJSON() : null;
                        return context.subscription.unsubscribe().then(function ()
                        {
                            return postWebPushSubscription('unsubscribe', existingPayload);
                        });
                    }

                    if (context.subscription)
                    {
                        var payload = context.subscription.toJSON ? context.subscription.toJSON() : null;
                        return postWebPushSubscription('subscribe', payload);
                    }

                    var appServerKey = base64UrlToUint8Array(webPushVapidPublicKey);
                    return context.registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: appServerKey
                    }).then(function (newSubscription)
                    {
                        var newPayload = newSubscription.toJSON ? newSubscription.toJSON() : null;
                        return postWebPushSubscription('subscribe', newPayload);
                    });
                })
                .catch(function ()
                {
                    // Browser/policy may block silent subscription attempts.
                })
                .finally(function ()
                {
                    webPushSyncInFlight = false;
                });
        };

        var requestBrowserNotificationPermission = function ()
        {
            if (!('Notification' in window) || browserNotificationRequestAttempted)
            {
                return;
            }

            if (Notification.permission !== 'default')
            {
                browserNotificationRequestAttempted = true;
                return;
            }

            browserNotificationRequestAttempted = true;
            Notification.requestPermission()
                .then(function (permission)
                {
                    if (permission === 'granted')
                    {
                        syncWebPushSubscription();
                    }
                })
                .catch(function ()
                {
                    // Intentionally ignored: browser decides whether prompts are allowed.
                });
        };

        var resolveNotificationOpenUrl = function (notification)
        {
            if (notification && notification.open_url)
            {
                return String(notification.open_url);
            }

            if (browserNotificationOpenTemplate && notification && notification.ticket_id)
            {
                return browserNotificationOpenTemplate.replace('__TICKET_ID__', String(notification.ticket_id));
            }

            return window.location.pathname;
        };

        var showDesktopNotification = function (notification)
        {
            if (!('Notification' in window) || Notification.permission !== 'granted')
            {
                return;
            }

            var title = (notification && notification.title ? String(notification.title) : '<?= addslashes(__('header.title')) ?>');
            var body = (notification && notification.body ? String(notification.body) : '');
            var tag = 'ticket-' + String(notification && notification.ticket_id ? notification.ticket_id : 'general');
            var desktopNotification = new Notification(title, {
                body: body,
                tag: tag,
                renotify: true
            });

            desktopNotification.onclick = function ()
            {
                window.focus();
                window.location.href = resolveNotificationOpenUrl(notification);
                desktopNotification.close();
            };

            window.setTimeout(function ()
            {
                desktopNotification.close();
            }, 12000);
        };

        var pollBrowserNotifications = function ()
        {
            if (!browserNotificationPollUrl || browserNotificationInFlight || document.hidden)
            {
                return;
            }

            if (!('Notification' in window))
            {
                return;
            }

            if (Notification.permission === 'default')
            {
                requestBrowserNotificationPermission();
                return;
            }

            if (Notification.permission !== 'granted')
            {
                return;
            }

            browserNotificationInFlight = true;
            apiFetchJson('browser_notifications_poll', {})
                .then(function (data)
                {
                    (data && Array.isArray(data.notifications) ? data.notifications : []).forEach(function (notification)
                    {
                        showDesktopNotification(notification);
                    });
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }

                    // Silently ignore: next poll can recover.
                })
                .finally(function ()
                {
                    browserNotificationInFlight = false;
                });
        };

        var captureOpenTicketIds = function (section)
        {
            return Array.prototype.map.call(
                section.querySelectorAll('details.ticket-card[open][data-ticket-id]'),
                function (detail)
                {
                    return detail.getAttribute('data-ticket-id');
                }
            );
        };

        var restoreOpenTicketIds = function (section, openTicketIds)
        {
            openTicketIds.forEach(function (ticketId)
            {
                var detail = section.querySelector('details.ticket-card[data-ticket-id="' + ticketId + '"]');
                if (detail)
                {
                    detail.open = true;
                }
            });
        };

        var openTicketScrollDone = false;

        var scrollTicketCardIntoView = function (card)
        {
            if (!card)
            {
                return;
            }

            window.requestAnimationFrame(function ()
            {
                card.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        };

        var focusOpenTicketFromUrl = function ()
        {
            if (!liveTicketSection || openTicketScrollDone)
            {
                return;
            }

            var openTicketId = parseInt(ticketPollPayload.open_ticket_id || '0', 10);
            if (openTicketId <= 0)
            {
                return;
            }

            var card = liveTicketSection.querySelector('details.ticket-card[data-ticket-id="' + openTicketId + '"]');
            if (!card)
            {
                return;
            }

            openTicketScrollDone = true;
            card.open = true;
            loadLazyTicketThread(card);
            scrollTicketCardIntoView(card);
        };

        var ticketSectionHasActiveInput = function (section)
        {
            if (section.querySelector('[data-role="ticket-participants-modal"].is-open, [data-role="ticket-category-modal"].is-open'))
            {
                return true;
            }

            var activeElement = document.activeElement;
            if (!activeElement || !section.contains(activeElement))
            {
                return false;
            }

            return /INPUT|TEXTAREA|SELECT/.test(activeElement.tagName);
        };

        var refreshLiveTicketSection = function ()
        {
            if (!liveTicketSection || liveTicketRefreshInFlight || ticketSectionHasActiveInput(liveTicketSection))
            {
                return;
            }

            liveTicketRefreshInFlight = true;
            apiFetchJson('ticket_poll', ticketPollPayload)
                .then(function (data)
                {
                    applyIncrementalTicketUpdate(data);
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }

                    window.location.reload();
                })
                .finally(function ()
                {
                    liveTicketRefreshInFlight = false;
                });
        };

        var ensureTicketList = function ()
        {
            var list = liveTicketSection.querySelector('.ticket-list');
            if (!list)
            {
                list = document.createElement('div');
                list.className = 'ticket-list';
                var emptyState = liveTicketSection.querySelector('.empty-state');
                if (emptyState)
                {
                    emptyState.remove();
                }
                liveTicketSection.appendChild(list);
            }

            return list;
        };

        var syncEmptyState = function (data)
        {
            var list = liveTicketSection.querySelector('.ticket-list');
            var emptyState = liveTicketSection.querySelector('.empty-state');

            if (data.is_empty)
            {
                if (list)
                {
                    list.remove();
                }
                if (!emptyState)
                {
                    var template = document.createElement('template');
                    template.innerHTML = (data.empty_html || '').trim();
                    emptyState = template.content.firstElementChild;
                    if (emptyState)
                    {
                        liveTicketSection.appendChild(emptyState);
                    }
                }
                return null;
            }

            if (emptyState)
            {
                emptyState.remove();
            }

            return ensureTicketList();
        };

        var setText = function (element, value)
        {
            if (element && element.textContent !== value)
            {
                element.textContent = value;
            }
        };

        var setValue = function (element, value)
        {
            if (element && element.value !== value)
            {
                element.value = value;
            }
        };

        var applyTicketCardFields = function (card, ticket)
        {
            setText(card.querySelector('[data-role="ticket-number"]'), '#' + ticket.id);
            setText(card.querySelector('[data-role="ticket-title"]'), ticket.title);
            var rawTitle = String(ticket.title_raw || ticket.title || '');
            setText(card.querySelector('[data-role="meta-title-value"]'), rawTitle);
            var openTitleButton = card.querySelector('[data-role="change-title-open"]');
            if (openTitleButton && rawTitle !== '')
            {
                openTitleButton.setAttribute('data-current-title', rawTitle);
            }
            applyParticipantSummaryToCard(card, ticket.participant_emails, ticket.requester_label, ticket.requester_tooltip, ticket.user_email);
            setText(card.querySelector('[data-role="ticket-category"]'), ticket.category_label);
            setText(card.querySelector('[data-role="ticket-created"]'), ticket.created_at_label);
            setText(card.querySelector('[data-role="meta-created-value"]'), ticket.meta_created_value);
            setText(card.querySelector('[data-role="meta-updated-value"]'), ticket.meta_updated_value);
            setText(card.querySelector('[data-role="meta-due-date-value"]'), ticket.due_date_label);
            setValue(card.querySelector('[data-role="due-date-input"]'), ticket.due_date);
            setValue(card.querySelector('[data-role="priority-select"]'), String(ticket.priority));
            setValue(card.querySelector('[data-role="status-select"]'), ticket.status);
            setValue(card.querySelector('[data-role="assigned-select"]'), ticket.assigned_email);

            var statusPill = card.querySelector('[data-role="status-pill"]');
            if (statusPill)
            {
                setText(statusPill, ticket.status_label);
                statusPill.style.setProperty('--ticket-color', ticket.status_color);
            }

            var priorityPill = card.querySelector('[data-role="priority-pill"]');
            if (priorityPill)
            {
                setText(priorityPill, '<?= addslashes(__('ticket.meta_priority')) ?> ' + ticket.priority + ' · ' + ticket.priority_label);
                priorityPill.style.setProperty('--ticket-color', ticket.priority_color);
            }

            var assigneeBadge = card.querySelector('[data-role="assignee-badge"]');
            if (assigneeBadge)
            {
                setText(assigneeBadge, ticket.assigned_label);
                assigneeBadge.style.setProperty('--assignee-color', ticket.assigned_color);
            }

            var timeOpenBadge = card.querySelector('[data-role="time-open-badge"]');
            if (timeOpenBadge)
            {
                setText(timeOpenBadge, '<?= addslashes(__('ticket.time_open')) ?>: ' + ticket.time_open_label);
            }

            var reopenWrap = card.querySelector('[data-role="reopen-wrap"]');
            if (reopenWrap)
            {
                var reopenEnabled = reopenWrap.getAttribute('data-user-reopen-enabled') === '1';
                reopenWrap.hidden = !reopenEnabled || ticket.status !== 'afgehandeld';
            }

            var privateToggleLabelCard = card.querySelector('.private-ticket-toggle');
            if (privateToggleLabelCard)
            {
                var privateInput = privateToggleLabelCard.querySelector('[data-role="ticket-private-toggle"]');
                if (privateInput && !privateInput.disabled)
                {
                    privateInput.checked = !!ticket.is_private;
                }
                syncPrivateToggleState(privateToggleLabelCard, !!ticket.is_private);
            }

            card.style.setProperty('--ticket-color', ticket.status_color);
        };

        var appendNewMessages = function (card, ticket)
        {
            var messagesWrap = card.querySelector('[data-role="messages-wrap"]');
            var thread = card.querySelector('[data-role="thread"]');
            if (!messagesWrap || !thread)
            {
                return;
            }

            var existingMessageIds = {};
            thread.querySelectorAll('[data-message-id]').forEach(function (messageNode)
            {
                existingMessageIds[messageNode.getAttribute('data-message-id')] = true;
            });

            ticket.messages.forEach(function (message)
            {
                if (existingMessageIds[String(message.id)])
                {
                    return;
                }

                var template = document.createElement('template');
                template.innerHTML = message.html.trim();
                var messageNode = template.content.firstElementChild;
                if (messageNode)
                {
                    thread.appendChild(messageNode);
                    messagesWrap.hidden = false;
                    card.setAttribute('data-needs-translation', '1');
                }
            });
        };

        var insertOrMoveCard = function (list, card, previousCard)
        {
            if (!previousCard)
            {
                if (list.firstElementChild !== card)
                {
                    list.insertBefore(card, list.firstElementChild);
                }
                return;
            }

            if (previousCard.nextElementSibling !== card)
            {
                list.insertBefore(card, previousCard.nextElementSibling);
            }
        };

        var applyIncrementalTicketUpdate = function (data)
        {
            if (!liveTicketSection || !data)
            {
                return;
            }

            if (data.unchanged)
            {
                return;
            }

            var currentSignature = liveTicketSection.getAttribute('data-ticket-signature') || '';
            if (data.signature && data.signature === currentSignature)
            {
                return;
            }

            var list = syncEmptyState(data);
            liveTicketSection.setAttribute('data-ticket-signature', data.signature || '');
            if (data.signature)
            {
                ticketPollPayload.last_signature = data.signature;
            }
            if (!list)
            {
                return;
            }

            var existingCards = {};
            list.querySelectorAll('details.ticket-card[data-ticket-id]').forEach(function (card)
            {
                existingCards[card.getAttribute('data-ticket-id')] = card;
            });

            var seenTicketIds = {};
            var previousCard = null;

            (data.tickets || []).forEach(function (ticket)
            {
                var ticketId = String(ticket.id);
                var card = existingCards[ticketId] || null;
                seenTicketIds[ticketId] = true;

                if (!card)
                {
                    var template = document.createElement('template');
                    template.innerHTML = (ticket.card_html || '').trim();
                    card = template.content.querySelector('details.ticket-card[data-ticket-id]');
                    if (!card)
                    {
                        return;
                    }

                    card.querySelectorAll('.textarea-wrapper').forEach(initTextareaWrapper);
                } else
                {
                    applyTicketCardFields(card, ticket);
                    appendNewMessages(card, ticket);
                }

                insertOrMoveCard(list, card, previousCard);
                if (typeof hydrateTicketThumbnails === 'function')
                {
                    hydrateTicketThumbnails(card);
                }
                initializeEmailChipInputs(card);
                initializeAccumulatingFileInputs(card);
                previousCard = card;
            });

            Object.keys(existingCards).forEach(function (ticketId)
            {
                if (!seenTicketIds[ticketId] && existingCards[ticketId].parentNode)
                {
                    existingCards[ticketId].parentNode.removeChild(existingCards[ticketId]);
                }
            });

            if (!list.children.length)
            {
                syncEmptyState({
                    is_empty: true,
                    empty_html: data.empty_html || ''
                });
            }

            triggerLazyTranslations(list);
        };

        var pollLiveTicketSection = function ()
        {
            if (!liveTicketSection || document.hidden)
            {
                return;
            }

            apiFetchJson('ticket_poll', ticketPollPayload)
                .then(function (data)
                {
                    if (!data || data.unchanged)
                    {
                        return;
                    }

                    applyIncrementalTicketUpdate(data);
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }

                    if (liveTicketPollTimer !== null)
                    {
                        window.clearInterval(liveTicketPollTimer);
                        liveTicketPollTimer = null;
                    }
                });
        };

        var appendThreadMessagesToCard = function (card, messages)
        {
            var messagesWrap = card.querySelector('[data-role="messages-wrap"]');
            var thread = card.querySelector('[data-role="thread"]');
            if (!messagesWrap || !thread)
            {
                return;
            }

            var loadingHint = thread.querySelector('[data-role="thread-loading-hint"]');
            if (loadingHint)
            {
                loadingHint.hidden = true;
            }

            (messages || []).forEach(function (message)
            {
                var template = document.createElement('template');
                template.innerHTML = (message.html || '').trim();
                var messageNode = template.content.firstElementChild;
                if (messageNode)
                {
                    thread.appendChild(messageNode);
                }
            });

            messagesWrap.hidden = false;
            thread.removeAttribute('data-lazy-messages');
            messagesWrap.removeAttribute('data-lazy-messages');
            card.dataset.threadLoaded = '1';

            if (typeof hydrateTicketThumbnails === 'function')
            {
                hydrateTicketThumbnails(card);
            }

            if (typeof triggerLazyTranslations === 'function')
            {
                triggerLazyTranslations(card);
            }
        };

        var loadLazyTicketThread = function (card)
        {
            if (!card || card.dataset.threadLoaded === '1' || card.dataset.threadLoading === '1')
            {
                return;
            }

            if (!card.querySelector('[data-role="messages-wrap"][data-lazy-messages="1"]'))
            {
                return;
            }

            card.dataset.threadLoading = '1';
            var loadingHint = card.querySelector('[data-role="thread-loading-hint"]');
            if (loadingHint)
            {
                loadingHint.hidden = false;
            }

            var threadPayload = Object.assign({}, ticketPollPayload, {
                ticket_id: parseInt(card.getAttribute('data-ticket-id') || '0', 10)
            });

            apiFetchJson('ticket_thread', threadPayload)
                .then(function (data)
                {
                    if (!data || !data.success)
                    {
                        return;
                    }

                    appendThreadMessagesToCard(card, data.messages || []);
                })
                .finally(function ()
                {
                    card.dataset.threadLoading = '0';
                });
        };

        document.addEventListener('toggle', function (event)
        {
            var card = event.target;
            if (!(card instanceof HTMLDetailsElement) || !card.classList.contains('ticket-card') || !card.open)
            {
                return;
            }

            loadLazyTicketThread(card);
        }, true);

        if (liveTicketSection)
        {
            var intervalMs = parseInt(liveTicketSection.getAttribute('data-ticket-poll-interval') || '15000', 10);
            liveTicketPollTimer = window.setInterval(pollLiveTicketSection, Math.max(intervalMs, 5000));
            window.setTimeout(function ()
            {
                triggerLazyTranslations(liveTicketSection);
            }, 200);
            window.setTimeout(focusOpenTicketFromUrl, 120);
            window.setTimeout(focusOpenTicketFromUrl, 500);
        }

        if (browserNotificationPollUrl)
        {
            var browserNotificationIntervalMs = parseInt((document.body && document.body.getAttribute('data-browser-notification-poll-interval')) || '15000', 10);
            window.setTimeout(requestBrowserNotificationPermission, 900);
            window.setTimeout(syncWebPushSubscription, 950);
            window.setTimeout(pollBrowserNotifications, 1200);
            browserNotificationPollTimer = window.setInterval(pollBrowserNotifications, Math.max(browserNotificationIntervalMs, 5000));
            document.addEventListener('visibilitychange', function ()
            {
                if (!document.hidden)
                {
                    requestBrowserNotificationPermission();
                    syncWebPushSubscription();
                    pollBrowserNotifications();
                }
            });
        }

        if (sessionKeepaliveUrl)
        {
            var sessionKeepaliveIntervalMs = parseInt((document.body && document.body.getAttribute('data-session-keepalive-interval')) || '120000', 10);
            window.setTimeout(refreshSessionKeepalive, 15000);
            sessionKeepaliveTimer = window.setInterval(refreshSessionKeepalive, Math.max(sessionKeepaliveIntervalMs, 60000));
            document.addEventListener('visibilitychange', function ()
            {
                if (!document.hidden)
                {
                    refreshSessionKeepalive(true);
                }
            });
        }

        // File preview modal + lazy thumbnails
        var previewModal = null;
        var previewIframe = null;
        var pendingThumbImages = [];
        var activeThumbLoads = 0;
        var MAX_CONCURRENT_THUMB_LOADS = 2;

        var loadNextThumbImage = function ()
        {
            while (activeThumbLoads < MAX_CONCURRENT_THUMB_LOADS && pendingThumbImages.length > 0)
            {
                var imageThumb = pendingThumbImages.shift();
                if (!imageThumb || imageThumb.dataset.thumbLoaded === '1')
                {
                    continue;
                }

                var thumbSrc = imageThumb.getAttribute('data-thumb-src') || '';
                if (!thumbSrc)
                {
                    var missingSrcButton = imageThumb.closest('.attachment-thumb-button');
                    if (missingSrcButton)
                    {
                        missingSrcButton.remove();
                    }
                    continue;
                }

                activeThumbLoads += 1;
                imageThumb.dataset.thumbLoaded = '1';

                var finalizeThumbLoad = function ()
                {
                    activeThumbLoads = Math.max(0, activeThumbLoads - 1);
                    loadNextThumbImage();
                };

                imageThumb.addEventListener('load', finalizeThumbLoad, { once: true });
                imageThumb.addEventListener('error', function ()
                {
                    imageThumb.removeAttribute('src');
                    imageThumb.dataset.thumbLoaded = '0';
                    finalizeThumbLoad();
                }, { once: true });
                imageThumb.src = thumbSrc;
            }
        };

        var enqueueThumbImage = function (imageThumb)
        {
            if (!imageThumb || imageThumb.dataset.thumbQueued === '1' || imageThumb.dataset.thumbLoaded === '1')
            {
                return;
            }

            imageThumb.dataset.thumbQueued = '1';
            pendingThumbImages.push(imageThumb);
            loadNextThumbImage();
        };

        var hydrateTicketThumbnails = function (ticketCard)
        {
            if (!ticketCard || !ticketCard.open)
            {
                return;
            }

            ticketCard.querySelectorAll('img[data-thumb-src]').forEach(function (imageThumb)
            {
                enqueueThumbImage(imageThumb);
            });

            ticketCard.querySelectorAll('[data-file-thumb-open]').forEach(function (thumbButton)
            {
                if (thumbButton.dataset.thumbInitialized === '1')
                {
                    return;
                }

                thumbButton.dataset.thumbInitialized = '1';
                var checkUrl = thumbButton.getAttribute('data-file-thumb-check-url') || '';
                var thumbSrc = thumbButton.getAttribute('data-file-thumb-src') || '';
                var thumbFrame = thumbButton.querySelector('.attachment-file-thumb-frame');

                if (!checkUrl || !thumbSrc || !thumbFrame)
                {
                    thumbButton.remove();
                    return;
                }

                fetch(checkUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'fetch'
                    }
                })
                    .then(function (response)
                    {
                        if (!response.ok)
                        {
                            throw new Error('thumb-check-failed');
                        }

                        return response.json();
                    })
                    .then(function (payload)
                    {
                        if (!payload || payload.ok !== true)
                        {
                            throw new Error('thumb-not-available');
                        }

                        thumbButton.hidden = false;
                        thumbFrame.src = thumbSrc;
                    })
                    .catch(function ()
                    {
                        thumbButton.remove();
                    });
            });
        };

        document.addEventListener('submit', function (event)
        {
            var uploadForm = event.target;
            if (!uploadForm || !uploadForm.getAttribute || uploadForm.getAttribute('enctype') !== 'multipart/form-data')
            {
                return;
            }

            var maxUploadBytes = parseInt((document.body && document.body.getAttribute('data-max-upload-bytes')) || '0', 10);
            var postMaxBytes = parseInt((document.body && document.body.getAttribute('data-post-max-bytes')) || '0', 10);
            var effectiveLimit = maxUploadBytes;
            if (postMaxBytes > 0 && (effectiveLimit <= 0 || postMaxBytes < effectiveLimit))
            {
                effectiveLimit = postMaxBytes;
            }

            if (effectiveLimit <= 0)
            {
                return;
            }

            var fileInputs = uploadForm.querySelectorAll('input[type="file"]');
            var totalBytes = 0;
            fileInputs.forEach(function (fileInput)
            {
                Array.prototype.forEach.call(fileInput.files || [], function (file)
                {
                    totalBytes += file.size || 0;
                });
            });

            if (totalBytes > effectiveLimit)
            {
                event.preventDefault();
                window.alert(<?= json_encode(__('flash.upload_request_too_large'), JSON_UNESCAPED_UNICODE) ?>);
            }
        });

        document.querySelectorAll('details.ticket-card[open]').forEach(function (ticketCard)
        {
            hydrateTicketThumbnails(ticketCard);
        });

        document.addEventListener('toggle', function (event)
        {
            var ticketCard = event.target;
            if (!ticketCard || !ticketCard.matches || !ticketCard.matches('details.ticket-card'))
            {
                return;
            }

            hydrateTicketThumbnails(ticketCard);
        }, true);

        var openFilePreview = function (attachmentId)
        {
            if (!attachmentId)
            {
                return;
            }

            if (!previewModal)
            {
                previewModal = document.createElement('div');
                previewModal.className = 'file-preview-modal';
                previewModal.setAttribute('aria-hidden', 'true');

                var modalContent = document.createElement('div');
                modalContent.className = 'file-preview-content';
                modalContent.setAttribute('role', 'dialog');
                modalContent.setAttribute('aria-modal', 'true');

                var closeBtn = document.createElement('button');
                closeBtn.innerHTML = '&times;';
                closeBtn.className = 'image-preview-close';
                closeBtn.setAttribute('data-file-preview-close', '1');
                closeBtn.setAttribute('aria-label', '<?= addslashes(__('ticket.preview_close')) ?>');
                closeBtn.addEventListener('click', closeFilePreview);

                previewIframe = document.createElement('iframe');
                previewIframe.className = 'file-preview-frame';
                previewIframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

                modalContent.appendChild(closeBtn);
                modalContent.appendChild(previewIframe);
                previewModal.appendChild(modalContent);
                document.body.appendChild(previewModal);
            }

            previewModal.classList.add('is-open');
            previewModal.setAttribute('aria-hidden', 'false');
            document.documentElement.style.overflow = 'hidden';
            if (previewIframe)
            {
                previewIframe.src = 'preview.php?id=' + encodeURIComponent(attachmentId);
            }
        };

        var closeFilePreview = function ()
        {
            if (previewModal)
            {
                previewModal.classList.remove('is-open');
                previewModal.setAttribute('aria-hidden', 'true');
                document.documentElement.style.overflow = '';
                if (previewIframe)
                {
                    window.setTimeout(function ()
                    {
                        if (!previewModal.classList.contains('is-open'))
                        {
                            previewIframe.src = '';
                        }
                    }, 200);
                }
            }
        };

        document.addEventListener('click', function (event)
        {
            var filePreviewTrigger = event.target.closest('[data-file-preview-trigger], [data-file-thumb-open]');
            if (filePreviewTrigger)
            {
                event.preventDefault();
                openFilePreview(filePreviewTrigger.getAttribute('data-preview-id'));
                return;
            }

            if (previewModal && event.target === previewModal)
            {
                event.preventDefault();
                closeFilePreview();
            }
        });

        document.addEventListener('keydown', function (e)
        {
            if (e.key === 'Escape' && previewModal && previewModal.classList.contains('is-open'))
            {
                closeFilePreview();
            }
        });

        /**
         * Key picker popup
         */
        var KEY_PICKER_GROUPS = [
            {
                label: 'Modifiers',
                keys: [
                    { token: 'ctrl', label: 'Ctrl' },
                    { token: 'alt', label: 'Alt' },
                    { token: 'shift', label: 'Shift' },
                    { token: 'win', label: '', icon: 'windows' },
                    { token: 'altgr', label: 'Alt Gr' },
                    { token: 'fn', label: 'Fn' }
                ]
            },
            {
                label: 'Navigatie',
                keys: [
                    { token: 'esc', label: 'Esc' },
                    { token: 'tab', label: 'Tab' },
                    { token: 'caps', label: 'Caps Lock' },
                    { token: 'enter', label: 'Enter' },
                    { token: 'space', label: 'Space' },
                    { token: 'backspace', label: 'Backspace' },
                    { token: 'delete', label: 'Delete' },
                    { token: 'ins', label: 'Insert' },
                    { token: 'home', label: 'Home' },
                    { token: 'end', label: 'End' },
                    { token: 'pageup', label: 'Page Up' },
                    { token: 'pagedown', label: 'Page Down' }
                ]
            },
            {
                label: 'Pijltjes',
                keys: [
                    { token: 'up', label: '', icon: 'arrow-up' },
                    { token: 'down', label: '', icon: 'arrow-down' },
                    { token: 'left', label: '', icon: 'arrow-left' },
                    { token: 'right', label: '', icon: 'arrow-right' }
                ]
            },
            {
                label: 'Functietoetsen',
                keys: (function ()
                {
                    var rows = [];
                    for (var i = 1; i <= 12; i++) { rows.push({ token: 'f' + i, label: 'F' + i }); }
                    return rows;
                }())
            },
            {
                label: 'Systeem',
                keys: [
                    { token: 'prtsc', label: 'PrtSc' },
                    { token: 'scrolllock', label: 'Scroll Lock' },
                    { token: 'pause', label: 'Pause' },
                    { token: 'menu', label: 'Menu' },
                    { token: 'numlock', label: 'Num Lock' }
                ]
            },
            {
                label: 'Media',
                keys: [
                    { token: 'volup', label: 'Vol +' },
                    { token: 'voldown', label: 'Vol -' },
                    { token: 'mute', label: 'Mute' },
                    { token: 'playpause', label: 'Play/Pause' },
                    { token: 'nexttrack', label: 'Next' },
                    { token: 'previoustrack', label: 'Prev' }
                ]
            },
            {
                label: 'Symbolen',
                keys: [
                    { token: 'minus', label: '-' },
                    { token: 'equals', label: '=' },
                    { token: 'comma', label: ',' },
                    { token: 'period', label: '.' },
                    { token: 'slash', label: '/' },
                    { token: 'backslash', label: '\\' },
                    { token: 'semicolon', label: ';' },
                    { token: 'quote', label: "'" },
                    { token: 'backtick', label: '`' },
                    { token: 'lbracket', label: '[' },
                    { token: 'rbracket', label: ']' }
                ]
            }
        ];

        var KEY_PICKER_BUTTON_ICONS = KEY_PICKER_ICONS;

        var buildKeyPickerPopup = function (popup)
        {
            var html = '';
            KEY_PICKER_GROUPS.forEach(function (group)
            {
                html += '<div class="key-picker-group">'
                    + '<div class="key-picker-group-label">' + group.label + '</div>'
                    + '<div class="key-picker-group-keys">';

                group.keys.forEach(function (key)
                {
                    var definition = getShortcutKeyDefinition(key.token);
                    var icon = key.icon || (definition ? definition.icon : '');
                    var label = definition ? definition.label : (key.label || '');
                    var inner = (KEY_PICKER_BUTTON_ICONS[icon || ''] || '') + (label ? escapeHtml(label) : '');
                    html += '<button type="button" class="key-picker-key" data-key-token="' + key.token + '">'
                        + inner + '</button>';
                });

                html += '</div></div>';
            });
            popup.innerHTML = html;
        };

        var initKeyPicker = function (wrapper)
        {
            var toggle = wrapper.querySelector('.key-picker-toggle');
            var popup = wrapper.querySelector('.key-picker-popup');
            var textarea = wrapper.querySelector('textarea');
            if (!toggle || !popup || !textarea) { return; }

            buildKeyPickerPopup(popup);

            toggle.addEventListener('click', function (e)
            {
                e.stopPropagation();
                var isOpen = !popup.hidden;
                popup.hidden = isOpen;
                toggle.classList.toggle('is-active', !isOpen);
            });

            popup.addEventListener('click', function (e)
            {
                var keyBtn = e.target.closest('.key-picker-key');
                if (!keyBtn) { return; }

                var token = keyBtn.getAttribute('data-key-token') || '';
                if (!token) { return; }

                var insertion = '[' + token + ']';

                if (typeof textarea.setRangeText === 'function')
                {
                    var start = textarea.selectionStart;
                    var end = textarea.selectionEnd;
                    textarea.setRangeText(insertion, start, end, 'end');
                }
                else
                {
                    textarea.value += insertion;
                }

                textarea.focus();
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                popup.hidden = true;
                toggle.classList.remove('is-active');
            });
        };

        var syncTextareaHeight = function (textarea)
        {
            if (!textarea)
            {
                return;
            }

            textarea.style.height = 'auto';
            textarea.style.height = String(textarea.scrollHeight) + 'px';
        };

        var initAutoGrowTextarea = function (textarea)
        {
            if (!textarea || textarea.dataset.autoGrowInit === '1')
            {
                return;
            }

            textarea.dataset.autoGrowInit = '1';
            textarea.addEventListener('input', function ()
            {
                syncTextareaHeight(textarea);
            });
            syncTextareaHeight(textarea);
        };

        var initTextareaWrapper = function (wrapper)
        {
            if (!wrapper)
            {
                return;
            }

            initKeyPicker(wrapper);
            initAutoGrowTextarea(wrapper.querySelector('textarea'));
        };

        document.querySelectorAll('.textarea-wrapper').forEach(initTextareaWrapper);

        document.addEventListener('click', function (e)
        {
            if (!e.target.closest('.textarea-wrapper'))
            {
                document.querySelectorAll('.key-picker-popup').forEach(function (p)
                {
                    p.hidden = true;
                });
                document.querySelectorAll('.key-picker-toggle.is-active').forEach(function (t)
                {
                    t.classList.remove('is-active');
                });
            }
        });
    });
</script>