<?php
/**
 * Ava CMS - Setup Helper
 */
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Setup Required - Ava CMS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 520px;
            text-align: center;
        }
        .icon {
            width: 48px;
            height: 48px;
            background: #8b5cf6;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
        }
        .icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }
        h1 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 1.75rem;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1rem;
            text-align: left;
        }
        .card h2 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .card p {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }
        code {
            background: #f1f5f9;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-size: 0.8125rem;
            color: #334155;
        }
        .example {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #f1f5f9;
            font-size: 0.8125rem;
        }
        .good { color: #16a34a; }
        .bad { color: #dc2626; }
        .or {
            color: #94a3b8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0.75rem 0;
        }
        .footer {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .footer a {
            color: #8b5cf6;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
        </div>
        
        <h1>Setup Required</h1>
        <p class="subtitle">
            Ava keeps sensitive files separate from public files for security.<br>
            Only the <code>public</code> folder should be web-accessible.
        </p>

        <div class="card">
            <h2>Option 1: Reorganize the files</h2>
            <p>
                Move Ava to a folder outside your web directory, then copy only 
                the contents of <code>public/</code> into your web folder 
                (e.g. <code>public_html</code> or <code>public</code>).
            </p>
        </div>

        <div class="or">— or —</div>

        <div class="card">
            <h2>Option 2: Change your document root</h2>
            <p>
                Point your site's document root to Ava's <code>public</code> folder.
            </p>
            <div class="example">
                <span class="bad">✗</span> <code>/home/user/ava</code><br>
                <span class="good">✓</span> <code>/home/user/ava/public</code>
            </div>
        </div>

        <div class="footer">
            <a href="https://ava.addy.zone/docs/hosting">View full hosting guide →</a>
        </div>
    </div>
</body>
</html>
<?php exit;
