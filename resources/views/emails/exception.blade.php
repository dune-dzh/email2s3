<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error – Email Search</title>
    <style>
        :root { --text: #1e293b; --muted: #64748b; --danger: #dc2626; --border: #e2e8f0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            background: #f8fafc;
            color: var(--text);
            padding: 2rem 1.5rem;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { font-size: 1.25rem; color: var(--danger); margin-bottom: 0.5rem; }
        .class { font-size: 0.85rem; color: var(--muted); margin-bottom: 1rem; }
        .message {
            background: rgba(249, 115, 115, 0.15);
            border: 1px solid var(--danger);
            border-radius: 0.5rem;
            padding: 1rem;
            margin: 0.5rem 0;
            word-break: break-word;
            white-space: pre-wrap;
        }
        .location { font-size: 0.85rem; color: var(--muted); margin-top: 0.5rem; }
        .trace {
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--muted);
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 40vh;
            overflow: auto;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Exception</h1>
    <p class="class">{{ $class }}</p>
    <div class="message">{{ $message }}</div>
    <p class="location">{{ $file }} (line {{ $line }})</p>
    @if(!empty($trace))
        <pre class="trace">{{ $trace }}</pre>
    @endif
</div>
</body>
</html>
