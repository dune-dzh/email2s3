<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Search & Migration Dashboard</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --bg-card: #ffffff;
            --accent: #4f46e5;
            --accent-soft: rgba(79,70,229,0.15);
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --danger: #dc2626;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            overflow-x: hidden;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(to bottom right, #f1f5f9, #e2e8f0);
            color: var(--text);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: clamp(0.75rem, 3vw, 1.5rem);
            min-width: 0;
            overflow-x: hidden;
        }
        h1 {
            font-size: clamp(1.35rem, 4vw, 1.8rem);
            margin-bottom: 0.25rem;
            word-break: break-word;
        }
        .subtitle {
            color: var(--muted);
            margin-bottom: 1.5rem;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }
        .layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(1rem, 2vw, 1.5rem);
            align-items: start;
            min-width: 0;
        }
        @media (min-width: 961px) {
            .layout {
                grid-template-columns: 2fr minmax(0, 20rem);
            }
            .layout > .card-filters {
                order: -1;
            }
        }
        .card {
            background: var(--bg-card);
            border-radius: clamp(0.75rem, 2vw, 1rem);
            border: 1px solid var(--border);
            padding: clamp(0.75rem, 2.5vw, 1.25rem) clamp(1rem, 3vw, 1.5rem);
            min-width: 0;
        }
        .card-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .card-header h2 {
            font-size: 1rem;
            margin: 0;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            font-size: 0.7rem;
            background: #f1f5f9;
            border: 1px solid var(--border);
            color: var(--muted);
            gap: 0.35rem;
        }
        .pill-dot {
            width: 0.35rem;
            height: 0.35rem;
            border-radius: 999px;
            background: #22c55e;
        }
        form {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
            align-items: end;
        }
        @media (max-width: 960px) {
            form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 480px) {
            form {
                grid-template-columns: 1fr;
            }
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            font-size: 0.8rem;
        }
        label {
            color: var(--muted);
        }
        input[type="text"],
        input[type="date"] {
            background: #fff;
            border-radius: 0.55rem;
            border: 1px solid var(--border);
            padding: 0.45rem 0.6rem;
            color: var(--text);
            font-size: 0.8rem;
        }
        input[type="text"]:focus,
        input[type="date"]:focus {
            outline: none;
            border-color: var(--accent);
        }
        button {
            border: 1px solid var(--accent);
            border-radius: 0.55rem;
            padding: 0.55rem 0.9rem;
            font-size: 0.8rem;
            cursor: pointer;
            background: var(--accent);
            color: white;
            font-weight: 500;
        }
        button:hover {
            background: #4338ca;
            border-color: #4338ca;
        }
        .field-actions {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
            align-items: center;
            grid-column: 1 / -1;
        }
        .btn-clear {
            display: inline-block;
            padding: 0.55rem 0.9rem;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--muted);
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-clear:hover {
            color: var(--text);
            border-color: var(--text);
        }
        .table-wrap {
            margin: 1rem 0;
            min-width: 0;
        }
        .emails-list-partial table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: clamp(0.7rem, 1.5vw, 0.8rem);
        }
        .emails-list-partial th,
        .emails-list-partial td {
            padding: clamp(0.4rem, 1vw, 0.55rem) clamp(0.35rem, 1vw, 0.5rem);
            text-align: left;
            border-bottom: 1px solid var(--border);
            overflow-wrap: break-word;
            word-break: break-word;
        }
        .emails-list-partial .col-id { width: 7%; }
        .emails-list-partial .col-sender { width: 18%; }
        .emails-list-partial .col-receiver { width: 18%; }
        .emails-list-partial .col-subject { width: 20%; }
        .emails-list-partial .col-created { width: 14%; }
        .emails-list-partial .col-status { width: 8%; }
        .emails-list-partial .col-attachments { width: 11%; }
        .emails-list-partial .col-body { width: 4%; }
        th {
            color: var(--muted);
            font-weight: 500;
            font-size: 0.75rem;
        }
        tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.1rem 0.45rem;
            border-radius: 999px;
            font-size: 0.7rem;
            border: 1px solid var(--border);
            white-space: nowrap;
        }
        .status-pending {
            color: var(--muted);
        }
        .status-migrating {
            color: #facc15;
            border-color: #facc15;
        }
        .status-migrated {
            color: #22c55e;
            border-color: #22c55e;
        }
        .attachments-cell {
            max-width: min(180px, 40vw);
            font-size: 0.75rem;
        }
        .attachment-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .attachment-link {
            color: var(--accent);
            text-decoration: none;
        }
        .attachment-link:hover {
            text-decoration: underline;
        }
        .attachment-source {
            margin-left: 0.35rem;
            color: var(--muted);
            font-size: 0.65rem;
        }
        .muted {
            color: var(--muted);
        }
        .body-view-btn {
            background: var(--accent-soft);
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 0.25rem 0.5rem;
            border-radius: 0.4rem;
            font-size: 0.75rem;
            cursor: pointer;
        }
        .body-view-btn:hover {
            background: var(--accent);
            color: white;
        }
        .body-viewer-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .body-viewer-overlay.is-open {
            display: flex;
        }
        .body-viewer-modal {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: clamp(0.5rem, 2vw, 1rem);
            max-width: min(800px, 95vw);
            width: 95vw;
            max-height: 90vh;
            height: 90vh;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        @media (max-width: 480px) {
            .body-viewer-overlay {
                padding: 0;
                align-items: stretch;
            }
            .body-viewer-modal {
                width: 100%;
                max-width: 100%;
                height: 100%;
                max-height: 100%;
                border-radius: 0;
            }
        }
        .body-viewer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
        }
        .body-viewer-header > div {
            min-width: 0;
        }
        .body-viewer-source {
            display: block;
            font-size: 0.7rem;
            color: var(--muted);
            margin-top: 0.2rem;
            font-weight: normal;
        }
        .body-viewer-title {
            font-size: 1rem;
            margin: 0;
            color: var(--text);
        }
        .body-viewer-close {
            background: none;
            border: none;
            color: var(--muted);
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            padding: 0 0.25rem;
        }
        .body-viewer-close:hover {
            color: var(--text);
        }
        .body-viewer-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: clamp(0.5rem, 2vw, 1rem);
            border-bottom: 1px solid var(--border);
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        @media (max-width: 480px) {
            .body-viewer-toggle,
            .body-viewer-downloads {
                flex: 1 1 auto;
            }
        }
        .body-viewer-toggle {
            display: flex;
            gap: 0.25rem;
        }
        .body-viewer-tab {
            padding: 0.35rem 0.75rem;
            border-radius: 0.4rem;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            font-size: 0.8rem;
            cursor: pointer;
        }
        .body-viewer-tab:hover, .body-viewer-tab.active {
            border-color: var(--accent);
            color: var(--accent);
        }
        .body-viewer-tab.active {
            background: var(--accent-soft);
        }
        .body-viewer-downloads {
            display: flex;
            gap: 0.5rem;
        }
        .body-viewer-dl {
            padding: 0.35rem 0.6rem;
            border-radius: 0.4rem;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font-size: 0.75rem;
            cursor: pointer;
        }
        .body-viewer-dl:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .body-viewer-content-wrap {
            flex: 1;
            min-height: 200px;
            max-height: 60vh;
            overflow: auto;
            padding: clamp(0.5rem, 2vw, 1rem);
            -webkit-overflow-scrolling: touch;
        }
        @media (max-width: 480px) {
            .body-viewer-content-wrap {
                max-height: none;
            }
        }
        .body-viewer-iframe {
            width: 100%;
            min-height: 280px;
            height: 100%;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: #fff;
        }
        .body-viewer-text {
            margin: 0;
            padding: 1rem;
            font-size: 0.8rem;
            white-space: pre-wrap;
            word-break: break-word;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text);
            max-height: 55vh;
            overflow: auto;
        }
        .pagination {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 1rem;
        }
        .pagination a,
        .pagination span {
            padding: clamp(0.2rem, 1vw, 0.25rem) clamp(0.35rem, 1vw, 0.5rem);
            border-radius: 0.4rem;
            border: 1px solid var(--border);
            font-size: clamp(0.7rem, 1.5vw, 0.75rem);
            color: var(--muted);
            text-decoration: none;
        }
        .pagination .active {
            background: var(--accent);
            color: white;
            border-color: transparent;
        }
        .migration-dashboard-card {
            max-width: 100%;
            min-width: 0;
            overflow-wrap: break-word;
        }
        .dashboard-caption {
            margin-top: 1rem;
            font-size: 0.7rem;
            color: var(--muted);
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(5.5rem, 1fr));
            gap: 0.75rem;
            width: 100%;
            max-width: 100%;
        }
        @media (max-width: 700px) {
            .stat-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (min-width: 701px) and (max-width: 960px) {
            .stat-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        .stat {
            min-width: 0;
            padding: clamp(0.5rem, 1.5vw, 0.75rem) clamp(0.6rem, 1.5vw, 0.9rem);
            border-radius: 0.75rem;
            background: linear-gradient(135deg, rgba(79,70,229,0.08), #fff);
            border: 1px solid var(--border);
            overflow: visible;
        }
        .stat-label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 0.25rem;
            white-space: nowrap;
        }
        .stat-value {
            display: inline-block;
            font-size: clamp(0.9rem, 2vw, 1.05rem);
            font-weight: 600;
            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
        }
        .stat-caption {
            font-size: 0.65rem;
            color: var(--muted);
            margin-top: 0.25rem;
            line-height: 1.3;
        }
        .stat-remaining .stat-value {
            color: var(--danger);
        }
        th a.sort-link {
            color: var(--accent);
            text-decoration: underline;
            text-underline-offset: 2px;
            font-weight: 500;
            cursor: pointer;
        }
        th a.sort-link:hover {
            color: #4338ca;
        }
        th a.sort-link .sort-arrow {
            margin-left: 0.2rem;
            font-size: 0.7em;
            color: var(--muted);
            white-space: nowrap;
        }
        @media (min-width: 961px) {
            th.th-sortable {
                white-space: nowrap;
            }
        }
        td.td-id {
            white-space: nowrap;
        }
        @media (max-width: 700px) {
            th.th-sortable,
            th a.sort-link {
                white-space: normal;
                word-break: break-word;
            }
        }
        th.th-status {
            white-space: nowrap;
        }
        th a.sort-link.active .sort-arrow {
            color: var(--accent);
        }
        .ws-status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.7rem;
            color: var(--muted);
        }
        .ws-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: #f97316;
        }
        .ws-dot.connected {
            background: #22c55e;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Email search</h1>
    <p class="subtitle">Search emails and monitor S3 migration progress in real time.</p>

    <div class="layout">
        <div class="card migration-dashboard-card">
            <div class="card-header">
                <h2>Migration dashboard</h2>
                <div>
                    <span class="ws-status">
                        <span id="ws-dot" class="ws-dot"></span>
                        <span id="ws-label">Connecting...</span>
                    </span>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">Migrated</div>
                    <div id="stat-migrated" class="stat-value">{{ number_format($stats['migrated']) }}</div>
                    <div class="stat-caption">Completed uploads</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Migrating</div>
                    <div id="stat-migrating" class="stat-value">{{ number_format($stats['migrating']) }}</div>
                    <div class="stat-caption">In progress</div>
                </div>
                <div class="stat stat-remaining">
                    <div class="stat-label">Remaining</div>
                    <div id="stat-remaining" class="stat-value">
                        {{ number_format($stats['pending']) }}
                    </div>
                    <div class="stat-caption">
                        Out of <span id="stat-total">{{ number_format($stats['total']) }}</span> total
                    </div>
                </div>
            </div>

            <p class="dashboard-caption">Stats update in real time via Laravel Reverb (every second). Shows progress while seeding and during migration.</p>
        </div>

        <div class="card card-filters">
            <div class="card-header">
                <h2>Filters</h2>
                <span class="pill">
                    <span class="pill-dot"></span>
                    <span id="emails-matching-count">{{ number_format($emails->total()) }}</span> matching
                </span>
            </div>

            <form method="GET" action="{{ route('emails.index') }}">
                <div class="field">
                    <label for="sender_email">Sender email</label>
                    <input type="text" id="sender_email" name="sender_email"
                           value="{{ $filters['sender_email'] ?? '' }}"
                           placeholder="sender@example.com">
                </div>
                <div class="field">
                    <label for="receiver_email">Receiver email</label>
                    <input type="text" id="receiver_email" name="receiver_email"
                           value="{{ $filters['receiver_email'] ?? '' }}"
                           placeholder="receiver@example.com">
                </div>
                <div class="field">
                    <label for="date_from">Created from</label>
                    <input type="date" id="date_from" name="date_from"
                           value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="field">
                    <label for="date_to">Created to</label>
                    <input type="date" id="date_to" name="date_to"
                           value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="field field-actions">
                    <a href="{{ route('emails.index') }}" class="btn btn-clear">Clear filters</a>
                    <button type="submit">Apply filters</button>
                </div>
            </form>

            <div id="emails-list-container" data-emails-index-url="{{ route('emails.index') }}">
                @include('emails.partials.list', ['emails' => $emails, 'fileMap' => $fileMap, 'sort' => $sort ?? 'id', 'sortDir' => $sortDir ?? 'asc'])
            </div>
        </div>
    </div>
</div>

{{-- Body viewer modal --}}
<div id="body-viewer-overlay" class="body-viewer-overlay" aria-hidden="true">
    <div class="body-viewer-modal" role="dialog" aria-labelledby="body-viewer-title">
        <div class="body-viewer-header">
            <div>
                <h2 id="body-viewer-title" class="body-viewer-title">Email body</h2>
                <span id="body-viewer-source" class="body-viewer-source" aria-live="polite"></span>
            </div>
            <button type="button" class="body-viewer-close" id="body-viewer-close" aria-label="Close">&times;</button>
        </div>
        <div class="body-viewer-toolbar">
            <div class="body-viewer-toggle">
                <button type="button" class="body-viewer-tab active" data-mode="html">HTML</button>
                <button type="button" class="body-viewer-tab" data-mode="text">Plain text</button>
            </div>
            <div class="body-viewer-downloads">
                <button type="button" class="body-viewer-dl" id="body-dl-html">Download as HTML</button>
                <button type="button" class="body-viewer-dl" id="body-dl-txt">Download as TXT</button>
            </div>
        </div>
        <div class="body-viewer-content-wrap">
            <iframe id="body-viewer-iframe" class="body-viewer-iframe" title="HTML content" sandbox="allow-same-origin"></iframe>
            <pre id="body-viewer-text" class="body-viewer-text" style="display: none;"></pre>
        </div>
    </div>
</div>

<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.min.js"></script>
<script>
    (function () {
        const dot = document.getElementById('ws-dot');
        const label = document.getElementById('ws-label');
        const elMigrated = document.getElementById('stat-migrated');
        const elMigrating = document.getElementById('stat-migrating');
        const elRemaining = document.getElementById('stat-remaining');
        const elTotal = document.getElementById('stat-total');

        const reverbKey = "{{ config('broadcasting.connections.reverb.key', env('REVERB_APP_KEY')) }}";
        let reverbHost = "{{ config('broadcasting.connections.reverb.options.host', env('REVERB_HOST', 'localhost')) }}";
        const reverbPort = "{{ config('broadcasting.connections.reverb.options.port', env('REVERB_PORT', 6001)) }}";
        const reverbScheme = "{{ config('broadcasting.connections.reverb.options.scheme', env('REVERB_SCHEME', 'http')) }}";
        if (reverbHost === 'localhost' || reverbHost === '127.0.0.1') {
            reverbHost = window.location.hostname || reverbHost;
        }

        let wsConnected = false;

        function setStatus(connected, polling) {
            if (!dot || !label) return;
            wsConnected = connected;
            if (connected) {
                dot.classList.add('connected');
                label.textContent = 'Live';
            } else if (polling) {
                dot.classList.add('connected');
                label.textContent = 'Live (polling)';
            } else {
                dot.classList.remove('connected');
                label.textContent = 'Connecting...';
            }
        }

        function updateStats(data) {
            if (data && typeof data.migrated === 'number') elMigrated.textContent = data.migrated.toLocaleString();
            if (data && typeof data.migrating === 'number') elMigrating.textContent = data.migrating.toLocaleString();
            if (data && typeof data.pending === 'number') elRemaining.textContent = data.pending.toLocaleString();
            if (data && typeof data.total === 'number' && elTotal) elTotal.textContent = data.total.toLocaleString();
        }

        var listContainer = document.getElementById('emails-list-container');
        var matchingCountEl = document.getElementById('emails-matching-count');
        function refreshList(url) {
            if (!listContainer) return;
            var u = url || (window.location.pathname + (window.location.search ? window.location.search + '&' : '?') + 'partial=1');
            if (u.indexOf('partial=1') === -1) {
                u += (u.indexOf('?') >= 0 ? '&' : '?') + 'partial=1';
            }
            fetch(u, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    listContainer.innerHTML = html;
                    var partial = listContainer.querySelector('.emails-list-partial');
                    if (partial && matchingCountEl && partial.dataset.total !== undefined) {
                        matchingCountEl.textContent = Number(partial.dataset.total).toLocaleString();
                    }
                })
                .catch(function () {});
        }

        var statsUrl = '{{ route("emails.stats") }}';
        function pollStats() {
            fetch(statsUrl, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    updateStats(data);
                    if (!wsConnected) setStatus(false, true);
                })
                .catch(function () {});
            refreshList();
        }
        pollStats();
        setInterval(pollStats, 2000);

        if (!reverbKey || !reverbHost) {
            setStatus(false, true);
        } else {
            window.Pusher = Pusher;
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: reverbKey,
                wsHost: reverbHost,
                wsPort: reverbPort,
                wssPort: reverbPort,
                forceTLS: reverbScheme === 'https',
                enabledTransports: ['ws', 'wss'],
            });

            window.Echo.channel('migration-stats')
                .listen('.MigrationStatsUpdated', function (e) {
                    setStatus(true, false);
                    updateStats(e);
                    refreshList();
                })
                .error(function () {
                    setStatus(false, true);
                })
                .subscribed(function () {
                    setStatus(true, false);
                });
        }
    })();
</script>
<script>
    (function () {
        const overlay = document.getElementById('body-viewer-overlay');
        const titleEl = document.getElementById('body-viewer-title');
        const iframe = document.getElementById('body-viewer-iframe');
        const textEl = document.getElementById('body-viewer-text');
        const closeBtn = document.getElementById('body-viewer-close');
        const tabs = document.querySelectorAll('.body-viewer-tab');
        const dlHtml = document.getElementById('body-dl-html');
        const dlTxt = document.getElementById('body-dl-txt');

        let currentHtml = '';
        let currentText = '';
        let currentSubject = '';
        let currentEmailId = null;

        function openViewer() {
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
        }
        function closeViewer() {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
        }

        function setMode(mode) {
            tabs.forEach(function (t) {
                t.classList.toggle('active', t.getAttribute('data-mode') === mode);
            });
            if (mode === 'html') {
                iframe.style.display = 'block';
                textEl.style.display = 'none';
                iframe.srcdoc = currentHtml || '<p>No content</p>';
            } else {
                iframe.style.display = 'none';
                textEl.style.display = 'block';
                textEl.textContent = currentText || 'No content';
            }
        }

        document.addEventListener('click', function (e) {
            var btn = e.target && e.target.closest('.body-view-btn');
            if (!btn) return;
            var id = btn.getAttribute('data-email-id');
            var subject = btn.getAttribute('data-subject') || '';
            var url = '{{ url("/emails") }}/' + id + '/body';
            var sourceEl = document.getElementById('body-viewer-source');
            currentEmailId = id;
            currentSubject = subject;
            titleEl.textContent = 'Email #' + id + (subject ? ' – ' + subject.substring(0, 50) + (subject.length > 50 ? '…' : '') : '');
            if (sourceEl) sourceEl.textContent = '';
            overlay.classList.add('is-open');
            iframe.style.display = 'block';
            textEl.style.display = 'none';
            textEl.textContent = 'Loading…';
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    currentHtml = data.html || '';
                    currentText = data.plain_text || '';
                    if (sourceEl) {
                        sourceEl.textContent = data.source === 's3' ? 'Viewed from S3' : 'Viewed from database';
                    }
                    setMode('html');
                })
                .catch(function () {
                    currentHtml = '';
                    currentText = 'Failed to load body.';
                    if (sourceEl) sourceEl.textContent = '';
                    setMode('html');
                });
        });

        closeBtn.addEventListener('click', closeViewer);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeViewer();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeViewer();
        });

        tabs.forEach(function (t) {
            t.addEventListener('click', function () {
                setMode(this.getAttribute('data-mode'));
            });
        });

        function downloadBlob(content, filename, mime) {
            var blob = new Blob([content], { type: mime });
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = filename;
            a.click();
            URL.revokeObjectURL(a.href);
        }

        dlHtml.addEventListener('click', function () {
            var name = 'email-' + (currentEmailId || 'body') + '.html';
            downloadBlob(currentHtml, name, 'text/html;charset=utf-8');
        });
        dlTxt.addEventListener('click', function () {
            var name = 'email-' + (currentEmailId || 'body') + '.txt';
            downloadBlob(currentText, name, 'text/plain;charset=utf-8');
        });
    })();
</script>
</body>
</html>

