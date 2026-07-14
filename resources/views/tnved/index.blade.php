<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ТН ВЭД</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    <style>
        :root {
            --bg: #f1f5f9;
            --surface: #fff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #1d4ed8;
            --primary-bg: #eff6ff;
        }

        * { box-sizing: border-box; }

        html {
            overflow-x: clip;
        }

        body {
            margin: 0;
            font: 14px/1.45 Inter, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            overflow-x: clip;
        }

        [x-cloak] { display: none !important; }

        .app {
            max-width: 1280px;
            margin: 0 auto;
            padding: 12px 16px 20px;
            min-width: 0;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
        }

        .alert {
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 0.85rem;
        }

        .actualization {
            margin-bottom: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            background: var(--primary-bg);
            border: 1px solid #dbeafe;
            color: #1e3a8a;
            font-size: 0.85rem;
        }

        .actualization--compact {
            margin: 0 0 8px;
            padding: 0;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .sidebar-actualization {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 12px;
            align-items: start;
            min-width: 0;
        }

        .layout > * {
            min-width: 0;
        }

        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar-scroll { max-height: none; }

            .scheme-tree-level-1 { margin-left: 10px; }
            .scheme-tree-level-2 { margin-left: 18px; }
            .scheme-tree-level-3 { margin-left: 26px; }
            .scheme-tree-level-4 { margin-left: 34px; }
            .scheme-tree-level-5 { margin-left: 42px; }
            .scheme-tree-level-6 { margin-left: 50px; }
            .scheme-tree-level-7 { margin-left: 58px; }
            .scheme-tree-level-8 { margin-left: 66px; }

            .tree-row .row-btn {
                padding-left: 8px !important;
            }

            .row-text {
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
        }

        @media (max-width: 640px) {
            body { font-size: 13px; }

            .app {
                padding: 8px 8px 14px;
            }

            .topbar {
                align-items: flex-start;
                gap: 6px;
                margin-bottom: 8px;
            }

            .topbar h1 {
                font-size: 1rem;
            }

            .layout {
                gap: 8px;
            }

            .search-row {
                padding: 8px;
            }

            .search-row input,
            .search-row select {
                padding: 8px 9px;
                min-width: 0;
            }

            .head-breadcrumb {
                padding: 6px 8px;
                font-size: 0.76rem;
            }

            .head-breadcrumb li {
                max-width: 100%;
            }

            .head-breadcrumb button,
            .head-breadcrumb .crumb-current {
                overflow-wrap: anywhere;
                word-break: break-word;
                text-align: left;
            }

            .detail {
                padding: 8px;
                overflow-wrap: anywhere;
            }

            .head-title {
                font-size: 0.92rem;
                margin-bottom: 8px;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            .head-table { font-size: 0.76rem; }
            .head-table thead { display: none; }
            .head-table, .head-table tbody, .head-table tr, .head-table td { display: block; width: 100%; max-width: 100%; }
            .head-table tr { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-bottom: 6px; background: #fff; }
            .head-table td {
                padding: 6px 8px;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
                font-size: 0.78rem;
                overflow-wrap: anywhere;
                word-break: break-word;
            }
            .head-table td::before {
                content: attr(data-label);
                flex: none;
                min-width: 0;
                width: 100%;
                font-weight: 600;
                color: var(--muted);
            }
            .head-table td:last-child { border-bottom: 0; }

            .welcome {
                padding: 32px 16px;
                min-height: 280px;
            }

            .welcome-icon { font-size: 2rem; }
            .welcome-title { font-size: 1rem; }

            .toolbar {
                gap: 4px;
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-group {
                flex-wrap: wrap;
                max-width: 100%;
            }

            .btn {
                padding: 5px 8px;
                font-size: 0.76rem;
            }

            .nav-pager {
                width: 100%;
                margin-left: 0;
                justify-content: flex-start;
            }

            .desc {
                font-size: 0.8rem;
                padding: 8px;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            .row-btn {
                padding: 8px;
                align-items: flex-start;
            }

            .row-text {
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
                line-height: 1.35;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            .code.wide {
                max-width: 100%;
                overflow-wrap: anywhere;
            }

            .tree-row .row-btn {
                padding-left: 8px !important;
            }

            .scheme {
                padding: 8px;
                font-size: 0.8rem;
                overflow-x: clip;
            }

            .scheme-tree-row {
                align-items: flex-start;
                flex-wrap: wrap;
                gap: 4px;
                margin: 6px 0;
                max-width: 100%;
            }

            .scheme-tree-text {
                flex: 1 1 100%;
                min-width: 0;
                overflow-wrap: anywhere;
                word-break: break-word;
            }

            .scheme-tree-row > span:first-child,
            .scheme-tree-row > .scheme-icon {
                flex: 0 0 auto;
            }

            .scheme-expand-btn {
                margin-left: auto;
            }

            .scheme-tree-level-0,
            .scheme-tree-level-1,
            .scheme-tree-level-2,
            .scheme-tree-level-3,
            .scheme-tree-level-4,
            .scheme-tree-level-5,
            .scheme-tree-level-6,
            .scheme-tree-level-7,
            .scheme-tree-level-8 {
                margin-left: 0;
                padding-left: 0;
            }

            .scheme-tree-level-1 { padding-left: 8px; }
            .scheme-tree-level-2 { padding-left: 14px; }
            .scheme-tree-level-3 { padding-left: 18px; }
            .scheme-tree-level-4 { padding-left: 22px; }
            .scheme-tree-level-5 { padding-left: 26px; }
            .scheme-tree-level-6 { padding-left: 30px; }
            .scheme-tree-level-7 { padding-left: 34px; }
            .scheme-tree-level-8 { padding-left: 38px; }

            .scheme-siblings {
                margin-left: 0 !important;
                padding-left: 18px;
            }
        }

        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            min-width: 0;
            max-width: 100%;
        }

        .search-row {
            display: grid;
            gap: 6px;
            padding: 10px;
            border-bottom: 1px solid var(--border);
        }

        .search-row input,
        .search-row select {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 10px;
            font: inherit;
        }

        .search-row input:focus,
        .search-row select:focus {
            outline: 2px solid var(--primary-bg);
            border-color: var(--primary);
        }

        .sidebar-scroll {
            max-height: calc(100vh - 140px);
            overflow: auto;
        }

        .row-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            border: 0;
            border-bottom: 1px solid #f1f5f9;
            background: transparent;
            padding: 7px 10px;
            text-align: left;
            font: inherit;
            cursor: pointer;
            color: inherit;
        }

        .row-btn:hover { background: #f8fafc; }
        .row-btn.active { background: var(--primary-bg); }
        .row-btn.focused { background: #e0e7ff; }

        .code {
            flex: 0 0 auto;
            font: 500 0.78rem/1 ui-monospace, Menlo, monospace;
            color: #334155;
            background: #f1f5f9;
            border-radius: 4px;
            padding: 2px 6px;
            min-width: 2.2em;
            text-align: center;
        }

        .code.wide { min-width: auto; text-align: left; }

        .row-text {
            flex: 1;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            overflow-wrap: anywhere;
        }

        .row-sub {
            font-size: 0.75rem;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            flex: 0 0 auto;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--muted);
            background: #f1f5f9;
            padding: 1px 6px;
            border-radius: 10px;
            line-height: 1.4;
        }

        .side-label {
            padding: 6px 10px;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            background: #fafafa;
            border-bottom: 1px solid var(--border);
        }

        .part-back {
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            border: 0;
            border-bottom: 1px solid var(--border);
            background: #f8fafc;
            padding: 8px 10px;
            text-align: left;
            font: inherit;
            font-size: 0.82rem;
            color: var(--primary);
            cursor: pointer;
        }

        .part-back:hover { background: #eef2ff; }

        .part-row {
            display: block;
            width: 100%;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
            padding: 10px 12px;
            text-align: left;
            font: inherit;
            cursor: pointer;
            color: inherit;
        }

        .part-row:hover,
        .part-row.active {
            background: var(--primary, #2563eb);
            color: #fff;
        }

        .part-row .part-label {
            display: block;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            margin-bottom: 4px;
        }

        .part-row .part-name {
            display: block;
            font-size: 0.72rem;
            line-height: 1.35;
            opacity: 0.92;
        }

        .part-row.compact {
            padding: 8px 10px;
        }

        .part-row.compact .part-name {
            display: none;
        }

        .tks-section-row {
            display: block;
            width: 100%;
            border: 0;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
            padding: 12px 14px;
            text-align: left;
            font: inherit;
            cursor: pointer;
            color: inherit;
        }

        .tks-section-row:hover,
        .tks-section-row.active {
            background: var(--primary, #2563eb);
            color: #fff;
        }

        .tks-section-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .tks-section-title {
            display: block;
            font-size: 0.78rem;
            line-height: 1.4;
            text-transform: uppercase;
            opacity: 0.95;
        }

        .tks-section-row.compact .tks-section-title {
            display: none;
        }

        .tks-product-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .tks-product-item {
            border-bottom: 1px solid #e2e8f0;
        }

        .tks-product-head {
            display: block;
            width: 100%;
            border: 0;
            background: #fff;
            padding: 12px 14px;
            text-align: left;
            font: inherit;
            cursor: pointer;
            color: inherit;
        }

        .tks-product-head:hover,
        .tks-product-item--expanded > .tks-product-head {
            background: var(--primary, #2563eb);
            color: #fff;
        }

        .tks-product-chapter {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .tks-product-section {
            display: block;
            font-size: 0.78rem;
            line-height: 1.4;
            text-transform: uppercase;
            opacity: 0.95;
        }

        .tks-tree-list {
            list-style: none;
            margin: 0;
            padding: 0 0 8px;
        }

        .tks-tree-list .tks-tree-list {
            padding-left: 30px;
        }

        .tks-tree-item {
            border-bottom: 1px solid #f1f5f9;
        }

        .tks-tree-title {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            width: 100%;
            border: 0;
            background: transparent;
            padding: 8px 14px;
            text-align: left;
            font: inherit;
            cursor: pointer;
            color: inherit;
        }

        .tks-tree-title:hover {
            background: #f8fafc;
        }

        .tks-tree-code {
            flex: 0 0 auto;
            min-width: 6.5em;
            font: 600 0.8rem/1.35 ui-monospace, Menlo, monospace;
            color: #334155;
        }

        .tks-tree-name {
            flex: 1;
            min-width: 0;
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .tks-tree-item--folder .tks-tree-name {
            font-style: normal;
        }

        .tks-tree-item--leaf .tks-tree-code {
            min-width: 8.5em;
        }

        .tree-loading {
            padding: 16px;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .parts-panel {
            padding: 0;
        }

        .parts-panel .part-row .part-name {
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .part-head {
            margin-bottom: 12px;
        }

        .part-head .part-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 6px;
        }

        .part-head .part-name {
            font-size: 0.85rem;
            line-height: 1.45;
            color: var(--muted);
            text-transform: uppercase;
        }

        .search-foot {
            padding: 6px 10px;
            font-size: 0.75rem;
            color: var(--muted);
            border-top: 1px solid var(--border);
        }

        .main {
            min-height: 320px;
            min-width: 0;
        }

        .head-breadcrumb {
            list-style: none;
            margin: 0;
            padding: 8px 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            border-bottom: 1px solid var(--border);
            background: #fafafa;
            font-size: 0.8rem;
        }

        .head-breadcrumb li {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--muted);
        }

        .head-breadcrumb li:not(:last-child)::after {
            content: "›";
            color: #cbd5e1;
            margin-left: 2px;
        }

        .head-breadcrumb button {
            border: 0;
            background: transparent;
            color: inherit;
            font: inherit;
            cursor: pointer;
            padding: 0;
        }

        .head-breadcrumb button:hover { color: var(--primary); }

        .head-title {
            margin: 0 0 10px;
            font-size: 1rem;
            font-weight: 600;
            line-height: 1.35;
            overflow-wrap: anywhere;
            word-break: break-word;
        }


        .head-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            font-size: 0.82rem;
            margin-bottom: 12px;
            table-layout: fixed;
        }

        .head-table th,
        .head-table td {
            border: 1px solid var(--border);
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .head-table th {
            background: #f8fafc;
            font-weight: 600;
            white-space: nowrap;
        }

        .detail {
            padding: 12px;
            min-width: 0;
            max-width: 100%;
        }


        .toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
            max-width: 100%;
        }

        .toolbar-group {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 2px;
            background: #fafafa;
            max-width: 100%;
        }

        .toolbar-group .btn {
            border: 0;
            border-radius: 4px;
            background: transparent;
        }

        .toolbar-group .btn:hover {
            background: var(--primary-bg);
        }

        .toolbar-group .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .toolbar-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0 4px;
        }

        .btn {
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 6px;
            padding: 5px 10px;
            font: inherit;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .btn:hover { border-color: #cbd5e1; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn-ghost { border-color: transparent; color: var(--muted); padding: 5px 6px; }
        .btn-ghost:hover { color: var(--primary); background: var(--primary-bg); }

        .nav-pager {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            color: var(--muted);
        }

        .block-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin: 12px 0 6px;
        }

        .block-title:first-child { margin-top: 0; }

        .children-intro {
            margin: 0 0 8px;
            font-size: 0.85rem;
            color: #334155;
            line-height: 1.45;
        }

        .children-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            font-size: 0.85rem;
            table-layout: fixed;
        }

        .children-table td {
            border-top: 1px solid var(--border);
            padding: 8px 10px;
            vertical-align: top;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .children-table tr:first-child td {
            border-top: 0;
        }

        .children-table tbody tr:hover {
            background: #f8fafc;
        }

        .children-table .td-code {
            width: 9.5em;
            font: 500 0.82rem/1.35 ui-monospace, Menlo, monospace;
            color: #334155;
            white-space: nowrap;
        }

        .children-table .child-link {
            border: 0;
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-align: left;
        }

        .children-table .child-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .children-table .td-code {
                width: auto;
                white-space: normal;
                display: block;
                padding-bottom: 0;
            }

            .children-table tr {
                display: block;
                border-top: 1px solid var(--border);
            }

            .children-table tr:first-child {
                border-top: 0;
            }

            .children-table td {
                display: block;
                border: 0;
                padding: 8px 10px 10px;
            }

            .children-table td:first-child {
                padding-bottom: 4px;
                font-weight: 600;
            }
        }

        .desc {
            font-size: 0.85rem;
            line-height: 1.55;
            color: #334155;
            background: #f8fafc;
            border-radius: 6px;
            padding: 10px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            max-width: 100%;
        }

        .list {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            max-width: 100%;
        }

        .tree-row {
            display: flex;
            align-items: stretch;
            border-bottom: 1px solid #f1f5f9;
            min-width: 0;
            max-width: 100%;
        }

        .tree-row:last-child { border-bottom: 0; }

        .tree-toggle {
            flex: 0 0 28px;
            border: 0;
            border-right: 1px solid #f1f5f9;
            background: #fafafa;
            color: var(--muted);
            cursor: pointer;
            font: inherit;
            font-size: 0.72rem;
            padding: 0;
        }

        .tree-toggle:hover { color: var(--primary); background: var(--primary-bg); }

        .tree-toggle.is-placeholder {
            cursor: default;
            background: transparent;
            border-right-color: transparent;
        }

        .tree-row .row-btn {
            flex: 1;
            min-width: 0;
            border-bottom: 0;
        }

        .list .row-btn .arrow {
            color: #cbd5e1;
            font-size: 0.9rem;
            flex: 0 0 auto;
        }

        .scheme {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 10px;
            background: #fff;
            font-size: 0.83rem;
            line-height: 1.5;
            min-width: 0;
            max-width: 100%;
            overflow-x: clip;
        }

        .scheme-tree { margin-top: 12px; min-width: 0; }

        .scheme-tree p {
            margin: 0 0 8px;
            color: #334155;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .scheme-tree-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin: 4px 0;
            color: #334155;
            min-width: 0;
            max-width: 100%;
        }

        .scheme-tree-text {
            flex: 1;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .scheme-tree-level-0 { margin-left: 0; }
        .scheme-tree-level-1 { margin-left: 14px; }
        .scheme-tree-level-2 { margin-left: 28px; }
        .scheme-tree-level-3 { margin-left: 42px; }
        .scheme-tree-level-4 { margin-left: 56px; }
        .scheme-tree-level-5 { margin-left: 70px; }
        .scheme-tree-level-6 { margin-left: 84px; }
        .scheme-tree-level-7 { margin-left: 98px; }
        .scheme-tree-level-8 { margin-left: 112px; }

        .scheme-folder {
            cursor: pointer;
            user-select: none;
        }

        .scheme-folder:hover {
            background: var(--primary-bg);
            border-radius: 4px;
        }

        .scheme-icon {
            flex: 0 0 auto;
        }

        .scheme-tree-link {
            border: 0;
            background: transparent;
            color: var(--primary);
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-align: left;
            text-decoration: underline;
        }

        .scheme-tree-link:hover { color: #1e40af; }

        .scheme-tree-muted {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .scheme-active {
            background: var(--primary-bg);
            border-radius: 4px;
            font-weight: 500;
        }

        .scheme-sibling {
            cursor: pointer;
            padding: 1px 4px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin: 1px 0;
            min-width: 0;
            max-width: 100%;
        }

        .scheme-siblings {
            padding-left: 28px;
        }

        .scheme-sibling:hover {
            background: #f1f5f9;
            color: var(--primary);
        }

        .scheme-expand-btn {
            flex: 0 0 auto;
            border: 0;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-size: 0.7rem;
            padding: 2px 4px;
            border-radius: 3px;
        }

        .scheme-expand-btn:hover {
            background: var(--primary-bg);
            color: var(--primary);
        }

        .crumb-current {
            color: var(--text);
            font-weight: 500;
        }

        .empty {
            padding: 40px 16px;
            text-align: center;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .empty strong {
            display: block;
            color: var(--text);
            margin-bottom: 4px;
        }

        .toast {
            position: fixed;
            right: 16px;
            bottom: 16px;
            background: #111827;
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            opacity: 0;
            transition: opacity .15s;
            pointer-events: none;
            z-index: 50;
        }

        .toast.show { opacity: 1; }

        .welcome {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 48px 20px;
            text-align: center;
            min-height: 420px;
        }

        .welcome-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .welcome-title { font-size: 1.15rem; font-weight: 600; margin-bottom: 4px; }
        .welcome-sub { font-size: 0.85rem; color: var(--muted); margin-bottom: 16px; }
        .welcome-hint { font-size: 0.85rem; color: var(--muted); }

        .welcome-kbd {
            margin-top: 8px;
            font-size: 0.8rem;
            color: var(--muted);
        }

        .welcome-kbd kbd {
            display: inline-block;
            padding: 1px 6px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: #f8fafc;
            font-family: ui-monospace, Menlo, monospace;
            font-size: 0.85em;
            box-shadow: 0 1px 0 rgba(0,0,0,0.06);
        }

        mark { background: #fef08a; padding: 0 1px; }
        code { font-family: ui-monospace, Menlo, monospace; font-size: 0.9em; }
    </style>
</head>
<body x-data="tnvedApp()" x-init="init()">
    <div class="app">
        <header class="topbar">
            <h1>ТН ВЭД</h1>
            <nav style="display:flex;gap:8px;margin-left:auto;font-size:0.85rem">
                <a href="{{ $okpd2Url }}" style="color:var(--muted);text-decoration:none">ОКПД 2</a>
                <span style="color:var(--border)">|</span>
                <span style="color:var(--primary);font-weight:500">ТН ВЭД</span>
            </nav>
        </header>

        @include('partials.actualization', ['date' => $classifierUpdatedAt])

        @if($totalCount === 0)
            <div class="alert">База пуста — <code>php artisan tnved</code></div>
        @endif

        <div class="layout">
            <aside class="panel">
                <div class="search-row">
                    <input
                        type="search"
                        placeholder="Код или название…"
                        x-model="query"
                        @input.debounce.200ms="onSearchInput()"
                        @keydown.down.prevent="moveFocus(1)"
                        @keydown.up.prevent="moveFocus(-1)"
                        @keydown.enter.prevent="openFocused()"
                        @keydown.escape="clearSearch()"
                        x-ref="searchInput"
                    >
                    <select x-model="sectionFilter" @change="onSearchInput()">
                        <option value="ALL">Все главы</option>
                        <template x-for="section in sections" :key="section.code">
                            <option :value="section.code" x-text="`${section.code} — ${truncate(section.name, 36)}`"></option>
                        </template>
                    </select>
                </div>

                <div class="sidebar-scroll">
                    <div x-show="query.length > 0" x-cloak>
                        <div class="side-label">Поиск</div>
                        <template x-if="searchLoading">
                            <div class="empty" style="padding:16px">…</div>
                        </template>
                        <template x-if="!searchLoading && searchResults.length === 0">
                            <div class="empty" style="padding:16px">Не найдено</div>
                        </template>
                        <template x-for="(item, index) in searchResults" :key="item.code">
                            <button
                                type="button"
                                class="row-btn"
                                :class="{ active: selected?.code === item.code, focused: focusIndex === index }"
                                @click="openCode(item.code, true)"
                                @mouseenter="focusIndex = index"
                            >
                                <span class="code wide" x-html="highlight(item.display_code || item.code)"></span>
                                <span style="flex:1;min-width:0">
                                    <div class="row-text" x-html="highlight(item.name)"></div>
                                </span>
                            </button>
                        </template>
                        <div class="search-foot" x-show="searchTotal > 0">
                            <span x-text="searchTotal"></span> найдено
                        </div>
                    </div>

                    <div x-show="query.length === 0">
                        <div>
                            <div class="side-label">Разделы</div>
                            <template x-if="treeLoading && !rootNodes.length">
                                <div class="tree-loading">Загрузка…</div>
                            </template>
                            <template x-for="section in rootNodes" :key="section.id">
                                <button
                                    type="button"
                                    class="tks-section-row compact"
                                    :class="{ active: isExpanded(section.id) }"
                                    @click="toggleSection(section)"
                                >
                                    <span class="tks-section-label" x-text="section.section_label || section.name"></span>
                                </button>
                            </template>
                        </div>

                        <template x-if="history.length > 0">
                            <div class="side-label">Недавние</div>
                            <template x-for="item in history" :key="item.code">
                                <button type="button" class="row-btn" @click="openCode(item.code, true)">
                                    <span class="code wide" x-text="item.code"></span>
                                    <span class="row-text" x-text="truncate(item.name, 32)"></span>
                                </button>
                            </template>
                        </template>
                    </div>

                    <div class="sidebar-actualization">
                        @include('partials.actualization', ['date' => $mappingsUpdatedAt, 'compact' => true])
                    </div>
                </div>
            </aside>

            <main class="panel main">
                <div class="detail" x-show="!selected" x-cloak>
                    <div class="part-head" style="margin-bottom:12px">
                        <div class="welcome-title" style="margin-bottom:4px">ТН ВЭД ЕАЭС</div>
                        <div class="welcome-sub">Дерево классификатора — как на <a href="https://www.tks.ru/db/tnved/tree/" target="_blank" rel="noopener" style="color:var(--primary)">tks.ru</a></div>
                    </div>

                    <template x-if="treeLoading && !rootNodes.length">
                        <div class="tree-loading">Загрузка разделов…</div>
                    </template>

                    <ul class="tks-product-list" data-tree-list>
                        <template x-for="section in rootNodes" :key="section.id">
                            <li class="tks-product-item" :class="{ 'tks-product-item--expanded': isExpanded(section.id) }" :id="section.id">
                                <button type="button" class="tks-product-head" @click="toggleSection(section)">
                                    <span class="tks-product-chapter" x-text="section.section_label"></span>
                                    <span class="tks-product-section" x-text="section.section_title"></span>
                                </button>

                                <ul class="tks-tree-list" x-show="isExpanded(section.id)" x-cloak>
                                    <template x-for="row in flattenBranch(section.id)" :key="`${row.id}_${row.depth}`">
                                        <li class="tks-tree-item" :class="treeItemClass(row)" :id="row.id" :style="`padding-left:${14 + row.depth * 30}px`">
                                            <button type="button" class="tks-tree-title" @click="onTreeRowClick(row)">
                                                <span class="tks-tree-code" x-show="row.display_code" x-text="row.display_code"></span>
                                                <span class="tks-tree-name" x-text="row.name"></span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </li>
                        </template>
                    </ul>
                </div>

                <div x-show="selected" x-cloak>
                    <template x-if="selected">
                    <div>
                    <ul class="head-breadcrumb" x-show="navigableBreadcrumb().length">
                        <li>
                            <button type="button" @click="goHome()" title="К списку разделов">ТН ВЭД</button>
                        </li>
                        <template x-for="crumb in navigableBreadcrumb()" :key="crumb.code + (crumb.is_section ? '_s' : '')">
                            <li>
                                <template x-if="crumb.code !== selected.code">
                                    <button type="button" :title="crumb.name" @click="openCrumb(crumb)" x-text="crumb.display_code || crumb.code"></button>
                                </template>
                                <template x-if="crumb.code === selected.code">
                                    <span class="crumb-current" :title="crumb.name" x-text="crumb.display_code || crumb.code"></span>
                                </template>
                            </li>
                        </template>
                    </ul>

                    <div class="detail">
                        @include('partials.actualization', ['date' => $classifierUpdatedAt, 'compact' => true])
                        <h1 class="head-title" x-text="selected.name"></h1>

                        <table class="head-table">
                            <thead>
                                <tr>
                                    <th>Классификатор</th>
                                    <th>Код раздела</th>
                                    <th>Код</th>
                                    <th>Наименование</th>
                                    <th>Уровень вложенности,<br>название уровня</th>
                                    <th>Дочерних кодов</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td data-label="Классификатор">ТН ВЭД ЕАЭС</td>
                                    <td data-label="Глава" x-text="selected.section"></td>
                                    <td data-label="Код" x-text="selected.display_code"></td>
                                    <td data-label="Наименование" x-text="selected.name"></td>
                                    <td data-label="Уровень вложенности, название уровня" x-text="levelLabel(selected)"></td>
                                    <td data-label="Дочерних кодов" x-text="children.length"></td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="toolbar">
                            <div class="toolbar-group">
                                <span class="toolbar-label">Скопировать</span>
                                <button type="button" class="btn btn-primary" @click="copyText(selected.display_code)" title="Скопировать код">Код</button>
                                <button type="button" class="btn" @click="copyText(`${selected.display_code} — ${selected.name}`)" title="Скопировать код и название">Код + название</button>
                                <button type="button" class="btn" @click="copyLink()" title="Скопировать ссылку">Ссылка</button>
                            </div>
                            <div class="toolbar-group" x-show="siblings.total > 1 && siblings.index">
                                <span class="toolbar-label">Соседние</span>
                                <button type="button" class="btn" :disabled="!siblings.prev" @click="openCode(siblings.prev)" title="Предыдущий код">←</button>
                                <span style="font-size:0.8rem;color:var(--muted);padding:0 2px" x-text="`${siblings.index} / ${siblings.total}`"></span>
                                <button type="button" class="btn" :disabled="!siblings.next" @click="openCode(siblings.next)" title="Следующий код">→</button>
                            </div>
                        </div>

                        <template x-if="selected.description">
                            <div>
                                <div class="block-title">Примечания</div>
                                <div class="desc" x-text="selected.description"></div>
                            </div>
                        </template>

                        <template x-if="ratesSummary().length">
                            <div>
                                <div class="block-title">Ставки и пошлины</div>
                                <table class="children-table">
                                    <tbody>
                                        <template x-for="row in ratesSummary()" :key="row.label">
                                            <tr>
                                                <td class="td-code" x-text="row.label"></td>
                                                <td x-text="row.value"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <div x-show="relatedOkpd2.length > 0">
                            <div class="block-title">Связанные коды ОКПД 2</div>
                            @include('partials.actualization', ['date' => $mappingsUpdatedAt, 'compact' => true])
                            <table class="children-table">
                                <tbody>
                                    <template x-for="item in relatedOkpd2" :key="item.code">
                                        <tr>
                                            <td class="td-code" x-text="item.code"></td>
                                            <td>
                                                <a :href="`${okpd2Url}?code=${encodeURIComponent(item.code)}`" x-text="item.name || item.code"></a>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div x-show="children.length > 0">
                            <div class="block-title">Уточняющие коды</div>
                            <p class="children-intro" x-text="childrenIntroText()"></p>
                            <table class="children-table">
                                <tbody>
                                    <template x-for="child in children" :key="child.code">
                                        <tr>
                                            <td class="td-code" x-text="child.display_code"></td>
                                            <td>
                                                <button type="button" class="child-link" @click="openCode(child.code)" x-text="child.name"></button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="scheme-tree">
                            <div class="block-title">Схема-дерево</div>
                            <div class="scheme">
                                <p>Схема иерархии в классификаторе ТН ВЭД для кода <span x-text="selected.display_code"></span>:</p>

                                <div class="scheme-tree-row scheme-tree-level-0 scheme-folder" @click="goHome()">
                                    <span class="scheme-icon">📂</span>
                                    <span>ТН ВЭД</span>
                                    <span class="scheme-tree-muted">ЕТТ ЕАЭС</span>
                                </div>

                                <template x-for="(node, idx) in schemeNodes()" :key="`scheme_${node.code}_${idx}`">
                                    <div>
                                        <div class="scheme-tree-row scheme-folder"
                                             :class="[`scheme-tree-level-${Math.min(idx + 1, 8)}`, node.code === selected.code ? 'scheme-active' : '']"
                                             @click="node.code !== selected.code && openCode(node.code)">
                                            <span>↳</span>
                                            <span class="scheme-icon" x-text="schemeNodeIcon(node)"></span>
                                            <span class="scheme-tree-text">
                                                <strong x-text="node.display_code || node.code"></strong>
                                                <span x-text="` — ${node.name}`"></span>
                                                <span class="scheme-tree-muted" x-show="node.code === selected.code"> (текущий уровень)</span>
                                            </span>
                                            <button type="button" class="scheme-expand-btn"
                                                x-show="!node.is_section && schemeSiblings[node.code]?.length > 1"
                                                @click.stop="schemeExpanded[node.code] = !schemeExpanded[node.code]; schemeExpanded = {...schemeExpanded}"
                                                x-text="schemeExpanded[node.code] ? '▼' : '▶'"
                                                :title="schemeExpanded[node.code] ? 'Скрыть соседние коды' : 'Показать соседние коды'">
                                            </button>
                                        </div>

                                        <template x-if="schemeSiblings[node.code]?.length > 1 && schemeExpanded[node.code]">
                                            <div :class="`scheme-tree-level-${Math.min(idx + 1, 8)} scheme-siblings`">
                                                <template x-for="sib in schemeSiblings[node.code].filter(s => s.code !== node.code)" :key="`sib_${sib.code}`">
                                                    <div class="scheme-tree-row scheme-sibling" @click="openCode(sib.code)">
                                                        <span>↳</span>
                                                        <span class="scheme-icon" x-text="schemeLeafIcon(sib.has_children)"></span>
                                                        <span class="scheme-tree-text">
                                                            <span x-text="sib.display_code || sib.code"></span>
                                                            <span class="scheme-tree-muted" x-text="` — ${sib.name}`"></span>
                                                        </span>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="children.length > 0">
                                    <div :class="`scheme-tree-level-${Math.min(schemeNodes().length + 1, 8)} scheme-siblings`">
                                        <template x-for="child in children" :key="`scheme_child_${child.code}`">
                                            <div class="scheme-tree-row scheme-sibling" @click="openCode(child.code)">
                                                <span>↳</span>
                                                <span class="scheme-icon" x-text="schemeLeafIcon(child.has_children)"></span>
                                                <span class="scheme-tree-text">
                                                    <span x-text="child.display_code || child.code"></span>
                                                    <span class="scheme-tree-muted" x-text="` — ${child.name}`"></span>
                                                </span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <div x-show="children.length === 0">
                                    <div class="scheme-tree-row" :class="`scheme-tree-level-${Math.min(schemeNodes().length + 1, 8)}`">
                                        <span>↳</span>
                                        <span>✖</span>
                                        <span>нет уточняющих кодов</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                    </template>
                </div>

            </main>
        </div>
    </div>

    <div class="toast" :class="{ show: toastVisible }" x-text="toastMessage"></div>

    <script>
        function tnvedApp() {
            return {
                sections: @json($sections),
                rootNodes: [],
                treeLoading: false,
                noChildrenIds: [],
                okpd2Url: @json($okpd2Url),
                tnvedUrl: @json($tnvedUrl),
                tnvedShareUrl: @json($tnvedShareUrl),
                totalCount: {{ $totalCount }},
                query: '',
                sectionFilter: 'ALL',
                searchResults: [],
                searchTotal: 0,
                searchLoading: false,
                focusIndex: -1,
                selected: null,
                children: [],
                relatedOkpd2: [],
                siblings: { prev: null, next: null, index: null, total: 0 },
                activeSection: null,
                expandedIds: [],
                childCache: {},
                history: [],
                schemeSiblings: {},
                schemeCollapsed: {},
                schemeExpanded: {},
                toastVisible: false,
                toastMessage: '',

                async init() {
                    this.history = this.loadHistory();
                    await this.loadRootNodes();
                    const code = new URLSearchParams(location.search).get('code');
                    if (code) await this.openCode(code);
                    window.addEventListener('keydown', (e) => {
                        if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                            e.preventDefault();
                            this.$refs.searchInput.focus();
                        }
                    });
                },

                async loadRootNodes() {
                    this.treeLoading = true;
                    try {
                        const data = await (await fetch('/api/tnved/tree')).json();
                        this.rootNodes = data.items || [];
                    } catch {
                        this.showToast('Не удалось загрузить разделы');
                    } finally {
                        this.treeLoading = false;
                    }
                },

                async fetchBranch(nodeId) {
                    const response = await fetch(`/api/tnved/tree/${nodeId}`);
                    if (!response.ok) {
                        throw new Error('branch load failed');
                    }
                    const data = await response.json();
                    return data.items || [];
                },

                onSearchInput() {
                    this.focusIndex = -1;
                    this.search();
                },

                async search() {
                    if (!this.query.trim()) {
                        this.searchResults = [];
                        this.searchTotal = 0;
                        return;
                    }
                    this.searchLoading = true;
                    try {
                        const params = new URLSearchParams({ q: this.query.trim(), section: this.sectionFilter, limit: 50 });
                        const data = await (await fetch(`/api/tnved/search?${params}`)).json();
                        this.searchResults = data.items || [];
                        this.searchTotal = data.total || 0;
                        this.focusIndex = this.searchResults.length ? 0 : -1;
                    } finally {
                        this.searchLoading = false;
                    }
                },

                clearSearch() {
                    this.query = '';
                    this.searchResults = [];
                    this.searchTotal = 0;
                    this.focusIndex = -1;
                },

                moveFocus(delta) {
                    if (!this.searchResults.length) return;
                    const next = this.focusIndex + delta;
                    if (next >= 0 && next < this.searchResults.length) this.focusIndex = next;
                },

                openFocused() {
                    if (this.focusIndex >= 0 && this.searchResults[this.focusIndex]) {
                        this.openCode(this.searchResults[this.focusIndex].code, true);
                    }
                },

                async toggleSection(section) {
                    if (this.isExpanded(section.id)) {
                        this.expandedIds = this.expandedIds.filter(id => id !== section.id);
                        return;
                    }

                    if (!this.childCache[section.id]) {
                        try {
                            this.childCache[section.id] = await this.fetchBranch(section.id);
                            this.childCache = { ...this.childCache };
                        } catch {
                            this.showToast('Не удалось загрузить раздел');
                            return;
                        }
                    }

                    this.expandedIds = [...this.expandedIds, section.id];
                    this.selected = null;
                    this.clearCodeFromUrl();
                },

                async openCode(code) {
                    const response = await fetch(`/api/tnved/${encodeURIComponent(code)}`);
                    if (!response.ok) {
                        this.showToast('Код не найден');
                        return;
                    }
                    const data = await response.json();

                    const item = data.item;
                    const children = data.children || [];

                    this.selected = item;
                    this.children = children;
                    this.relatedOkpd2 = data.related_okpd2 || [];
                    this.siblings = data.siblings || { prev: null, next: null, index: null, total: 0 };
                    this.schemeSiblings = data.scheme_siblings || {};
                    this.activeSection = item.section;
                    this.schemeCollapsed = {};
                    this.schemeExpanded = {};
                    this.query = '';
                    this.searchResults = [];
                    this.pushHistory(item);

                    this.syncCodeToUrl(code);
                },

                shareUrl(code) {
                    const url = new URL(this.tnvedShareUrl, location.origin);
                    if (code) {
                        url.searchParams.set('code', code);
                    } else {
                        url.searchParams.delete('code');
                    }

                    return url.toString();
                },

                syncCodeToUrl(code) {
                    const url = new URL(location.href);
                    if (code) {
                        url.searchParams.set('code', code);
                    } else {
                        url.searchParams.delete('code');
                    }
                    history.replaceState({}, '', url.pathname + url.search);
                },

                goHome() {
                    this.selected = null;
                    this.activeSection = null;
                    this.children = [];
                    this.clearCodeFromUrl();
                },

                async openCrumb(crumb) {
                    if (crumb.is_section) {
                        const section = this.rootNodes.find(n => n.section_label && crumb.name?.includes(n.section_title?.slice(0, 12)));
                        if (section) {
                            await this.toggleSection(section);
                        }
                        return;
                    }

                    await this.openCode(crumb.code);
                },

                resetTreeExpansion() {
                    this.expandedIds = [];
                    this.noChildrenIds = [];
                },

                treeItemClass(row) {
                    const classes = ['tks-tree-item'];
                    if (row.is_group) classes.push('tks-tree-item--folder');
                    if (row.is_leaf) classes.push('tks-tree-item--leaf');
                    if (this.canExpand(row)) classes.push('tks-tree-item--expandable');
                    return classes.join(' ');
                },

                canExpand(row) {
                    return (row.is_group || row.has_children) && !this.noChildrenIds.includes(row.id);
                },

                branchItems(sectionId) {
                    return this.childCache[sectionId] || [];
                },

                flattenBranch(sectionId) {
                    return this.flattenItems(this.branchItems(sectionId));
                },

                isExpanded(nodeId) {
                    return this.expandedIds.includes(nodeId);
                },

                onTreeRowClick(row) {
                    if (row.is_leaf && row.code) {
                        this.openCode(row.code);
                        return;
                    }

                    if (this.canExpand(row)) {
                        this.toggleExpand(row.id);
                        return;
                    }

                    if (row.code) {
                        this.openCode(row.code);
                    }
                },

                async toggleExpand(nodeId) {
                    if (this.isExpanded(nodeId)) {
                        this.expandedIds = this.expandedIds.filter(id => id !== nodeId);
                        return;
                    }

                    if (!this.childCache[nodeId]) {
                        try {
                            this.childCache[nodeId] = await this.fetchBranch(nodeId);
                            this.childCache = { ...this.childCache };
                        } catch {
                            this.showToast('Не удалось загрузить уровень');
                            this.childCache[nodeId] = [];
                            this.childCache = { ...this.childCache };
                        }
                    }

                    if (!this.childCache[nodeId]?.length) {
                        this.noChildrenIds = [...this.noChildrenIds, nodeId];
                        return;
                    }

                    this.expandedIds = [...this.expandedIds, nodeId];
                },

                flattenItems(items, depth = 0) {
                    const rows = [];

                    for (const item of items || []) {
                        rows.push({ ...item, depth });

                        if (this.isExpanded(item.id) && this.childCache[item.id]?.length) {
                            rows.push(...this.flattenItems(this.childCache[item.id], depth + 1));
                        }
                    }

                    return rows;
                },

                navigableBreadcrumb() {
                    if (!this.selected?.breadcrumb) return [];

                    return this.selected.breadcrumb.filter((crumb, index, all) => {
                        if (index === all.length - 1) {
                            return true;
                        }

                        return !crumb.is_section || this.sections.some(s => s.code === crumb.code);
                    });
                },

                clearCodeFromUrl() {
                    this.syncCodeToUrl(null);
                },

                loadHistory() {
                    try { return JSON.parse(localStorage.getItem('tnved_history') || '[]'); }
                    catch { return []; }
                },

                pushHistory(item) {
                    this.history = [{ code: item.code, name: item.name }, ...this.history.filter(h => h.code !== item.code)].slice(0, 6);
                    localStorage.setItem('tnved_history', JSON.stringify(this.history));
                },

                async copyText(text) {
                    try { await navigator.clipboard.writeText(text); this.showToast('Скопировано'); }
                    catch { this.showToast('Ошибка копирования'); }
                },

                copyLink() {
                    this.copyText(this.shareUrl(this.selected.code));
                },

                highlight(text) {
                    if (!text || !this.query.trim()) return this.escapeHtml(text || '');
                    const q = this.query.trim();
                    const pattern = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
                    return this.escapeHtml(text).replace(pattern, '<mark>$1</mark>');
                },

                escapeHtml(text) {
                    return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                },

                showToast(msg) {
                    this.toastMessage = msg;
                    this.toastVisible = true;
                    setTimeout(() => this.toastVisible = false, 1500);
                },

                truncate(text, max) {
                    return !text || text.length <= max ? (text || '') : text.slice(0, max) + '…';
                },

                sectionTitle(code) {
                    const s = this.sections.find(x => x.code === code);
                    return s ? s.name : code;
                },

                levelLabel(item) {
                    if (!item) return '';
                    const level = item.nesting_level ?? item.level;
                    const name = item.level_name ?? 'уровень';
                    return `${level}, ${name}`;
                },

                schemeNodes() {
                    if (!this.selected?.breadcrumb?.length) return [];
                    return this.selected.breadcrumb
                        .map(node => ({
                            code: node.code,
                            display_code: node.display_code || node.code,
                            name: node.name || node.code,
                            is_section: !!node.is_section,
                        }));
                },

                schemeNodeIcon(node) {
                    if (node.is_section) {
                        return '📂';
                    }

                    if (node.code === this.selected?.code) {
                        return this.children.length > 0 ? '📂' : '📄';
                    }

                    return '📁';
                },

                schemeLeafIcon(hasChildren) {
                    return hasChildren ? '📁' : '📄';
                },

                toggleSchemeLevel(level) {
                    this.schemeCollapsed[level] = !this.schemeCollapsed[level];
                    this.schemeCollapsed = { ...this.schemeCollapsed };
                },

                isSchemeHidden(level) {
                    for (let l = 0; l < level; l++) {
                        if (this.schemeCollapsed[l]) return true;
                    }
                    return false;
                },

                childrenIntroText() {
                    if (!this.selected) {
                        return '';
                    }

                    const count = this.children.length;
                    const mod10 = count % 10;
                    const mod100 = count % 100;
                    let word = 'кодов';

                    if (mod10 === 1 && mod100 !== 11) {
                        word = 'код';
                    } else if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
                        word = 'кода';
                    }

                    return `Запись в классификаторе с кодом ${this.selected.display_code} содержит ${count} уточняющих (дочерних) ${word}.`;
                },

                ratesSummary() {
                    const rates = this.selected?.rates;
                    if (!rates || typeof rates !== 'object') return [];

                    const rows = [];
                    const imp = rates.IMP;
                    const vat = rates.MIN;

                    if (imp !== undefined && imp !== null && imp !== '') {
                        rows.push({ label: 'Импортная пошлина', value: imp === 0 || imp === '0' ? 'нет' : `${imp} %` });
                    }

                    if (vat !== undefined && vat !== null && vat !== '') {
                        rows.push({ label: 'НДС', value: `${vat} %` });
                    }

                    if (rates.AKC !== undefined && rates.AKC !== null && rates.AKC !== '') {
                        rows.push({ label: 'Акциз', value: String(rates.AKC) });
                    }

                    return rows;
                },

                renderSeeAlso(text) {
                    if (!text) return '';

                    const escaped = this.escapeHtml(text);

                    return escaped.replace(/см\.\s*([0-9]{2}(?:\.[0-9]+)*)/gi, (match, code) => {
                        const safeCode = this.escapeHtml(code);
                        const safeMatch = this.escapeHtml(match);

                        return `<button type="button" class="scheme-tree-link" data-code="${safeCode}">${safeMatch}</button>`;
                    });
                },

                handleSeeAlsoClick(event) {
                    const link = event.target.closest('[data-code]');

                    if (!link) {
                        return;
                    }

                    event.preventDefault();
                    this.openCode(link.dataset.code);
                },
            };
        }
    </script>
</body>
</html>
