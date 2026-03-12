<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Search – Error</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --border: #e2e8f0;
            --text: #1e293b;
            --muted: #64748b;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(to bottom right, #f1f5f9, #e2e8f0);
            color: var(--text);
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .message {
            background: rgba(249, 115, 115, 0.1);
            border: 1px solid var(--danger);
            border-radius: 0.75rem;
            padding: 1rem;
            font-size: 0.85rem;
            word-break: break-word;
            margin: 1rem 0;
        }
        .hint {
            color: var(--muted);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Database error</h1>
    <p class="hint">The web UI could not load. Details below.</p>
    <div class="message">{{ $message }}</div>
    <p class="hint">{{ $hint }}</p>
</div>
</body>
</html>
