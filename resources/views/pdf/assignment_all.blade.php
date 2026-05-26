<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @if(!empty($web_preview))
        <meta name="viewport" content="width=device-width, initial-scale=1">
    @else
        @include('pdf.partials.fonts')
    @endif
    @if(empty($web_preview))
    <style>
        * { box-sizing: border-box; }
        body {
            font-size: 9px;
            margin: 8px 10px;
            color: #111;
        }
        .doc-title {
            font-size: 11px;
            font-weight: 700;
            margin: 0 0 10px 0;
            letter-spacing: 0.02em;
        }
        .grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
        }
        .grid th,
        .grid td {
            border: 1px solid #111;
            padding: 3px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        .grid th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: center;
        }
        .wp-head {
            text-align: center;
            font-weight: 700;
        }
        .wp-sub {
            font-weight: 400;
            display: block;
            margin-top: 2px;
            min-height: 10px;
        }
        .cell-muted {
            color: #333;
        }
        .stack {
            line-height: 1.35;
        }
        .section-label {
            font-weight: 700;
            text-align: center;
            background: #f9fafb;
        }
        .page-break {
            page-break-after: always;
        }
        .company-footer {
            text-align: right;
            font-size: 11px;
            font-weight: 700;
            margin-top: 12px;
            padding-right: 6px;
        }
        .col-abs {
            width: 9%;
        }
    </style>
    @else
    <style>
        :root {
            --bg: #f1f5f9;
            --surface: #ffffff;
            --border: #e2e8f0;
            --border-strong: #cbd5e1;
            --text: #0f172a;
            --text-muted: #64748b;
            --primary: #2563eb;
            --primary-soft: #eff6ff;
            --accent-tech: #0ea5e9;
            --accent-worker: #6366f1;
            --accent-heavy: #f59e0b;
            --accent-vehicle: #10b981;
            --accent-absence: #ef4444;
            --shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        * { box-sizing: border-box; }

        body, table, th, td, h1, h2, h3, div, span, strong, b, button, a, input {
            font-family: system-ui, -apple-system, "Segoe UI", "Hiragino Sans", "Hiragino Kaku Gothic ProN", "Yu Gothic UI", "Meiryo", "Noto Sans JP", sans-serif;
        }

        html, body {
            background: var(--bg);
        }

        body {
            font-size: 15px;
            line-height: 1.55;
            color: var(--text);
            margin: 0;
            padding: 24px 28px 40px 28px;
            min-width: 1180px;
        }

        .preview-toolbar {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: saturate(180%) blur(12px);
            -webkit-backdrop-filter: saturate(180%) blur(12px);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 18px;
            margin: 0 0 20px 0;
            box-shadow: var(--shadow);
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
            align-items: center;
            font-size: 14px;
        }
        .preview-toolbar .toolbar-spacer {
            flex: 1 1 auto;
        }
        .preview-toolbar a.tool-link,
        .preview-toolbar button.tool-btn {
            font: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid var(--border-strong);
            background: #fff;
            color: var(--text);
            text-decoration: none;
            line-height: 1.2;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
        }
        .preview-toolbar a.tool-link:hover,
        .preview-toolbar button.tool-btn:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }
        .preview-toolbar a.tool-link.is-back {
            color: var(--text-muted);
            border-color: transparent;
            background: transparent;
            padding-left: 4px;
        }
        .preview-toolbar a.tool-link.is-back:hover {
            color: var(--text);
            background: transparent;
        }
        .preview-toolbar button.tool-btn.is-primary {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        .preview-toolbar button.tool-btn.is-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        .preview-toolbar a.tool-link.is-pdf {
            color: var(--primary);
            border-color: #bfdbfe;
            background: var(--primary-soft);
        }
        .preview-toolbar a.tool-link.is-pdf:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }

        .doc-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin: 0 0 14px 0;
            padding: 0 4px;
        }
        .doc-title {
            font-size: 26px;
            font-weight: 700;
            margin: 0;
            letter-spacing: 0.01em;
            color: var(--text);
        }
        .doc-title .doc-title-sub {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-muted);
            margin-top: 4px;
            letter-spacing: 0;
        }
        .doc-meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .doc-date-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            color: var(--text);
            font-weight: 600;
            font-size: 15px;
        }
        /* デスクトップでは表示切替ボタンは不要なので非表示。スマホで有効化する */
        .m-view-toggle { display: none; }

        .page-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 18px 18px 20px 18px;
            margin-bottom: 22px;
            overflow: hidden;
        }

        .grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
            margin: 0;
            font-size: 15px;
        }
        .grid th,
        .grid td {
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 10px 12px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
            background: #fff;
        }
        .grid tr > th:first-child,
        .grid tr > td:first-child {
            border-left: 1px solid var(--border);
        }
        .grid thead tr:first-child th {
            border-top: 1px solid var(--border);
        }
        .grid thead tr:first-child th:first-child {
            border-top-left-radius: 12px;
        }
        .grid thead tr:first-child th:last-child {
            border-top-right-radius: 12px;
        }
        .grid tbody tr:last-child td:first-child {
            border-bottom-left-radius: 12px;
        }
        .grid tbody tr:last-child td:last-child {
            border-bottom-right-radius: 12px;
        }

        .wp-head {
            text-align: center;
            font-weight: 700;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%) !important;
            padding: 12px 10px !important;
        }
        .wp-head .wp-head-label {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid var(--border);
        }
        .wp-sub {
            display: block;
            margin-top: 8px;
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            line-height: 1.35;
            min-height: 22px;
        }
        .wp-head.col-abs-head {
            background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%) !important;
        }
        .wp-head.col-abs-head .wp-head-label {
            color: #b91c1c;
            background: #fff;
            border-color: #fecaca;
        }

        .grid td.cell {
            font-size: 15px;
            line-height: 1.55;
        }
        .cell-muted {
            color: #94a3b8;
            font-style: normal;
        }
        .stack {
            line-height: 1.55;
        }
        .row-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--text-muted);
        }
        .row-label::before {
            content: "";
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.5;
        }
        .row-tech { color: var(--accent-tech); }
        .row-worker { color: var(--accent-worker); }

        .section-divider td {
            background: #f8fafc !important;
            padding: 6px 12px !important;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: var(--text-muted);
        }
        .section-divider .section-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
        }
        .section-divider .section-chip::before {
            content: "";
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: currentColor;
        }
        .section-divider.section-heavy .section-chip {
            background: #fef3c7;
            color: #b45309;
        }
        .section-divider.section-vehicle .section-chip {
            background: #d1fae5;
            color: #047857;
        }

        .name-line {
            display: block;
            padding: 2px 0;
        }
        .name-line .name-prefix {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            padding: 1px 6px;
            border-radius: 6px;
            background: #f1f5f9;
            color: var(--text-muted);
            margin-right: 6px;
            vertical-align: 2px;
        }
        .name-line .name-text {
            font-weight: 600;
            color: var(--text);
        }
        .name-line.is-tech .name-prefix {
            background: #e0f2fe;
            color: #0369a1;
        }

        .chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px 6px;
        }
        .chip {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            background: #f1f5f9;
            color: var(--text);
            border: 1px solid #e2e8f0;
        }
        .chip.chip-heavy {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }
        .chip.chip-vehicle {
            background: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .absence-cell {
            background: #fff7ed !important;
            border-left: 1px solid #fed7aa !important;
        }
        .absence-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .absence-list .absence-name {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #b91c1c;
        }
        .absence-list .absence-name::before {
            content: "";
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent-absence);
            flex: 0 0 auto;
        }
        .absence-empty {
            color: #94a3b8;
            font-size: 13px;
        }

        .col-abs {
            width: 11%;
        }

        .page-break {
            page-break-after: always;
        }

        .company-footer {
            text-align: right;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 18px;
            padding: 0 6px;
        }

        @media (max-width: 1280px) {
            body { padding: 16px 16px 32px 16px; min-width: 1180px; }
            .doc-title { font-size: 22px; }
        }

        /* === Mobile cards (default hidden on desktop) === */
        .mobile-cards { display: none; }
        .desktop-grid { display: block; }

        @media (max-width: 900px) {
            body {
                min-width: 0 !important;
                padding: 14px 14px 36px 14px;
                font-size: 15px;
            }

            .preview-toolbar {
                border-radius: 12px;
                padding: 10px 12px;
                font-size: 13px;
                gap: 8px;
            }
            .preview-toolbar .toolbar-spacer { display: none; }
            .preview-toolbar a.tool-link.is-back {
                width: 100%;
                padding: 6px 4px;
                margin-bottom: 2px;
                font-size: 13px;
            }
            .preview-toolbar button.tool-btn.is-primary,
            .preview-toolbar a.tool-link.is-pdf {
                flex: 1 1 0;
                justify-content: center;
                padding: 12px 12px;
                font-size: 14px;
                font-weight: 600;
            }

            .doc-header {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 0 2px;
            }
            .doc-title { font-size: 20px; }
            .doc-title .doc-title-sub { font-size: 13px; margin-top: 2px; }
            .doc-meta {
                align-self: flex-start;
                width: 100%;
                gap: 8px;
                flex-wrap: wrap;
            }
            .doc-date-badge {
                font-size: 14px;
                padding: 6px 12px;
            }
            .m-view-toggle {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 7px 14px;
                border-radius: 999px;
                border: 1px solid var(--border-strong);
                background: #fff;
                color: var(--text);
                font: inherit;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                line-height: 1.2;
                margin-left: auto;
                transition: background-color .15s ease, border-color .15s ease, color .15s ease;
            }
            .m-view-toggle:hover,
            .m-view-toggle:active {
                background: #f8fafc;
                border-color: #94a3b8;
            }
            .m-view-toggle::before {
                content: "";
                display: inline-block;
                width: 14px;
                height: 11px;
                background: currentColor;
                /* 3 段の横線（カード表示アイコン） */
                -webkit-mask: linear-gradient(currentColor, currentColor) top/100% 2px no-repeat,
                              linear-gradient(currentColor, currentColor) center/100% 2px no-repeat,
                              linear-gradient(currentColor, currentColor) bottom/100% 2px no-repeat;
                        mask: linear-gradient(currentColor, currentColor) top/100% 2px no-repeat,
                              linear-gradient(currentColor, currentColor) center/100% 2px no-repeat,
                              linear-gradient(currentColor, currentColor) bottom/100% 2px no-repeat;
            }
            .m-view-toggle[data-mode="table"] {
                background: var(--primary);
                color: #fff;
                border-color: var(--primary);
            }
            .m-view-toggle[data-mode="table"]:hover,
            .m-view-toggle[data-mode="table"]:active {
                background: #1d4ed8;
                border-color: #1d4ed8;
            }
            .m-view-toggle[data-mode="table"]::before {
                /* 3 本の縦線（テーブル列アイコン） */
                -webkit-mask: linear-gradient(currentColor, currentColor) left/2px 100% no-repeat,
                              linear-gradient(currentColor, currentColor) center/2px 100% no-repeat,
                              linear-gradient(currentColor, currentColor) right/2px 100% no-repeat;
                        mask: linear-gradient(currentColor, currentColor) left/2px 100% no-repeat,
                              linear-gradient(currentColor, currentColor) center/2px 100% no-repeat,
                              linear-gradient(currentColor, currentColor) right/2px 100% no-repeat;
            }

            /* === 表モード（スマホで横スクロール可能なテーブル表示） === */
            body.view-table .mobile-cards { display: none; }
            body.view-table .desktop-grid { display: block; }
            body.view-table .page-card {
                display: block;
                padding: 10px;
                margin-bottom: 14px;
                border-radius: 14px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            body.view-table .grid {
                min-width: 920px;
                font-size: 13px;
            }
            body.view-table .grid th,
            body.view-table .grid td { padding: 7px 8px; }
            body.view-table .wp-head { padding: 8px 6px !important; }
            body.view-table .wp-head .wp-head-label { font-size: 10px; padding: 1px 6px; }
            body.view-table .wp-sub { font-size: 13px; min-height: 18px; margin-top: 5px; }
            body.view-table .grid td.cell { font-size: 13px; line-height: 1.45; }
            body.view-table .chip { font-size: 12px; padding: 2px 8px; }
            body.view-table .name-line .name-prefix {
                font-size: 10px;
                padding: 1px 5px;
                margin-right: 5px;
            }
            body.view-table .section-divider .section-chip { font-size: 11px; padding: 2px 8px; }
            body.view-table .absence-list .absence-name { font-size: 13px; }
            body.view-table .doc-title .doc-title-sub { display: none; }

            .desktop-grid { display: none; }
            .mobile-cards { display: block; }
            .page-card { display: none; }

            .m-absence-banner {
                background: linear-gradient(180deg, #fff7ed 0%, #ffedd5 100%);
                border: 1px solid #fed7aa;
                border-radius: 14px;
                padding: 12px 14px;
                margin-bottom: 16px;
                box-shadow: var(--shadow);
            }
            .m-absence-banner-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                font-weight: 700;
                color: #b91c1c;
                letter-spacing: 0.04em;
                margin-bottom: 10px;
            }
            .m-absence-icon {
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: var(--accent-absence);
                flex: 0 0 auto;
            }
            .m-absence-count {
                margin-left: auto;
                background: #fff;
                color: #b91c1c;
                border: 1px solid #fecaca;
                padding: 2px 9px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
            }
            .m-absence-banner-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .m-absence-pill {
                background: #fff;
                color: #b91c1c;
                border: 1px solid #fecaca;
                padding: 4px 11px;
                border-radius: 999px;
                font-weight: 600;
                font-size: 14px;
            }
            .m-no-absence {
                background: #ecfdf5;
                color: #047857;
                border: 1px solid #a7f3d0;
                border-radius: 12px;
                padding: 10px 14px;
                margin-bottom: 16px;
                font-size: 13px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .m-no-absence::before {
                content: "";
                display: inline-block;
                width: 8px;
                height: 8px;
                border-radius: 999px;
                background: #10b981;
            }

            .m-card {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 16px;
                box-shadow: var(--shadow);
                margin-bottom: 14px;
                overflow: hidden;
            }
            .m-card-head {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 12px 14px;
                background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
                border-bottom: 1px solid var(--border);
            }
            .m-card-num {
                display: inline-flex;
                align-items: center;
                padding: 3px 10px;
                border-radius: 999px;
                background: var(--primary);
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.08em;
                flex: 0 0 auto;
            }
            .m-card-title {
                font-size: 16px;
                font-weight: 700;
                color: var(--text);
                line-height: 1.35;
            }
            .m-card-body { padding: 2px 14px 10px 14px; }
            .m-row {
                display: grid;
                grid-template-columns: 70px 1fr;
                align-items: start;
                gap: 12px;
                padding: 11px 0;
                border-bottom: 1px dashed var(--border);
            }
            .m-row:last-child { border-bottom: none; }
            .m-row-label {
                font-size: 12px;
                font-weight: 700;
                color: var(--text-muted);
                letter-spacing: 0.06em;
                padding-top: 5px;
            }
            .m-row-label .m-row-chip {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.08em;
            }
            .m-row-label .m-row-chip::before {
                content: "";
                display: inline-block;
                width: 6px;
                height: 6px;
                border-radius: 999px;
                background: currentColor;
            }
            .m-row-label .m-chip-heavy { background: #fef3c7; color: #b45309; }
            .m-row-label .m-chip-vehicle { background: #d1fae5; color: #047857; }
            .m-row-content {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 0;
            }
            .m-row-content .name-line { padding: 1px 0; }
            .m-row-content .chip-list { margin-top: 2px; }
        }

        @media (max-width: 420px) {
            body { padding: 12px 10px 32px 10px; }
            .doc-title { font-size: 18px; }
            .m-row { grid-template-columns: 58px 1fr; gap: 10px; }
            .m-row-label { font-size: 11px; padding-top: 4px; }
            .m-row-label .m-row-chip { padding: 3px 8px; font-size: 11px; }
            .m-card-title { font-size: 15px; }
            .m-card-head { padding: 10px 12px; gap: 8px; }
            .m-card-body { padding: 2px 12px 8px 12px; }
            .name-line .name-prefix { font-size: 10px; padding: 1px 5px; margin-right: 5px; }
            .chip { font-size: 12px; padding: 3px 9px; }
        }

        @media print {
            html, body { background: #fff; }
            body {
                min-width: 0;
                padding: 8px 10px;
                font-size: 11px;
            }
            .no-print { display: none !important; }
            .doc-header { margin-bottom: 8px; }
            .doc-title { font-size: 14px; }
            .doc-title .doc-title-sub { display: none; }
            .doc-date-badge { display: none; }
            .page-card {
                box-shadow: none;
                border: none;
                padding: 0;
                margin-bottom: 12px;
                border-radius: 0;
            }
            .grid { font-size: 10px; }
            .grid th, .grid td { padding: 4px 5px; }
            .wp-head .wp-head-label { display: none; }
            .wp-sub { font-size: 11px; min-height: 12px; margin-top: 2px; }
            .row-label { display: none; }
            .section-divider td { padding: 3px 5px !important; }
            .section-divider .section-chip {
                background: transparent !important;
                color: #111 !important;
                padding: 0;
                font-size: 10px;
            }
            .section-divider .section-chip::before { display: none; }
            .chip { background: transparent; border: none; padding: 0; font-size: 10px; font-weight: 400; }
            .name-line .name-prefix { background: transparent; padding: 0; margin-right: 4px; color: #333; }
        }
    </style>
    @endif
</head>
<body>
@php
    $pdf_data_list = is_array($pdf_data_list ?? null) ? $pdf_data_list : [];
    $absenceFlat = $pdf_data_list['absence_list'] ?? [];
    $pages = collect($pdf_data_list)->except(['absence_list']);
    $absenceText = collect($absenceFlat)->filter(fn ($n) => $n !== null && $n !== '')->implode("\n");
    $absenceNames = collect($absenceFlat)->filter(fn ($n) => $n !== null && $n !== '')->values()->all();
    $isWeb = !empty($web_preview);
@endphp

@if($isWeb)
    <div class="preview-toolbar no-print">
        <a class="tool-link is-back" href="{{ $assignment_list_url ?? route('top.assignment') }}">← 配置一覧に戻る</a>
        <span class="toolbar-spacer"></span>
        <button type="button" class="tool-btn is-primary" onclick="window.print()">印刷する</button>
        <a class="tool-link is-pdf" href="{{ $assignment_pdf_url ?? '#' }}">PDFでダウンロード</a>
    </div>

    <div class="doc-header">
        <h1 class="doc-title">
            社員 ・ 作業員 ・ 重機 ・ 車両配置一覧
            <span class="doc-title-sub">現場ごとの配置と本日の欠勤予定者を表示しています</span>
        </h1>
        <div class="doc-meta">
            <div class="doc-date-badge">{{ $display_date ?? '' }}</div>
            <button id="m-view-toggle" type="button" class="m-view-toggle no-print" data-mode="card" aria-label="表示切替">
                <span class="m-view-toggle-label">横表示</span>
            </button>
        </div>
    </div>

    {{-- スマホ用カードレイアウト（≤900px で表示） --}}
    <div class="mobile-cards">
        @if(count($absenceNames) > 0)
            <div class="m-absence-banner">
                <div class="m-absence-banner-title">
                    <span class="m-absence-icon"></span>
                    本日の欠勤予定者
                    <span class="m-absence-count">{{ count($absenceNames) }}名</span>
                </div>
                <div class="m-absence-banner-list">
                    @foreach($absenceNames as $absName)
                        <span class="m-absence-pill">{{ $absName }}</span>
                    @endforeach
                </div>
            </div>
        @else
            <div class="m-no-absence">本日の欠勤予定者はいません</div>
        @endif

        @foreach($pages as $page)
            @for($i = 1; $i <= 8; $i++)
                @php
                    $wp = $page['workplace'.$i] ?? [];
                    $wpName = $wp['workplace_name'] ?? '';
                    $techs = array_values(array_filter($wp['technitian_list'] ?? [], fn ($n) => $n !== null && $n !== ''));
                    $workers = collect($wp['worker_list'] ?? [])->filter(fn ($w) => !empty($w['staff_name']));
                    $heavy = collect($wp['equipment_list'] ?? [])->filter(fn ($e) => !empty($e['vehicle_name']));
                    $vehicles = collect($wp['vehicle_list'] ?? [])->filter(fn ($v) => !empty($v['vehicle_name']));
                    $hasAny = $wpName !== '' || count($techs) > 0 || $workers->isNotEmpty() || $heavy->isNotEmpty() || $vehicles->isNotEmpty();
                @endphp
                @if($hasAny)
                    <div class="m-card">
                        <div class="m-card-head">
                            <span class="m-card-num">現場 {{ $i }}</span>
                            <span class="m-card-title">{{ $wpName !== '' ? $wpName : '—' }}</span>
                        </div>
                        <div class="m-card-body">
                            <div class="m-row">
                                <div class="m-row-label">技術者</div>
                                <div class="m-row-content">
                                    @if(count($techs) === 0)
                                        <span class="cell-muted">—</span>
                                    @else
                                        @foreach($techs as $t)
                                            <span class="name-line is-tech">
                                                <span class="name-prefix">技術者</span><span class="name-text">{{ $t }}</span>
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            <div class="m-row">
                                <div class="m-row-label">作業員</div>
                                <div class="m-row-content">
                                    @if($workers->isEmpty())
                                        <span class="cell-muted">—</span>
                                    @else
                                        @foreach($workers as $w)
                                            <span class="name-line">
                                                @if(!empty($w['staff_type']))
                                                    <span class="name-prefix">{{ $w['staff_type'] }}</span>
                                                @endif
                                                <span class="name-text">{{ $w['staff_name'] }}</span>
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            <div class="m-row">
                                <div class="m-row-label"><span class="m-row-chip m-chip-heavy">重機</span></div>
                                <div class="m-row-content">
                                    @if($heavy->isEmpty())
                                        <span class="cell-muted">—</span>
                                    @else
                                        <div class="chip-list">
                                            @foreach($heavy as $e)
                                                <span class="chip chip-heavy">{{ $e['vehicle_name'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="m-row">
                                <div class="m-row-label"><span class="m-row-chip m-chip-vehicle">車両</span></div>
                                <div class="m-row-content">
                                    @if($vehicles->isEmpty())
                                        <span class="cell-muted">—</span>
                                    @else
                                        <div class="chip-list">
                                            @foreach($vehicles as $v)
                                                <span class="chip chip-vehicle">{{ $v['vehicle_name'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endfor
        @endforeach
    </div>

    <div class="desktop-grid">
@endif

@foreach($pages as $pageIdx => $page)
    @if(!$loop->first)
        <div class="page-break"></div>
    @endif

    @if(!$isWeb)
        <div class="doc-title">
            社員 ・ 作業員 ・ 重機 ・ 車両配置一覧 ( {{ $display_date ?? '' }} )
        </div>
    @endif

    @if($isWeb)
    <div class="page-card">
    @endif

    <table class="grid">
        <thead>
            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <th class="wp-head">
                        @if($isWeb)
                            <span class="wp-head-label">現場 {{ $i }}</span>
                        @else
                            現場
                        @endif
                        <span class="wp-sub">{{ $wp['workplace_name'] ?? '' }}</span>
                    </th>
                @endfor
                <th class="wp-head col-abs col-abs-head">
                    @if($isWeb)
                        <span class="wp-head-label">本日</span>
                        <span class="wp-sub">欠勤予定者</span>
                    @else
                        欠勤予定者
                    @endif
                </th>
            </tr>
        </thead>
        <tbody>
            @for($r = 0; $r < 3; $r++)
                <tr>
                    @for($i = 1; $i <= 8; $i++)
                        @php
                            $wp = $page['workplace'.$i] ?? [];
                            $tech = $wp['technitian_list'] ?? [];
                            $name = $tech[$r] ?? '';
                        @endphp
                        <td class="cell">
                            @if($isWeb)
                                @if($name !== '')
                                    <span class="name-line is-tech">
                                        <span class="name-prefix">技術者</span><span class="name-text">{{ $name }}</span>
                                    </span>
                                @else
                                    <span class="cell-muted">—</span>
                                @endif
                            @else
                                @if($name !== '')
                                    技術者 {{ $name }}
                                @else
                                    <span class="cell-muted">技術者</span>
                                @endif
                            @endif
                        </td>
                    @endfor
                    @if($r === 0)
                        @if($isWeb)
                            <td rowspan="8" class="cell absence-cell stack" style="white-space: pre-wrap;">
                                @if(count($absenceNames) > 0)
                                    <div class="absence-list">
                                        @foreach($absenceNames as $absName)
                                            <span class="absence-name">{{ $absName }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="absence-empty">なし</span>
                                @endif
                            </td>
                        @else
                            <td rowspan="3" class="cell absence-cell stack" style="white-space: pre-wrap;">{{ $absenceText }}</td>
                        @endif
                    @endif
                </tr>
            @endfor

            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <td class="cell stack">
                        @if($isWeb)
                            @php
                                $workers = collect($wp['worker_list'] ?? [])->filter(fn ($w) => !empty($w['staff_name']));
                            @endphp
                            @if($workers->isEmpty())
                                <span class="cell-muted">—</span>
                            @else
                                @foreach($workers as $w)
                                    <span class="name-line">
                                        @if(!empty($w['staff_type']))
                                            <span class="name-prefix">{{ $w['staff_type'] }}</span>
                                        @endif
                                        <span class="name-text">{{ $w['staff_name'] }}</span>
                                    </span>
                                @endforeach
                            @endif
                        @else
                            @foreach($wp['worker_list'] ?? [] as $w)
                                @if(!empty($w['staff_name']))
                                    {{ $w['staff_type'] }} {{ $w['staff_name'] }}<br>
                                @endif
                            @endforeach
                        @endif
                    </td>
                @endfor
                @if(!$isWeb)<td></td>@endif
            </tr>

            @if($isWeb)
                <tr class="section-divider section-heavy">
                    <td colspan="8"><span class="section-chip">重機</span></td>
                </tr>
            @else
                <tr>
                    @for($i = 1; $i <= 8; $i++)
                        <td class="section-label">重機</td>
                    @endfor
                    <td></td>
                </tr>
            @endif
            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <td class="cell stack">
                        @if($isWeb)
                            @php
                                $heavyItems = collect($wp['equipment_list'] ?? [])->filter(fn ($e) => !empty($e['vehicle_name']));
                            @endphp
                            @if($heavyItems->isEmpty())
                                <span class="cell-muted">—</span>
                            @else
                                <div class="chip-list">
                                    @foreach($heavyItems as $e)
                                        <span class="chip chip-heavy">{{ $e['vehicle_name'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            @foreach($wp['equipment_list'] ?? [] as $e)
                                @if(!empty($e['vehicle_name']))
                                    {{ $e['vehicle_name'] }}<br>
                                @endif
                            @endforeach
                        @endif
                    </td>
                @endfor
                @if(!$isWeb)<td></td>@endif
            </tr>

            @if($isWeb)
                <tr class="section-divider section-vehicle">
                    <td colspan="8"><span class="section-chip">車両</span></td>
                </tr>
            @else
                <tr>
                    @for($i = 1; $i <= 8; $i++)
                        <td class="section-label">車両</td>
                    @endfor
                    <td></td>
                </tr>
            @endif
            <tr>
                @for($i = 1; $i <= 8; $i++)
                    @php $wp = $page['workplace'.$i] ?? []; @endphp
                    <td class="cell stack">
                        @if($isWeb)
                            @php
                                $vehicles = collect($wp['vehicle_list'] ?? [])->filter(fn ($v) => !empty($v['vehicle_name']));
                            @endphp
                            @if($vehicles->isEmpty())
                                <span class="cell-muted">—</span>
                            @else
                                <div class="chip-list">
                                    @foreach($vehicles as $v)
                                        <span class="chip chip-vehicle">{{ $v['vehicle_name'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                        @else
                            @foreach($wp['vehicle_list'] ?? [] as $v)
                                @if(!empty($v['vehicle_name']))
                                    {{ $v['vehicle_name'] }}<br>
                                @endif
                            @endforeach
                        @endif
                    </td>
                @endfor
                @if(!$isWeb)<td></td>@endif
            </tr>
        </tbody>
    </table>

    @if($isWeb)
    </div>
    @endif
@endforeach

@if($isWeb)
    </div>{{-- /.desktop-grid --}}
@endif

@if(!$isWeb)
<div class="page-break"></div>
@endif
<div class="company-footer">中塚建設株式会社</div>
@if($isWeb)
<script>
(function () {
    var STORAGE_KEY = 'nk-assignment-mobile-view';
    var btn = document.getElementById('m-view-toggle');
    if (!btn) return;
    var labelEl = btn.querySelector('.m-view-toggle-label');

    function applyMode(mode) {
        if (mode !== 'table') mode = 'card';
        btn.setAttribute('data-mode', mode);
        if (labelEl) {
            labelEl.textContent = (mode === 'table') ? 'カード表示' : '横表示';
        }
        btn.setAttribute('aria-label', (mode === 'table') ? 'カード表示に切替' : '横表示に切替');
        if (mode === 'table') {
            document.body.classList.add('view-table');
        } else {
            document.body.classList.remove('view-table');
        }
    }

    var saved = null;
    try { saved = window.localStorage.getItem(STORAGE_KEY); } catch (e) {}
    applyMode(saved === 'table' ? 'table' : 'card');

    btn.addEventListener('click', function () {
        var next = (btn.getAttribute('data-mode') === 'table') ? 'card' : 'table';
        applyMode(next);
        try { window.localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
    });
})();
</script>
@endif
</body>
</html>
