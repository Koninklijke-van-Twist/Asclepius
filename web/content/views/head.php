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
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            line-height: 0;
            border-radius: 4px;
            display: block;
        }

        .lang-current img {
            width: 28px;
            height: 20px;
            border-radius: 3px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
            display: block;
        }

        .lang-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 28px rgba(15, 35, 63, 0.18);
            padding: 6px;
            margin: 0;
            list-style: none;
            min-width: 150px;
            display: grid;
            gap: 2px;
        }

        .lang-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
            transition: background 0.1s;
        }

        .lang-option:hover {
            background: var(--accent-soft);
        }

        .lang-option.is-active {
            color: var(--accent);
        }

        .lang-option img {
            width: 22px;
            height: 15px;
            border-radius: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            flex-shrink: 0;
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

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
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

        .filters-form {
            display: grid;
            gap: 12px;
            padding: 12px;
            background: #f8fbff;
            border-radius: 14px;
            border: 1px solid var(--line);
            margin-bottom: 14px;
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

        .ticket-body {
            padding: 0 14px 14px;
            display: grid;
            gap: 14px;
        }

        .meta-grid {
            display: grid;
            gap: 10px;
        }

        .meta-item {
            padding: 10px;
            border-radius: 12px;
            background: #f8fbff;
            border: 1px solid var(--line);
        }

        .meta-label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 4px;
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

        .attachment-list {
            margin: 8px 0 0;
            padding-left: 18px;
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

        .meta-item .secondary-button[data-role="manage-participants-open"] {
            width: 100%;
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

        [data-role="manage-participants-feedback"] {
            min-height: 18px;
            margin: 0;
        }

        [data-role="manage-participants-feedback"].is-error {
            color: var(--danger);
        }

        [data-role="manage-participants-feedback"].is-success {
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
            .admin-grid,
            .meta-grid,
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .ticket-summary {
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
            }
        }
    </style>
</head>