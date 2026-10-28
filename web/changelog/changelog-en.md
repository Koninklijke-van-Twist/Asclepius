id: 2026-10-27-grok-default-webhook-optional
date: 2026-10-27
title: Default Grok bot alongside your own webhook
author: Cursor Agent

Under your own Grok webhook in **Preferences** there is a checkbox: *Also keep using the default Grok bot webhook alongside my own webhook.* It can only be changed with a valid webhook and send key.

When it is off, tickets assigned to you notify only your webhook, not the default Grok bot. When it is on, both are notified, as before. With your own webhook the checkbox is off by default, including if you already had a webhook. Tickets of others or without an assignee still go to the default Grok bot.

For **AI advice**, your checkbox counts when you request the advice, also on someone else's ticket: when it is off, the advice goes to your own bot and not to the default Grok bot. The assignee's checkbox does not count then. Without your own webhook, AI advice goes to the default Grok bot.

---
id: 2026-10-21-attachment-image-previews
date: 2026-10-21
title: Image attachment previews
author: Cursor Agent

An image attached to a ticket message shows a preview again, including images added through the attachments API. If there is no thumbnail, the original file is shown. Other file types keep their existing icon.

---
id: 2026-10-16-ghost-code-background
date: 2026-10-16
title: A code block in a ghost message is one panel
author: Cursor Agent

A code block in a ghost message has one background, not a white patch on every line. The code colours stay readable. In a normal message a code block looks the same as before.

---
id: 2026-10-16-close-public-data
date: 2026-10-16
title: Database and attachments are no longer public
author: Cursor Agent

The database, ticket attachments and temporary cache files can no longer be opened or browsed without signing in. An image or other attachment in a ticket stays visible while you are signed in. The cache indicator no longer shows sample data.

---
id: 2026-10-16-pasted-screenshot-visible
date: 2026-10-16
title: Pasted screenshots stay visible
author: Cursor Agent

A screenshot pasted into a message with Ctrl+V stays in the message after you post it. That still holds when the message is translated afterwards. If the image was already stored with the message but disappeared after a translation, it shows again. The screenshot also stays visible when a translation places the image in the middle of a sentence. When two screenshots end up directly after each other on one line, that leftover text disappears and both images stay visible. The image itself is not uploaded again.

---
id: 2026-10-16-duplicate-code-blocks
date: 2026-10-16
title: The same code block stays twice
author: Cursor Agent

A message with two identical code blocks keeps both in the translation, in the ticket, and in mail. That still holds when the translation dropped one of them.

---
id: 2026-10-16-code-highlight
date: 2026-10-16
title: Colour in code blocks
author: Cursor Agent

A code block in a ticket message is coloured when you name the language, for example `php`, `sql`, `js` or `powershell`. That also applies when you view the translation or the original, and when the message is loaded again later. The code itself is not translated. A block without a language stays plain text. AL (Business Central) has no colouring and stays plain text too.

---
id: 2026-10-16-search-by-date
date: 2026-10-16
title: Search the ticket list by date
author: Cursor Agent

The search field also accepts a date, in the overview and in admin. It matches the day a ticket was created, the last change, and the date of a visible message.

You can enter 16-10-2026, 16/10/2026, 16-10, 16 okt, 16 oktober 2026, 2026-10-16, okt 2026, oktober 2026, or just 2026. Without a year, the current year is used. A word and a date together, such as printer 16-10, must both match. A date that cannot exist, such as 31 februari 2026 or 16 oktober 1999, returns no tickets.

---
id: 2026-10-16-filter-chip-double-click
date: 2026-10-16
title: Double-click keeps one status or category
author: Cursor Agent

Double-click or double-tap a status or category in the ticket filters. Only that choice stays on; the other choices in the same group turn off. A single click still turns a choice on or off. The list and the saved filters follow that choice straight away, including on the admin overview.

If you then open a ticket, follow a link, or choose Reset filters, that action stays. The filter click is not applied afterwards. Scrolling, or a touch you release without clicking, does not do that: the filter choice is still applied. Changing the search field does not leave a filter looking on while the list still uses the previous choice.

---
id: 2026-10-14-translation-cache-i18n
date: 2026-10-14
title: Translations show again in every language
author: Cursor Agent

A new message is translated into English, French and German again, not only into Dutch. The translation appears on its own. A translation that was already stored stays in place.

On My tickets the title translation showed quickly, while the messages in the opened ticket stayed on the loading icon. Opening a ticket now requests those messages. If a translation is already running, the opened ticket waits briefly and continues on its own when that run has stalled.

A new reply in an existing ticket stayed on the loading icon when the background translation had already claimed that text but had not stored it. The opened ticket then finishes that translation itself. The list stays fast and shows the title. A collapsed ticket in the list does not wait for that, even when the title still shows a loading icon.

The buttons to show the original or the translation, the Janus presence status and the Printerproblemen category follow the language you have chosen.

id: 2026-10-14-update-mail-language
date: 2026-10-14
title: Update email in the recipient's language
author: Cursor Agent

An update email to a user follows the language that person has set, whether the message comes from the web interface or from the API (including a bot). An internal draft is only emailed once it becomes visible. The same applies to the confirmation of a new ticket, including a ticket created through the API without a separate action. The subject, the fixed texts and the message itself are in that language. Mail to ICT, an administrator or the assigned handler uses that person's language, not the language of the requester. Recipients in different languages each get their own email and wait for the translation in their language. Recipients who share a language share one email. A status line in the email, including one after a reply, is in the recipient's language. Switching off an ICT email still leaves the desktop notification and the web push for that notification in place. An escalation email or an email to the assignee still goes out when another email about the same message is already waiting. The footnote that someone is away is also in the substitute's email, in their language.

If the message is already in that language, or the translation is already ready, the email goes out at once. Otherwise it waits until the translation arrives. If that takes longer than three minutes, the email is sent anyway: fixed texts in the right language, the message in the original. That is logged. If the message changes before the email has gone out, that email is dropped. The same email is sent only once.

---
id: 2026-10-14-translate-on-post
date: 2026-10-14
title: Translation starts with a new message
author: Cursor Agent

A new ticket or a newly posted message is translated into every language right away. Posting itself does not wait for that. Existing messages are not translated afterwards in bulk; when you open a ticket you see a translation if it is already there, and otherwise it is still requested on demand.

When the Copilot flow returns several languages in one answer, all of them are kept. Without the flow, Lara still works one language at a time. The source language is the language that matches the original, not Dutch by default. A reader in that source language sees the original. If the original text comes back in more than one language, none of those is chosen as the source language. Text that matches the original does not count as a translation. A status line, such as a status change, is not sent to the translation service. It appears in your language, with the same small formatting as the original.

If you change a message or the title, the old translations are dropped and translated again. An internal draft is translated once it becomes visible.

---
id: 2026-10-13-copilot-translate
date: 2026-10-13
title: Translation via the Copilot flow
author: Cursor Agent

Ticket translation uses the Copilot flow once that is set in the configuration. The reader's language, when a text is translated, and what is stored stay the same. One text can take a couple of minutes. If translation fails, the original text stays visible. If time runs out while a ticket is being translated, the texts that already succeeded stay and the loading icon remains on the rest. That rest is requested again on its own while this tab stays open and visible, including in a preview and when a new message arrives in the meantime, without reloading the page. After a series of attempts the loading icon goes away and the original text or the error stays, until you return to the tab, the list actually changes, or five minutes have passed. A single shared word does not cause a real translation to be discarded. Without a flow address, the previous translation service (Lara) stays active.

---
id: 2026-10-09-message-url-links
date: 2026-10-09
title: Links in messages stay intact
author: Tim Falken

A link containing a long number, such as a bol.com product link, was sometimes shown broken with bits of code in front of it. Links now stay intact, also with dashes, asterisks, underscores, parentheses or a full stop after them.

---
id: 2026-10-06-session-keepalive
date: 2026-10-06
title: Session stays signed in while Asclepius is open
author: Cursor Agent

While you keep Asclepius open, your login is refreshed every few minutes. A tab in the background does that more slowly. When you come back to the tab, it happens right away. If the session has expired anyway, a message shows the time and a button to sign in again. Text you were typing stays in that browser and comes back when you sign in with the same account. If someone else signs in on that tab, the text is discarded. Choose attachments again.

---
id: 2026-10-02-message-reactions
date: 2026-10-02
title: Thumbs up or down on messages
author: Tim Falken

On every message you can give 👍 or 👎: this was the right or the wrong answer. Clicking the same thumb again removes your vote; the other thumb moves it. Counts sit right after the emoji, for example 👍2 and 👎5. Your own choice lights up when you hover the message. Hover an emoji to see who reacted. No email or notification is sent.

---
id: 2026-10-02-ticket-participants-anyone
date: 2026-10-02
title: Anyone can manage users
author: Tim Falken

Anyone who can open a ticket can add and remove users. The last user always stays on the ticket.

---
id: 2026-10-02-attachment-download-name
date: 2026-10-02
title: Attachment download names
author: Tim Falken

An attachment downloads as the ticket number and requester, for example `42_jan.pdf`, instead of a long technical file name.

---
id: 2026-10-01-ghost-publish-edit
date: 2026-10-01
title: Edit a ghost message before publishing
author: Tim Falken

When you publish a ghost message, you can still change the text in the confirmation window. The edited text is what gets published and included in the update email.

---
id: 2026-09-30-api-response-hints
date: 2026-09-30
title: Recommendations in API responses
author: Tim Falken

A message posted through the API still succeeds when username, title, or email is missing and filled in with a default. The response then includes a `hints` list recommending that you send those three fields yourself.

If a status change through the API and a message through the API land on the same ticket within about a minute, in either order, an extra hint asks you to send the status in the same request as the message. When they are already in one request, that hint is left out.

---
id: 2026-09-29-personal-grok-webhook
date: 2026-09-29
title: Personal Grok webhook in Preferences
author: Tim Falken

In Preferences you can set your own webhook URL and send key, alongside the central Grok bot.

The central webhook still receives every notification. Your webhook receives the same notification as well when a ticket is assigned to you, or when you request AI advice. Replies through your webhook show your name and email address, with the title Assistent. The bot can still set the name and title itself.

A webhook URL must be http or https and cannot point at a local, private, or metadata address. That is checked when you save it and again just before it is sent.

---
id: 2026-09-28-mimir-odata-fallback
date: 2026-09-28
title: Business Central stays available when Mímir is down
author: Tim Falken

OData data is requested through Mímir first. If that call fails, Asclepius loads the same data directly from Business Central. The rest of that request skips Mímir, in the web app and in CLI scripts such as the nightly job.

Keep the Business Central credentials in `auth.php` next to the Mímir key. With no Mímir key, nothing changes.

A company that lives in another BC environment is loaded from that environment, with its own credentials. If those credentials are missing, credentials from another environment are not used. When listing companies, one unreachable environment does not block the others.

Every failure that comes from Mímir itself (connection, timeout, HTTP error, invalid response, or an error message) switches over, once per request. A URL that Asclepius itself cannot translate does not.

---
id: 2026-09-23-webhook-user-reply-reopen
date: 2026-09-23
title: Webhook on user reply and reopened ticket
author: Tim Falken

The outbound webhook to ICT now also fires in two extra situations.

- When a ticket is **awaiting the user** and the requester then posts a message, a webhook follows with `type: user-reply`. In the overview that reply sets the status back to **in progress**; the notification is still sent.
- When a ticket moves from **Resolved** to any other status, a webhook follows with `type: ticket-reopened`.

Webhooks for a new ticket and for resolved stay the same.

---
id: 2026-09-22-publish-ghost-message
date: 2026-09-22
title: Publish ghost messages for admins
author: Tim Falken

Admins see an icon (👻 with a prohibition mark) on the top-right of a **ghost message**. Use it to take the message out of ghost mode. After confirmation it becomes visible to the user, who receives the same update email as for a newly posted message.

---
id: 2026-09-18-waiting-user-mail-priority
date: 2026-09-18
title: High priority when waiting on the requester
author: Tim Falken

When ICT sets a ticket to **Awaiting user**, the update email to the requester is marked high priority in the inbox. Other emails stay normal.

---
id: 2026-09-18-api-message-status-assignee
date: 2026-09-18
title: Message, status and assignee in one API call
author: Tim Falken

Bots such as ICT-Bot can optionally include a new **status** and/or **assignee** on `add_ticket_message`. This matches the ICT reply form: one request, the same rights and checks, and a system note when the status changes. Sending only a message is unchanged; the separate `change_*` actions still exist.

---
id: 2026-09-15-api-ticket-mutation-hardening
date: 2026-09-15
title: Stricter checks when changing tickets via the API
author: Tim Falken

API changes to **status**, **assignee**, **priority**, and **due date** now rely only on a valid service key, webhook key, or the session’s ICT rights. A `user_is_admin` flag in the request body no longer grants extra access. Priority must be a whole number **0**, **1**, or **2**; the due date must be a real calendar date (`YYYY-MM-DD`, so 31 February is rejected).
When ICT marks a ticket **resolved**, Save first asks for the technical solution. That note is optional and is stored as an internal (ghost) message for colleagues. Under **Preferences** you can turn that prompt off; you then get a reminder to document the technical solution in the ticket yourself.

---
id: 2026-09-15-api-change-ticket-status
date: 2026-09-15
title: Ticket status, assignment and priority via the API
author: Tim Falken

Bots and integrations (such as ICT-Bot) can now change a ticket’s **status** through the HTTP API, for example to **in behandeling** or **resolved**. This matches the ICT overview: built-in statuses, custom labels, a system note, and notifications. You can also update the **assignee**, **priority**, and **due date** via the API.

---
id: 2026-09-11-ticket-message-markdown
date: 2026-09-11
title: Markdown in ticket messages
author: Tim Falken

Ticket message text (including ghost messages from bots such as ICT-Bot) now renders **Markdown**: bold, italic, headings, lists, tables, links, code, and quotes. Ordinary plain text keeps its line breaks. Inline attachments and keyboard icons still work as before.

---
id: 2026-09-11-grok-webhook-ticket-solved
date: 2026-09-11
title: Webhook when a ticket is resolved
author: Tim Falken

The Grok webhook for a **new ticket** now also sends `type: new-ticket`. When a ticket is marked **resolved**, the same webhook is called with `type: ticket-solved`.

---
id: 2026-09-10-ticket-url-links
date: 2026-09-10
title: Ticket links in messages
author: Tim Falken

A URL to another ticket (such as `index.php?open=943`) becomes a clickable **Ticket #943** link in the message text.

---
id: 2026-09-10-grok-ticket-webhook
date: 2026-09-10
title: Webhook on new tickets; the bot sets its name and title
author: Tim Falken

Every **new ticket** can trigger a webhook with the ticket number and a **one-hour API key**. The bot secret goes in the `Authorization: Bearer` header. The bot replies through the API, sets its own **display name** and **blue function title**, and can list built-in categories and statuses. The hourly snapshot is unchanged.

---
id: 2026-09-10-public-api-docs
date: 2026-09-10
title: API documentation for everyone
author: Tim Falken

A subtle **api** link in the bottom-right corner of every page opens the API documentation. That page is its own tab (shown in the header only while you are on it). The spec covers every API action, including reading tickets and posting a ghost-mode message. Bots and agents can fetch the raw Markdown at `docs/api.md`.

---
id: 2026-09-10-my-tickets-page
date: 2026-09-10
title: My tickets via your name in the header
author: Tim Falken

Clicking your **name** in the header opens the **My tickets** page. You see the same tickets as on **New ticket**, but instead of the create form you can filter by status, category, search term, and assigned ICT employee — like on the ICT overview. Your filters are remembered.

---
id: 2026-09-01-category-bc-verbeteringen
date: 2026-09-01
title: New category BC Verbeteringen
author: Tim Falken

A new ticket category **BC Verbeteringen** has been added, alongside **Business Central**. You can select it for new tickets, filters, and role assignments.

---
id: 2026-09-01-overview-filter-session-fix
date: 2026-09-01
title: ICT overview filters stay tied to your account
author: Tim Falken

Filters on the **ICT overview** are saved per account. When a session was missing or expired, the app could sometimes show another ICT admin’s filters by mistake. This is fixed: without a valid login, another user’s filters are no longer loaded, and saved filters are checked against your account.

---
id: 2026-08-21-user-profile-sidebar
date: 2026-08-21
title: User profile card in the overview sidebar
author: Tim Falken

On the **ICT overview**, click a user name in a ticket (requester, assignee, or message author). A card appears on the left with name, email, and short stats: ticket count, open tickets, average response time, and average wait time. The card stays open until you close it or open another user.

---
id: 2026-08-20-related-presence-narrow-layout
date: 2026-08-20
title: Related tickets and presence on narrow screens
author: Tim Falken

When the screen is too narrow to show **Possibly related** beside the form, that section moves under **New ticket**. The presence tracker then collapses behind a **≪** button; use **≫** to hide it again.

---
id: 2026-08-19-email-autocomplete-new-ticket-users
date: 2026-08-19
title: Email suggestions for new ticket and manage users
author: Tim Falken

On **New ticket** (for admins) and in **Manage users** on the ICT overview, typing now shows the same email suggestions as on the Roles page. This makes it faster to add known users without having to type or remember the full address.

---
id: 2026-08-19-tips-sidebar
date: 2026-08-19
title: Tip when uploading an attachment — paste screenshots with Ctrl+V
author: Tim Falken

When creating a new ticket, a tip appears on the right as soon as you upload a file attachment: you can also paste screenshots directly from your clipboard into the message text with **Ctrl+V**. The tip disappears automatically if you already do this, or you can permanently dismiss it with "Don't show again". This is the foundation of a generic tips system to which more contextual tips can be added in the future.

Additional smart tips now also help point to a better-fitting category, warn about setting the priority too high, and detect a possible duplicate open ticket from the same requester. Category suggestions also recognize uppercase abbreviations (like BC). On the **ICT overview**, an extra tip now appears when your reply mentions another admin or role member, so you can choose to post that coordination as a purple 👻 internal-only message.

In Preferences, a **"Show hidden tips again"** button at the bottom lets you re-enable all dismissed tips at once.

---
id: 2026-08-19-presence-ict-role-groups
date: 2026-08-19
title: Presence grouped by ICT and roles
author: Tim Falken

The presence sidebar now lists global ICT admins under **ICT**. If members of a role also appear in Janus, a separator with that role name is shown, followed by their presence. Other Janus users without a role or global admin rights are not shown.

---
id: 2026-08-19-open-ticket-related-sidebar
date: 2026-08-19
title: Linked tickets stay visible and related tickets when creating
author: Tim Falken

A ticket opened with `?open=` in the URL now stays on the page during silent refresh, even if it is outside your current filter or page. On **New ticket**, a left sidebar **Possibly related** appears when completed tickets in **All tickets** match your title or description. Click a title to view that ticket in a window without clearing what you already typed.

---
id: 2026-08-18-ticket-sort-preferences
date: 2026-08-18
title: Set your own ticket sorting
author: Tim Falken

Under **Preferences > Appearance**, you can now build your own ticket order with multiple sorting rules. Rules can be dragged, added, removed with confirmation, and reset to the default order. The preview on the right now uses a larger set of more than ten tickets, including extra resolved examples, so you can immediately see what your chosen sorting does. The default order is now **Open first**, **High priority first**, then **Stale first** and **Lowest number first**. **Ticket age** puts oldest or newest tickets first; **Status** follows workflow order. Each option also has a short explanation.

---
id: 2026-08-13-open-trend-hover
date: 2026-08-13
title: Trend chart: value on hover
author: Tim Falken

In the **Open tickets over time** chart, fixed dots on the lines are gone. On hover (or touch), only the nearest point shows a dot with the current count.

---
id: 2026-08-13-hourly-open-tickets-trend
date: 2026-08-13
title: Open-tickets chart by the hour
author: Tim Falken

The **ICT stats** trend chart now uses **hourly** snapshots (`hourly.php`) instead of nightly ones. Unchanged hours are not stored again; the line fills those hours with the last known value. The axis still shows days, with hourly detail in the line. `nightly.php` now only handles the tea question.

---
id: 2026-08-05-date-format
date: 2026-08-05
title: Dates as 15 Jun 2026
author: Tim Falken

Dates on the ticketing pages (created/updated, due date, chart axes, holiday details) now use the readable format **15 Jun 2026** (with time where relevant: **15 Jun 2026 14:30**), in the interface language.

---
id: 2026-08-05-open-tickets-trend
date: 2026-08-13
title: Open tickets over time by category
author: Tim Falken

**ICT stats** has a subtab **Open tickets over time**: a sharp SVG chart of open tickets per category. By default you see the past month through today; the date pickers let you change the range live. Click a category in the legend to show or hide that line. Snapshots are stored **hourly** when open-ticket counts per category change (`hourly.php`).

---
id: 2026-08-05-ticket-appearance-prefs
date: 2026-08-05
title: Preferences with appearance
author: Tim Falken

The **Email preferences** tab is now called **Preferences**. Email notifications remain at the top; below that, **Appearance** lets you choose how tickets look: priority dots, time open, top border color (status/assignee/priority/category), and whether closed tickets look subtler. Preview tickets on the right update live; the choices apply everywhere in the system.

---
id: 2026-08-04-ict-roles-afas
date: 2026-08-04
title: ICT roles and AFAS category
author: Tim Falken

There is a new ticket category **AFAS**. Full ICT admins can also manage limited roles under **Roles**: a role name, linked categories, and members (email addresses).

Role members only see tickets in their role's categories, with navigation like `<role>-overview` and `<role>-stats`. Under Settings they manage vacation and auto-assignment for their role categories. Assignee choices and notifications follow who is eligible for that category. Ticket-number search and ticket links respect the same category access. A user can belong to **one role** only; when adding members you get suggestions from the known user list. Live refreshes (stats/ticket poll) stay within the same role restrictions. Changing a ticket to a category outside your role shows a warning and forces reassignment. Limited ICT users also keep the **All tickets** tab (same rights as a regular user); full ICT admins do not need it. The **Assigned to** filter also correctly remembers role members (not only full admins).

---
id: 2026-08-04-priority-edge-markers
date: 2026-08-04
title: Priority dots on open tickets
author: Tim Falken

On the **ICT overview**, open tickets with priority 1 or 2 show a dot on the left edge: orange with `!` for priority 1, and blinking red with `!!` for priority 2.

---
id: 2026-08-04-ticket-sort-oldest-first
date: 2026-08-04
title: Older open tickets first
author: Tim Falken

Open tickets still come first (unresolved before resolved), then by priority (high to low). Within the same priority, **older tickets now appear before newer ones**, so long-running tickets show up earlier in the list.

---
id: 2026-08-04-presence-sidebar
date: 2026-08-04
title: Presence via Janus
author: Tim Falken

A **presence overview** on the side now shows who is in the office or at home today, or away, sick, or on vacation. The data comes from [Janus](../janus/) and only includes colleagues who use the **full hours tracker** there.

Not listed yet (as an ICT admin)? Click the overview for an explanation, or open [Janus](../janus/), turn on the full hours tracker, and keep using it — you will then appear automatically.

---
id: 2026-07-22-ghost-messages
date: 2026-07-22
title: Ghost messages on ICT overview
author: Tim Falken

On the **ICT overview** you can enable ghost mode when replying (ghost button next to the keyboard). Those messages are only visible to ICT on that overview, with purple styling and a wavy border. Status changes and other system notes remain normal messages.

---
id: 2026-07-22-custom-ticket-statuses
date: 2026-07-22
title: Custom ticket statuses
author: Tim Falken

As an ICT admin you can click **Other** when changing a ticket status and enter a custom status name. That status appears in the status dropdown and in the filter chips while any tickets use it. A filter for a status you created is enabled by default. Hovering a custom filter chip shows who created it. Different capitalization is merged into one status, and the color is derived from the name.

---
id: 2026-07-21-exact-ticket-number-search
date: 2026-07-21
title: Ticket number search ignores filters
author: Tim Falken

When you type a **ticket number** in the search box (for example `42` or `#42`), that ticket always appears in the results — even if it does not match the active status, category, or assignee filters.

---
id: 2026-07-17-api-user-names
date: 2026-07-17
title: User names in API responses
author: Tim Falken

API responses that include a user email now also include the matching **display name** (from the Graph user directory). Participant lists include a `participants` array with email and name.

---
id: 2026-07-17-api-docs
date: 2026-07-17
title: API documentation in Settings
author: Tim Falken

At the bottom of **Settings** there is an **API** button. It opens the Asclepius API documentation (authentication, endpoints, and examples) inside the app.

---
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
