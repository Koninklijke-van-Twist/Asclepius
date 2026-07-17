id: 2026-07-17-prefs-led-ticket-filters
date: 2026-07-17
title: Filters stay saved in your profile
author: Tim Falken

Ticket filters (status, category, assignee, search) are now stored in your user preferences instead of the URL. After saving a ticket, your filters remain. Filters only appear temporarily in the URL when you change them; afterwards the URL is cleaned again.

---
id: 2026-07-17-attachment-open-new-tab
date: 2026-07-17
title: Attachments open in a new tab
author: Tim Falken

Clicking an attachment name now opens the file in a **new tab**. The ticket page stays open. Modal previews are unchanged.

---
id: 2026-07-14-tickets-per-page-preference
date: 2026-07-14
title: Configurable tickets per page
author: Tim Falken

You can now choose how many tickets appear per page (5 to 100, default 20). The control sits to the right of **Reset filters** in the filter box, or in the same box on **My tickets**. Your choice is saved and applies everywhere: My tickets, ICT overview, and All tickets.

---
id: 2026-07-10-ticket-pagination-filters
date: 2026-07-10
title: Ticket pagination and saved filters
author: Tim Falken

Ticket lists now show a maximum of **20 tickets per page**, with page navigation above and below the list. Pagination links keep filters, search terms, and other URL parameters. After searching or filtering, pagination is recalculated based on the filtered results.

Saved filters load correctly again when you go directly to `admin.php` or return to the ICT overview or All tickets via the navigation menu.

---
id: 2026-07-08-translation-assignment-fixes
date: 2026-07-08
title: Translations and ticket assignment
author: Tim Falken

For translated tickets you now see only the translated text. The original is available via **Show original**, or while the translation is still loading.

Tickets created via the API (such as automatic access requests) are immediately assigned to an available ICT admin. Existing tickets without an assignee are silently auto-assigned when loading or searching.

---
id: 2026-07-08-ticket-ux-upload-fixes
date: 2026-07-08
title: Search, self-assignment, and uploads
author: Tim Falken

The ticket overview search field now refreshes in the background without reloading the page, so you can keep typing.

You can always assign a ticket to **yourself**, even if you are marked absent or category rules would otherwise block it.

Very large uploads (such as MP4) now show a clear error instead of breaking your session. Inline images load more reliably when several appear in one message.

---
id: 2026-07-08-all-tickets-tab
date: 2026-07-08
title: All tickets tab and private tickets
author: Tim Falken

Regular users now have a new **All tickets** tab showing resolved tickets. Tickets there are read-only: you can view them but cannot post messages or change details. The overview has the same filters as the ICT overview (category, search, assignee).

ICT admins can mark a ticket as **Private** in the ICT overview via a checkbox on the ticket. Private tickets never appear in the All tickets tab.

On the ICT overview, All tickets, and My tickets pages, a 🔗 icon appears to the left of the ticket number. It always copies the same link (`index.php?open=…`). When opened, you land in the right place: **your own tickets** in My tickets, **admins** otherwise in the ICT overview, **other users** on completed public tickets in All tickets.

---
id: 2026-06-23-message-textarea-grow
date: 2026-06-23
title: Message field grows with your text
author: Tim Falken

When creating a new ticket or replying to an existing one, the message field automatically gets taller as you type. You no longer need to scroll inside the box or resize it manually.

---
id: 2026-06-23-admin-ticket-improvements
date: 2026-06-23
title: Ticket management and statistics
author: Tim Falken

ICT admins can change a ticket title via a button at the top of the ticket, similar to changing the category. The cards for title, dates, priority, users, and category are more compact and arranged in a clearer grid.

The statistics page shows extra counters for tickets waiting (order, user, third party). In the per-requester table you can see how many tickets each person has submitted.

---
id: 2026-06-19-user-display-names
date: 2026-06-19
title: Names instead of email addresses
author: Tim Falken

Where possible, you now see a user's real name instead of their email address — for example on requesters, assignees, messages, and statistics. Hover to see the email address. Known names are cached locally so the overview stays fast.

---
id: 2026-06-19-changelog-tab
date: 2026-06-19
title: Changelog tab
author: Tim Falken

Admins can see what's new in Asclepius. Unread updates are collapsed; open an item to read it. Read items can be shown again via the button at the bottom.

---
id: 2026-06-19-attachments
date: 2026-06-19
title: Attachments in messages
author: Tim Falken

You can now remove attachments before sending a message. Clipboard images are placed in the message text automatically; other files can be inserted with a button. Embedded attachments appear as their own block in the text.

---
id: 2026-06-19-admin-preferences
date: 2026-06-19
title: Email preferences and new status
author: Tim Falken

ICT admins can choose which events trigger an email. A new status "Awaiting third party" was added for tickets waiting on an external party.

---
id: 2026-06-18-category-change
date: 2026-06-18
title: Change ticket category
author: Tim Falken

Admins can change the category of an existing ticket, with optional reassignment to another handler. The requester and any new assignee receive a notification.

---
id: 2026-06-17-performance
date: 2026-06-17
title: Faster ticket overview
author: Tim Falken

Loading and refreshing large ticket lists is optimized: messages load only when expanded, polling sends less data, and the database uses more efficient queries.

---
id: 2026-06-16-session-uploads
date: 2026-06-16
title: Longer sessions and better uploads
author: Tim Falken

Sessions stay active longer while working on tickets. Multiple attachments are no longer overwritten when selecting files again, and the session is checked before submitting forms.

---
id: 2026-05-13-ticket-search
date: 2026-05-13
title: Ticket search
author: Omer Pesket

The ICT overview now has a search field to filter tickets by title, requester, and other fields.

---
id: 2026-05-07-translations
date: 2026-05-07
title: Automatic translation
author: Tim Falken

Ticket messages can be translated automatically into the reader's language. Support for multiple translation providers has been prepared.

---
id: 2026-05-05-template-tickets
date: 2026-05-05
title: Template tickets and checkboxes
author: Tim Falken

Template tickets make it easier to create standard requests. Messages support interactive checkboxes. Categories on the settings page can be reordered.

---
id: 2026-05-05-timezone
date: 2026-05-05
title: Timezone and dates
author: Omer Pesket

Dates and times in the application and API now consistently follow the configured timezone.

---
id: 2026-04-30-multi-user-keys
date: 2026-04-30
title: Multiple participants and keyboard symbols
author: Tim Falken

Tickets can have multiple participants. Text fields support quick insertion of keyboard keys and special symbols via a picker menu.

---
id: 2026-04-29-file-previews
date: 2026-04-29
title: Attachment previews
author: Tim Falken

Images and various file types can be viewed directly in the ticket without downloading, including thumbnails and document previews.
