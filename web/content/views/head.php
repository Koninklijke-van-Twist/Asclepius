<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Asclepius - ICT tickets</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --panel: #ffffff;
            --text: #10233f;
            --muted: #5b6b82;
            --line: #d8e0eb;
            --accent: #0b65c2;
            --accent-soft: #e8f1fb;
            --danger: #b42318;
            --danger-soft: #fee4e2;
            --success: #067647;
            --success-soft: #d1fadf;
            --shadow: 0 16px 40px rgba(15, 35, 63, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        [hidden] {
            display: none !important;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        body.has-update-notify {
            padding-top: 56px;
        }

        .update-notify-banner {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10000;
            height: 56px;
            overflow: hidden;
        }

        .update-notify-strip {
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(-45deg,
                    #f7c600 0px,
                    #f7c600 24px,
                    #111 24px,
                    #111 48px);
            background-size: 68px 68px;
            animation: update-notify-scroll 1.2s linear infinite;
        }

        @keyframes update-notify-scroll {
            from {
                background-position: 0 0;
            }

            to {
                background-position: 68px 0;
            }
        }

        .update-notify-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
        }

        .update-notify-box {
            background: #b42318;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 14px;
            line-height: 1.35;
            text-align: center;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        }

        .admin-grid-full {
            grid-column: 1 / -1;
        }

        a {
            color: var(--accent);
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px;
        }

        .hero {
            background: linear-gradient(135deg, #0e2c52, #0b65c2);
            color: #fff;
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: relative;
        }

        /* Lang switcher */
        .lang-switcher {
            position: absolute;
            top: 14px;
            right: 14px;
            z-index: 10;
        }

        .lang-current {
            width: 28px;
            height: 20px;
            border: none;
            background: none;
            padding: 0;
            cursor: pointer;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lang-current img {
            width: 28px;
            height: 20px;
            border-radius: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .lang-current:hover {
            opacity: 0.8;
        }

        .lang-dropdown {
            position: absolute;
            top: calc(100% + 6px);
            right: 0;
            list-style: none;
            margin: 0;
            padding: 8px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 6px;
            min-width: 80px;
            z-index: 999;
        }

        .lang-dropdown[hidden] {
            display: none !important;
        }

        .lang-option {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border-radius: 3px;
            padding: 4px;
            border: 2px solid transparent;
            cursor: pointer;
        }

        .lang-option:hover {
            background: rgba(0, 0, 0, 0.06);
        }

        .lang-option.is-active {
            border-color: #007bff;
        }

        .lang-option img {
            width: 28px;
            height: 20px;
            border-radius: 2px;
            display: block;
        }

        .brand {
            display: flex;
            gap: 14px;
            align-items: center;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            object-fit: contain;
            padding: 8px;
        }

        .eyebrow {
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 12px;
            opacity: 0.8;
        }

        .hero h1 {
            margin: 0;
            font-size: 28px;
        }

        .hero p {
            margin: 6px 0 0;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .user-chip,
        .nav-link,
        .status-pill,
        .assignee-badge,
        .count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .user-chip {
            background: rgba(255, 255, 255, 0.16);
        }

        .nav-link {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-link.active {
            background: #fff;
            color: var(--accent);
        }

        .nav-link.changelog-nav-link.has-unread-changelog {
            animation: changelog-nav-pulse 1.8s ease-in-out infinite;
        }

        .nav-link.changelog-nav-link.has-unread-changelog.active {
            animation: changelog-nav-pulse-active 1.8s ease-in-out infinite;
        }

        @keyframes changelog-nav-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(147, 197, 253, 0);
            }

            50% {
                box-shadow: 0 0 0 4px rgba(147, 197, 253, 0.55);
            }
        }

        @keyframes changelog-nav-pulse-active {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(11, 101, 194, 0);
            }

            50% {
                box-shadow: 0 0 0 4px rgba(11, 101, 194, 0.35);
            }
        }

        .flash-stack {
            margin: 16px 0;
            display: grid;
            gap: 10px;
        }

        .flash {
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            box-shadow: var(--shadow);
        }

        .flash.success {
            background: var(--success-soft);
            color: var(--success);
        }

        .flash.error {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .layout {
            display: grid;
            gap: 16px;
            margin-top: 16px;
        }

        .panel {
            background: var(--panel);
            border-radius: 18px;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .panel h2 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .panel-intro {
            margin-top: 0;
            color: var(--muted);
            font-size: 14px;
        }

        .dev-note {
            margin-top: 8px;
            font-size: 13px;
            color: #dbeafe;
        }

        .form-grid {
            display: grid;
            gap: 12px;
        }

        .form-grid.two-columns,
        .admin-grid,
        .meta-grid {
            grid-template-columns: 1fr;
        }

        label {
            display: grid;
            gap: 6px;
            font-weight: 700;
            font-size: 14px;
        }

        input[type="text"],
        input[type="search"],
        select,
        textarea {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 11px 12px;
            font: inherit;
            background: #fff;
            color: var(--text);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input[type="file"] {
            font: inherit;
        }

        .hint {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
        }

        .translation-toggle-button {
            border: 1px solid var(--line);
            background: transparent;
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 999px;
            cursor: pointer;
            line-height: 1.2;
        }

        .translation-toggle-button:hover {
            color: var(--accent);
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .translation-status-indicator {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            margin-left: auto;
            position: relative;
            opacity: 0.72;
        }

        .translation-flag-ghost {
            font-size: 12px;
            line-height: 1;
            filter: saturate(0.4);
            opacity: 0.6;
            transform: translateY(0.5px);
            user-select: none;
        }

        .translation-spinner-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 1.5px solid rgba(145, 162, 181, 0.32);
            border-top-color: rgba(95, 117, 141, 0.62);
            animation: spin 1.05s linear infinite;
            pointer-events: none;
        }

        .translation-status-indicator[data-status="error"] {
            cursor: pointer;
            opacity: 0.78;
        }

        .translation-status-indicator[data-status="error"] .translation-flag-ghost {
            display: none;
        }

        .translation-status-indicator[data-status="error"] .translation-spinner-ring {
            display: none;
        }

        .translation-error-icon {
            width: 14px;
            height: 14px;
            color: #b34736;
        }

        .translation-status-indicator[data-status="error"]:hover {
            opacity: 1;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.2s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .modal-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 24px;
            color: #666;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: #000;
        }

        .modal-body {
            padding: 16px;
        }

        .modal-body p {
            margin: 0;
            font-size: 14px;
            color: #333;
            line-height: 1.6;
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .settings-actions-row {
            justify-content: space-between;
            align-items: center;
        }

        .settings-api-docs-button {
            margin-left: auto;
        }

        .api-docs-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .api-docs-body {
            display: grid;
            gap: 8px;
        }

        button,
        .secondary-button {
            border: 0;
            border-radius: 12px;
            padding: 11px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        button {
            background: var(--accent);
            color: #fff;
        }

        .secondary-button {
            background: var(--accent-soft);
            color: var(--accent);
        }

        .danger-button {
            background: var(--danger-soft);
            color: var(--danger);
        }

        .template-ticket-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .template-ticket-left,
        .template-ticket-right {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            background: #f8fbff;
        }

        .template-editor-form textarea {
            min-height: 180px;
        }

        .template-preview-rendered {
            margin-top: 8px;
            min-height: 60px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            padding: 10px;
            display: grid;
            gap: 6px;
            color: var(--text);
            font-size: 14px;
            line-height: 1.4;
        }

        .template-preview-line {
            white-space: pre-wrap;
            word-break: break-word;
        }

        .template-preview-checkbox-line {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .template-preview-checkbox-line input {
            margin-top: 2px;
        }

        .template-preview-rendered .shortcut-key {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            box-sizing: border-box;
            height: 22px;
            padding: 0 7px;
            min-width: 24px;
            border: 1px solid #b7c1d0;
            border-bottom-width: 2px;
            border-radius: 6px;
            background: #fff;
            color: #132238;
            font-size: 0.86em;
            font-weight: 600;
            line-height: 1;
            vertical-align: middle;
            white-space: nowrap;
        }

        .template-fragment-list {
            display: grid;
            gap: 8px;
            margin-top: 10px;
        }

        .template-fragment-item {
            display: grid;
            grid-template-columns: auto auto 1fr auto;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 10px;
            background: #fff;
            cursor: default;
            user-select: none;
        }

        .template-fragment-item[draggable="true"] {
            cursor: grab;
        }

        .template-fragment-item.is-dragging {
            opacity: 0.4;
        }

        .template-fragment-item.drag-over {
            border-color: var(--accent);
            background: var(--accent-soft);
        }

        .template-fragment-name {
            font-weight: 700;
            font-size: 14px;
        }

        .template-drag-handle {
            color: var(--muted);
            font-size: 18px;
            line-height: 1;
            cursor: grab;
            padding: 0 2px;
        }

        .template-ticket-right-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }

        .template-ticket-right-header h3 {
            margin: 0;
            font-size: 16px;
        }

        .template-fragment-modal {
            position: fixed;
            inset: 0;
            background: rgba(10, 25, 41, 0.64);
            display: grid;
            place-items: center;
            padding: 16px;
            z-index: 1700;
        }

        .template-fragment-modal[hidden] {
            display: none !important;
        }

        .template-fragment-modal-card {
            width: min(560px, 100%);
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--line);
            box-shadow: 0 18px 60px rgba(3, 13, 27, 0.26);
            padding: 18px;
            display: grid;
            gap: 14px;
        }

        .template-fragment-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .template-fragment-modal-head h3 {
            margin: 0;
            font-size: 16px;
        }

        .template-fragment-modal-card label {
            display: grid;
            gap: 4px;
            font-size: 14px;
            font-weight: 600;
        }

        .template-fragment-modal-card textarea {
            min-height: 140px;
            resize: vertical;
        }

        .filters-form {
            display: grid;
            gap: 12px;
            padding: 12px;
            background: #f8fbff;
            border-radius: 14px;
            border: 1px solid var(--line);
            margin-bottom: 14px;
        }

        .filters-form-compact {
            display: block;
        }

        .filters-toolbar-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 8px;
        }

        .filters-toolbar-row-end {
            justify-content: flex-end;
        }

        .tickets-per-page-control {
            display: inline-flex;
            min-width: 250px;
            align-items: center;
            gap: 8px;
            margin: 0;
            font-weight: 600;
            color: var(--ink);
        }

        .tickets-per-page-control select {
            min-width: 72px;
            max-width: 100px;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 8px 10px;
            font: inherit;
            background: #fff;
        }

        .ticket-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin: 14px 0;
        }

        .ticket-pagination-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            font-weight: 600;
            line-height: 1;
        }

        .ticket-pagination-button:hover {
            background: #f0f6ff;
            border-color: #9db7df;
        }

        .ticket-pagination-button.is-current {
            background: var(--accent);
            border-color: var(--accent);
            color: #fff;
        }

        .ticket-pagination-button.is-disabled {
            opacity: 0.45;
            pointer-events: none;
        }

        .ticket-pagination-ellipsis {
            min-width: 24px;
            text-align: center;
            color: #5b6b82;
            font-weight: 700;
        }

        .stats-layout {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .stats-main {
            flex: 1 1 0;
            min-width: 0;
        }

        .stats-sidebar {
            flex: 0 0 25%;
            width: 25%;
            min-width: 200px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: var(--panel);
            padding: 12px;
        }

        .stats-sidebar h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .stats-ticket-item {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            gap: 6px;
            padding: 8px 10px;
            border-radius: 8px;
            border-left: 3px solid var(--ticket-color, #0b65c2);
            background: #f8fbff;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .stats-ticket-item .sti-body {
            flex: 1 1 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .stats-ticket-item .sti-title {
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .stats-ticket-item .sti-meta {
            color: var(--muted);
            font-size: 11px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sti-prio {
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            padding: 3px 6px;
            border-radius: 6px;
            margin-top: 1px;
        }

        .sti-prio-0 {
            color: #10233f;
            background: #e8ecf2;
        }

        .sti-prio-1 {
            color: #fff;
            background: #c2550b;
        }

        .sti-prio-2 {
            color: #fff;
            background: #cc0000;
            animation: prio2blink 0.5s step-start infinite;
        }

        @keyframes prio2blink {

            0%,
            100% {
                background: #cc0000;
            }

            50% {
                background: #000;
            }
        }

        .stats-grid {
            display: grid;
            gap: 12px;
            margin-bottom: 14px;
        }

        .stats-card {
            padding: 14px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: linear-gradient(135deg, #f8fbff, #eef4ff);
        }

        .stats-card strong {
            display: block;
            margin-top: 4px;
            font-size: 26px;
            color: var(--accent);
        }

        .stats-section-title {
            margin: 18px 0 8px;
            font-size: 16px;
        }

        .stats-note {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 13px;
        }

        /* Bigscreen alert overlay */
        #bigscreen-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #bigscreen-overlay.bs-phase1 {
            display: flex;
            background: #000;
        }

        #bigscreen-overlay.bs-phase2 {
            display: flex;
            background: #1a4ed8;
            color: #fff;
            text-align: center;
        }

        .bs-hazard-strip {
            position: absolute;
            left: 0;
            right: 0;
            height: 80px;
            background-image: repeating-linear-gradient(60deg,
                    #f7c600 0px,
                    #f7c600 40px,
                    #111 40px,
                    #111 80px);
            background-size: 93px 100%;
            overflow: hidden;
        }

        .bs-hazard-top {
            top: 0;
            animation: hazard-scroll-right 2s linear infinite;
        }

        .bs-hazard-bottom {
            bottom: 0;
            animation: hazard-scroll-left 2s linear infinite;
        }

        @keyframes hazard-scroll-right {
            from {
                background-position-x: 0;
            }

            to {
                background-position-x: 93px;
            }
        }

        @keyframes hazard-scroll-left {
            from {
                background-position-x: 0;
            }

            to {
                background-position-x: -93px;
            }
        }

        .bs-warning-emoji {
            font-size: min(40vw, 40vh);
            line-height: 1;
            z-index: 1;
            user-select: none;
        }

        .bs-max-prio-label {
            color: #f7c600;
            font-size: clamp(18px, 3vw, 36px);
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
            z-index: 1;
            margin-top: 8px;
            text-shadow: 0 2px 8px #000;
        }

        /* Linkerzijde update-kaarten */
        #bs-updates {
            position: fixed;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 260px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 100;
            pointer-events: none;
        }

        .bs-update-card {
            background: #0e2c52;
            color: #fff;
            border-left: 5px solid #f7c600;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: clamp(14px, 1.6vw, 20px);
            font-weight: 700;
            line-height: 1.3;
            animation: bs-card-in 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .bs-update-card .bsuc-sub {
            font-size: 0.7em;
            font-weight: 400;
            color: #aac4e8;
            margin-top: 4px;
        }

        @keyframes bs-card-in {
            from {
                opacity: 0;
                transform: translateX(-24px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .bs-ticket-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
            padding: 40px;
        }

        .bs-ticket-info .bs-headline {
            font-size: clamp(28px, 5vw, 56px);
            font-weight: 700;
            line-height: 1.2;
        }

        .bs-ticket-info .bs-title {
            font-size: clamp(22px, 4vw, 44px);
            font-weight: 600;
            line-height: 1.2;
            opacity: 0.9;
        }

        .bs-assignee-pill {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 40px;
            font-size: clamp(18px, 3vw, 36px);
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.01em;
        }


        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .checkbox-stack {
            display: grid;
            gap: 8px;
            margin-top: 4px;
        }

        .checkbox-line {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-weight: 600;
        }

        .checkbox-line input {
            margin-top: 3px;
        }

        .checkbox-line.flash-blue {
            animation: flashBlue 0.85s ease;
        }

        @keyframes flashBlue {
            0% {
                background: rgba(37, 99, 235, 0);
            }

            20% {
                background: rgba(37, 99, 235, 0.22);
            }

            100% {
                background: rgba(37, 99, 235, 0);
            }
        }

        .checkbox-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--status-color, #fff);
            border: 1px solid transparent;
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity 0.15s ease, filter 0.15s ease, transform 0.15s ease;
        }

        .checkbox-chip:hover {
            transform: translateY(-1px);
        }

        .checkbox-chip.is-inactive {
            opacity: 0.45;
            filter: saturate(0.65) brightness(1.2);
        }

        .checkbox-chip input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .checkbox-chip span {
            pointer-events: none;
        }

        .ticket-list {
            display: grid;
            gap: 12px;
        }

        .ticket-card {
            border-radius: 16px;
            scroll-margin-top: 24px;
            scroll-margin-bottom: 24px;
            background: #fff;
            border: 1px solid var(--line);
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(15, 35, 63, 0.04);
            border-top: 8px solid var(--ticket-color, #2563eb);
        }

        .ticket-card summary {
            list-style: none;
            cursor: pointer;
            padding: 14px;
        }

        .ticket-card summary::-webkit-details-marker {
            display: none;
        }

        .ticket-summary {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ticket-main-title {
            margin: 0;
            font-size: 18px;
        }

        .ticket-share-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            margin-right: 2px;
            padding: 0;
            border: 0;
            border-radius: 8px;
            background: transparent;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            vertical-align: middle;
            opacity: 0.72;
            transition: opacity 0.15s ease, background 0.15s ease, transform 0.15s ease;
        }

        .ticket-share-link:hover {
            opacity: 1;
            background: rgba(37, 99, 235, 0.1);
            transform: translateY(-1px);
        }

        .ticket-share-url-field {
            display: grid;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
        }

        .ticket-share-url-input {
            width: 100%;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
        }

        .ticket-share-modal-card {
            width: min(520px, 100%);
        }

        .ticket-subtitle {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            color: var(--muted);
            font-size: 13px;
        }

        .requester-multi {
            cursor: pointer;
            text-decoration: underline dotted;
            text-underline-offset: 2px;
        }

        .status-pill {
            color: #fff;
            background: var(--ticket-color, #2563eb);
            width: fit-content;
        }

        .assignee-badge {
            color: #fff;
            background: var(--assignee-color, #475569);
            width: fit-content;
        }

        .count-badge {
            background: #eef2f7;
            color: var(--muted);
            width: fit-content;
        }

        .private-ticket-toggle {
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .private-ticket-toggle input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
            margin: 0;
            padding: 0;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .private-ticket-pill {
            --ticket-color: #94a3b8;
            width: 92px;
            min-width: 92px;
            justify-content: center;
            opacity: 0.85;
            transition: opacity 0.15s ease, transform 0.15s ease, background 0.15s ease;
        }

        .private-ticket-toggle:hover .private-ticket-pill {
            opacity: 1;
            transform: translateY(-1px);
        }

        .private-ticket-toggle.is-active .private-ticket-pill {
            --ticket-color: #7c3aed;
            opacity: 1;
        }

        .ticket-body {
            padding: 0 14px 14px;
            display: grid;
            gap: 14px;
        }

        .meta-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
            padding: 8px 10px;
            border-radius: 10px;
            background: #f8fbff;
            border: 1px solid var(--line);
        }

        .meta-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            margin-bottom: 0;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .meta-item>span:not(.meta-label) {
            font-size: 13px;
            line-height: 1.35;
            word-break: break-word;
        }

        .meta-item [data-role="meta-title-value"] {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .meta-item select {
            padding: 6px 8px;
            border-radius: 8px;
            font-size: 13px;
        }

        .thread {
            display: grid;
            gap: 10px;
        }

        .message {
            border-radius: 14px;
            padding: 12px;
            border: 1px solid var(--line);
            background: #fbfdff;
        }

        .message.admin {
            background: #eff6ff;
        }

        .message.is-ghost {
            position: relative;
            overflow: hidden;
            background: #5b21b6;
            border-color: #ddd6fe;
            color: #f5f3ff;
        }

        .message.is-ghost::after {
            content: '';
            position: absolute;
            right: 48px;
            bottom: 6px;
            width: min(144px, 42%);
            height: min(162px, calc(100% - 12px));
            background: url('Ghost-watermark.svg') no-repeat center / contain;
            opacity: 0.22;
            pointer-events: none;
            z-index: 0;
        }

        .message.is-ghost > * {
            position: relative;
            z-index: 1;
        }

        .message.is-ghost .message-meta,
        .message.is-ghost .message-role,
        .message.is-ghost .message-text,
        .message.is-ghost .message-text a,
        .message.is-ghost .attachment-download-link,
        .message.is-ghost .hint {
            color: #f5f3ff;
        }

        .message.is-ghost .message-role {
            opacity: 0.92;
        }

        .message.is-ghost .message-text a {
            text-decoration-color: #ddd6fe;
        }

        .message-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 12px;
            align-items: center;
            margin-bottom: 6px;
            font-size: 13px;
        }

        .message-role {
            font-weight: 700;
            color: var(--accent);
        }

        .message-text {
            color: var(--text);
            line-height: 1.5;
            word-break: break-word;
            --shortcut-key-height: 22px;
        }

        .message-text a {
            color: var(--accent);
            text-decoration: underline;
        }

        .message-text small {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 0.82em;
        }

        .message-text .shortcut-key {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            box-sizing: border-box;
            height: var(--shortcut-key-height);
            padding: 0 7px;
            min-width: 24px;
            border: 1px solid #b7c1d0;
            border-bottom-width: 2px;
            border-radius: 6px;
            background: #fff;
            color: #132238;
            font-size: 0.86em;
            font-weight: 600;
            line-height: 1;
            vertical-align: middle;
            white-space: nowrap;
        }

        .message-text .shortcut-key-icon {
            width: 12px;
            height: 12px;
            display: block;
            flex: 0 0 auto;
        }

        .message-text .shortcut-plus {
            display: inline-block;
            padding: 0 4px;
            color: var(--muted);
            font-weight: 700;
        }

        .message-checkbox-line {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 2px 0;
        }

        .message-checkbox-line input {
            margin-top: 2px;
            flex: 0 0 auto;
            cursor: pointer;
        }

        .message-checkbox-line span {
            flex: 1 1 auto;
        }

        .attachment-list {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .draft-attachments-list {
            list-style: none;
            margin: 8px 0 0;
            padding: 0;
            display: grid;
            gap: 8px;
        }

        .draft-attachment-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 10px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #f8fbff;
        }

        .draft-attachment-name {
            flex: 1 1 auto;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 13px;
        }

        .draft-attachment-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .draft-attachment-insert,
        .draft-attachment-remove {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: #10233f;
            font-size: 12px;
            padding: 4px 8px;
            cursor: pointer;
        }

        .draft-attachment-remove {
            color: #b91c1c;
        }

        .draft-attachment-in-message {
            font-size: 12px;
            color: #0f766e;
        }

        .message-inline-attachment {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #f8fbff;
        }

        .message-inline-attachment-preview {
            display: block;
            max-width: 100%;
        }

        .message-inline-attachment-link {
            display: block;
            font-size: 12px;
            line-height: 1.4;
            color: var(--muted);
            text-decoration: none;
        }

        .message-inline-attachment-link:hover {
            color: #0b65c2;
            text-decoration: underline;
        }

        .attachment-inline-image {
            display: block;
            max-width: min(100%, 480px);
            max-height: 320px;
            width: auto;
            height: auto;
            border-radius: 8px;
            margin-bottom: 0;
        }

        .email-prefs-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 10px;
            max-width: 720px;
        }

        .email-prefs-label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
        }

        .email-prefs-label input {
            margin-top: 2px;
        }

        .changelog-toolbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .changelog-mark-all-button,
        .changelog-toggle-read-button {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fff;
            color: #10233f;
            font-size: 13px;
            padding: 8px 12px;
            cursor: pointer;
        }

        .changelog-unread-list,
        .changelog-read-list {
            display: grid;
            gap: 10px;
        }

        .changelog-read-section {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        .changelog-entry {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            overflow: hidden;
        }

        .changelog-entry.is-unread {
            border-color: #93c5fd;
            background: #f8fbff;
        }

        .changelog-entry.is-read {
            border-color: var(--line);
            background: #fff;
        }

        .changelog-entry-summary {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            cursor: pointer;
            list-style: none;
        }

        .changelog-entry-summary::-webkit-details-marker {
            display: none;
        }

        .changelog-entry-title {
            flex: 1 1 auto;
            font-weight: 600;
            color: #10233f;
        }

        .changelog-entry-date {
            font-size: 12px;
            color: var(--muted);
            white-space: nowrap;
        }

        .changelog-entry-badge {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #2563eb;
            flex: 0 0 auto;
        }

        .changelog-entry-body {
            padding: 0 14px 14px;
            color: #334155;
            font-size: 14px;
            line-height: 1.55;
        }

        .changelog-entry-body p {
            margin: 0 0 10px;
        }

        .changelog-body-list {
            margin: 0 0 10px;
            padding-left: 18px;
        }

        .changelog-body-list-ordered {
            list-style: decimal;
        }

        .changelog-entry-body code {
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 0.92em;
            background: #eef2f7;
            border-radius: 4px;
            padding: 1px 5px;
        }

        .api-docs-body .api-docs-code,
        .api-docs-body.changelog-entry-body .api-docs-code {
            margin: 4px 0 12px;
            padding: 0;
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #1e293b;
            background: #282c34;
            font-size: 0.92rem;
            line-height: 1.45;
        }

        .api-docs-body .api-docs-code code,
        .api-docs-body.changelog-entry-body .api-docs-code code {
            display: block;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            white-space: pre;
            background: transparent !important;
            padding: 12px 14px !important;
            border-radius: 0;
            font-size: inherit;
        }

        .api-docs-body .api-docs-code code.hljs {
            background: transparent;
        }

        .changelog-entry-body a {
            color: #0b65c2;
        }

        .changelog-heading {
            margin: 0 0 8px;
            color: #10233f;
        }

        .changelog-heading-1 {
            font-size: 1.05rem;
        }

        .changelog-heading-2 {
            font-size: 1rem;
        }

        .changelog-heading-3 {
            font-size: 0.95rem;
        }

        .changelog-entry-author {
            margin: 12px 0 0;
            padding-top: 10px;
            border-top: 1px solid var(--line);
            font-size: 12px;
            color: var(--muted);
        }

        .changelog-empty {
            margin: 0;
            color: var(--muted);
        }

        .changelog-feedback.is-error,
        .email-prefs-feedback.is-error {
            color: #b91c1c;
        }

        .changelog-feedback:not(.is-error),
        .email-prefs-feedback:not(.is-error) {
            color: #0f766e;
        }

        .changelog-footer-actions {
            margin-top: 16px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .attachment-thumb-button {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 2px;
            background: #fff;
            line-height: 0;
            cursor: zoom-in;
        }

        .attachment-thumb {
            display: block;
            width: 100px;
            max-width: 100px;
            max-height: 70px;
            object-fit: cover;
            border-radius: 8px;
        }

        .attachment-file-thumb-button {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 0;
            width: 100px;
            max-width: 100px;
            height: 70px;
            background: #f8fbff;
            cursor: pointer;
            overflow: hidden;
        }

        .attachment-file-thumb-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            pointer-events: none;
            background: #f8fbff;
        }

        .attachment-download-link {
            word-break: break-word;
        }

        .attachment-size {
            color: var(--muted);
        }

        .image-preview-modal {
            position: fixed;
            inset: 0;
            background: rgba(10, 25, 41, 0.72);
            display: grid;
            place-items: center;
            padding: 22px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 220ms ease;
            z-index: 1500;
        }

        .file-preview-modal {
            position: fixed;
            inset: 0;
            background: rgba(10, 25, 41, 0.72);
            display: grid;
            place-items: center;
            padding: 22px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 220ms ease;
            z-index: 1600;
        }

        .image-preview-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .file-preview-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .image-preview-content {
            position: relative;
            max-width: min(96vw, 1800px);
            max-height: min(92vh, 1200px);
            transform: translateY(20px) scale(0.88);
            opacity: 0;
            transition: transform 240ms ease, opacity 240ms ease;
        }

        .file-preview-content {
            position: relative;
            width: min(96vw, 1280px);
            height: min(92vh, 920px);
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 18px 60px rgba(3, 13, 27, 0.38);
            transform: translateY(20px) scale(0.96);
            opacity: 0;
            transition: transform 240ms ease, opacity 240ms ease;
            overflow: visible;
        }

        .image-preview-modal.is-open .image-preview-content {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .file-preview-modal.is-open .file-preview-content {
            transform: translateY(0) scale(1);
            opacity: 1;
        }

        .file-preview-frame {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
            background: #fff;
            border-radius: 12px;
        }

        .image-preview-full {
            display: block;
            max-width: min(96vw, 1800px);
            max-height: min(92vh, 1200px);
            width: auto;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 18px 60px rgba(3, 13, 27, 0.38);
            background: #fff;
        }

        .image-preview-close {
            position: absolute;
            top: -14px;
            right: -14px;
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 999px;
            background: #0d2238;
            color: #fff;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(3, 13, 27, 0.3);
        }

        .session-expired-modal {
            position: fixed;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(10, 25, 41, 0.64);
            opacity: 0;
            pointer-events: none;
            transition: opacity 180ms ease;
            z-index: 2000;
        }

        .session-expired-modal.is-open {
            opacity: 1;
            pointer-events: auto;
        }

        .session-expired-card {
            width: min(100%, 420px);
            background: #ffffff;
            border-radius: 18px;
            padding: 24px 22px;
            box-shadow: 0 18px 60px rgba(3, 13, 27, 0.24);
            border: 1px solid rgba(180, 35, 24, 0.16);
            text-align: center;
        }

        .session-expired-title {
            margin: 0;
            font-size: 22px;
            line-height: 1.3;
            color: var(--danger);
        }

        .session-expired-countdown {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 640px) {
            .image-preview-modal {
                padding: 10px;
            }

            .file-preview-modal {
                padding: 10px;
            }

            .image-preview-close {
                top: 8px;
                right: 8px;
            }

            .attachment-thumb {
                max-height: 64px;
            }

            .attachment-file-thumb-button {
                height: 64px;
            }
        }

        .reply-form {
            padding-top: 6px;
            border-top: 1px solid var(--line);
            display: grid;
            gap: 12px;
        }

        .textarea-wrapper {
            position: relative;
        }

        .textarea-wrapper textarea {
            padding-bottom: 34px;
            width: 100%;
            box-sizing: border-box;
            position: relative;
            z-index: 1;
            resize: none;
            overflow-y: hidden;
        }

        .textarea-wrapper.has-ghost-toggle textarea {
            padding-right: 68px;
        }

        .textarea-wrapper.is-ghost-mode textarea {
            background-color: #5b21b6;
            color: #f5f3ff;
            border-color: #ddd6fe;
            caret-color: #f5f3ff;
        }

        .textarea-wrapper.is-ghost-mode::after {
            content: '';
            position: absolute;
            right: 100px;
            bottom: 10px;
            width: min(176px, 42%);
            height: min(198px, calc(100% - 16px));
            background: url('Ghost-watermark.svg') no-repeat center / contain;
            opacity: 0.22;
            pointer-events: none;
            z-index: 0;
        }

        .textarea-wrapper.is-ghost-mode textarea::placeholder {
            color: #ddd6fe;
            opacity: 0.85;
        }

        .ghost-mode-toggle {
            position: absolute;
            bottom: 8px;
            right: 40px;
            width: 26px;
            height: 26px;
            padding: 0;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            font-size: 15px;
            z-index: 2;
            transition: background 0.12s, color 0.12s, border-color 0.12s, box-shadow 0.12s;
        }

        .ghost-mode-toggle:hover,
        .ghost-mode-toggle.is-active {
            background: #5b21b6;
            border-color: #ddd6fe;
            color: #f5f3ff;
            box-shadow: 0 0 0 2px rgba(196, 181, 253, 0.35);
        }

        .textarea-wrapper.is-ghost-mode .key-picker-toggle {
            background: rgba(91, 33, 182, 0.55);
            border-color: #ddd6fe;
            color: #f5f3ff;
        }

        .template-preview-rendered .shortcut-plus {
            display: inline-block;
            padding: 0 4px;
            color: var(--muted);
            font-weight: 700;
        }

        .key-picker-toggle {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 26px;
            height: 26px;
            padding: 4px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.88);
            color: var(--muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            z-index: 2;
            transition: background 0.12s, color 0.12s, border-color 0.12s;
        }

        .key-picker-toggle:hover,
        .key-picker-toggle.is-active {
            background: #fff;
            color: var(--accent);
            border-color: var(--accent);
        }

        .key-picker-toggle svg {
            width: 16px;
            height: 16px;
            display: block;
        }

        .key-picker-popup {
            position: absolute;
            bottom: 38px;
            right: 0;
            z-index: 200;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(3, 13, 27, 0.18);
            padding: 10px 12px 12px;
            width: min(440px, calc(100vw - 24px));
            max-height: 320px;
            overflow-y: auto;
        }

        .key-picker-popup[hidden] {
            display: none !important;
        }

        .key-picker-group {
            margin-top: 8px;
        }

        .key-picker-group:first-child {
            margin-top: 0;
        }

        .key-picker-group-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .key-picker-group-keys {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }

        .key-picker-key {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            height: 24px;
            padding: 0 7px;
            min-width: 26px;
            border: 1px solid #b7c1d0;
            border-bottom-width: 2px;
            border-radius: 6px;
            background: #f8fbff;
            color: #132238;
            font-size: 0.82em;
            font-weight: 600;
            line-height: 1;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.1s, color 0.1s, border-color 0.1s;
        }

        .key-picker-key:hover {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .key-picker-key-icon,
        .key-picker-key .shortcut-key-icon {
            width: 11px;
            height: 11px;
            display: block;
            flex: 0 0 auto;
        }

        .ticket-users-popover {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8fbff;
            padding: 10px 12px;
        }

        .ticket-users-popover-title {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }

        .ticket-users-popover-list {
            margin: 0;
            padding-left: 18px;
            display: grid;
            gap: 3px;
        }

        .ticket-participants-modal {
            position: fixed;
            inset: 0;
            background: rgba(10, 25, 41, 0.64);
            display: grid;
            place-items: center;
            padding: 16px;
            z-index: 1700;
        }

        .ticket-participants-modal[hidden] {
            display: none !important;
        }

        .ticket-participants-modal-card {
            width: min(640px, 100%);
            max-height: min(90vh, 760px);
            overflow: auto;
            background: #fff;
            border-radius: 14px;
            border: 1px solid var(--line);
            box-shadow: 0 18px 60px rgba(3, 13, 27, 0.26);
            padding: 14px;
            display: grid;
            gap: 10px;
        }

        .ticket-participants-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .ticket-participants-modal-head h3 {
            margin: 0;
            font-size: 16px;
        }

        .participant-modal-close {
            border: 0;
            background: #eef2f7;
            color: #334155;
            width: 34px;
            height: 34px;
            border-radius: 999px;
            font-size: 24px;
            line-height: 1;
            cursor: pointer;
        }

        .meta-item .secondary-button[data-role="manage-participants-open"],
        .meta-item .secondary-button[data-role="change-category-open"],
        .meta-item .secondary-button[data-role="change-title-open"] {
            width: auto;
            align-self: flex-start;
            margin-top: 2px;
            padding: 5px 10px;
            font-size: 12px;
            border-radius: 8px;
        }

        .status-select-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-select-row select {
            flex: 1 1 auto;
            min-width: 0;
        }

        .status-select-row .secondary-button {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .custom-status-recent {
            margin-top: 8px;
        }

        .custom-status-recent-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .custom-status-recent-chip {
            font-size: 13px;
        }

        [data-role="custom-status-feedback"] {
            min-height: 1.2em;
        }

        [data-role="custom-status-feedback"].is-error {
            color: #b91c1c;
        }

        .participant-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .participant-chip-form {
            display: inline-flex;
            align-items: center;
            gap: 0;
            border: 0;
            padding: 0;
            background: transparent;
            cursor: pointer;
        }

        .participant-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 6px 10px;
            background: #e8f1fb;
            color: #0b65c2;
            font-size: 12px;
            font-weight: 700;
            transition: transform 120ms ease, background 120ms ease, color 120ms ease, box-shadow 120ms ease;
        }

        .participant-chip.is-requester {
            background: #d1fadf;
            color: #067647;
        }

        .participant-chip-label,
        .participant-chip-remove-text {
            display: inline-block;
            min-width: 0;
            white-space: nowrap;
        }

        .participant-chip-remove-text {
            display: none;
        }

        .participant-chip-form:not(.is-lock-protected):hover .participant-chip,
        .participant-chip-form:not(.is-lock-protected):focus-visible .participant-chip {
            transform: translateY(-3px);
            background: #fee4e2;
            color: #b42318;
            box-shadow: 0 6px 14px rgba(180, 35, 24, 0.14);
        }

        .participant-chip-form:not(.is-lock-protected):hover .participant-chip-label,
        .participant-chip-form:not(.is-lock-protected):focus-visible .participant-chip-label {
            display: none;
        }

        .participant-chip-form:not(.is-lock-protected):hover .participant-chip-remove-text,
        .participant-chip-form:not(.is-lock-protected):focus-visible .participant-chip-remove-text {
            display: inline-block;
        }

        .participant-chip-form.is-lock-protected {
            cursor: default;
        }

        .participant-chip-form.is-lock-protected .participant-chip-remove-text {
            display: none !important;
        }

        .participant-chip-form.is-pending-remove .participant-chip {
            background: #fee4e2;
            color: #b42318;
            animation: participantPendingShake 0.45s ease-in-out infinite;
        }

        .participant-chip-form.is-pending-remove .participant-chip-label {
            display: inline-block;
        }

        .participant-chip-form.is-pending-remove .participant-chip-remove-text {
            display: none;
        }

        .ticket-participants-modal-card.is-save-hover .participant-chip-form.is-pending-remove .participant-chip {
            animation-duration: 0.2s;
        }

        @keyframes participantPendingShake {
            0% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-2px);
            }

            50% {
                transform: translateX(2px);
            }

            75% {
                transform: translateX(-1px);
            }

            100% {
                transform: translateX(0);
            }
        }

        .participant-add-form {
            display: grid;
            gap: 8px;
        }

        [data-role="manage-participants-feedback"],
        [data-role="change-category-feedback"],
        [data-role="change-title-feedback"] {
            min-height: 18px;
            margin: 0;
        }

        [data-role="manage-participants-feedback"].is-error,
        [data-role="change-category-feedback"].is-error,
        [data-role="change-title-feedback"].is-error {
            color: var(--danger);
        }

        [data-role="manage-participants-feedback"].is-success,
        [data-role="change-category-feedback"].is-success,
        [data-role="change-title-feedback"].is-success {
            color: var(--success);
        }

        .email-chip-field {
            min-height: 46px;
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 6px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            background: #fff;
        }

        .email-chip-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 8px;
        }

        .email-chip-remove {
            border: 0;
            border-radius: 999px;
            width: 18px;
            height: 18px;
            background: rgba(37, 99, 235, 0.14);
            color: #1d4ed8;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
        }

        .email-chip-input {
            border: 0 !important;
            box-shadow: none !important;
            flex: 1 1 180px;
            min-width: 140px;
            padding: 6px;
            border-radius: 8px;
        }

        .email-chip-input:focus {
            outline: none;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .settings-section-heading {
            margin: 8px 0 0;
            font-size: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 720px;
        }

        th,
        td {
            border-bottom: 1px solid var(--line);
            padding: 10px 8px;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        .user-color-cell {
            background: linear-gradient(90deg, var(--assignee-color, #0b65c2) 0 12px, #f8fbff 12px 100%);
        }

        .settings-user-cell {
            min-width: 220px;
        }

        .vacation-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .availability-slot {
            width: 12px;
            align-self: stretch;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: -10px 0 -10px -8px;
        }

        .availability-checkbox {
            margin: 0;
            width: 12px;
            height: 12px;
            accent-color: #ffffff;
            cursor: pointer;
        }

        .vacation-badge.is-away,
        .settings-row.is-away .vacation-badge {
            background: #94a3b8 !important;
            color: #fff;
        }

        .vacation-indicator {
            font-size: 16px;
            line-height: 1;
        }

        .settings-row.is-away .setting-checkbox-cell,
        .settings-row.is-away .open-load-cell {
            opacity: 0.4;
        }

        .settings-row.is-away .setting-checkbox-cell {
            pointer-events: none;
            filter: grayscale(1);
        }

        .empty-state {
            padding: 20px;
            border-radius: 14px;
            background: #f8fbff;
            border: 1px dashed var(--line);
            color: var(--muted);
            text-align: center;
        }

        @media (min-width: 760px) {

            .form-grid.two-columns,
            .admin-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .meta-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .ticket-summary {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }

            .template-ticket-layout {
                grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
            }
        }
    </style>
</head>
