
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($title ?? 'Something Went Wrong'); ?></title>
    <style>
        :root {
            --ink: #0f172a; --muted: #64748b; --line: #e2e8f0;
            --page: #f8fafc; --card: #ffffff; --brand: #2563eb; --brand-dark: #1d4ed8;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px; background: var(--page); color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%; max-width: 30rem; background: var(--card); border: 1px solid var(--line);
            border-radius: 16px; padding: 32px; text-align: center;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04), 0 8px 24px rgba(15, 23, 42, .06);
        }
        .code {
            display: inline-block; font-size: 12px; font-weight: 600; letter-spacing: .08em;
            text-transform: uppercase; color: var(--muted); border: 1px solid var(--line);
            border-radius: 999px; padding: 4px 12px; margin-bottom: 18px;
        }
        h1 { margin: 0 0 10px; font-size: 22px; line-height: 1.3; }
        p { margin: 0 0 8px; color: var(--muted); font-size: 15px; line-height: 1.6; }
        .actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        a.btn {
            display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
            font-size: 14px; font-weight: 500; padding: 9px 16px; border-radius: 10px;
            border: 1px solid transparent; transition: background-color .15s, border-color .15s, color .15s;
        }
        /* Borders present in both states, so hovering never shifts the layout. */
        a.primary { background: var(--brand); color: #fff; border-color: var(--brand); }
        a.primary:hover { background: var(--brand-dark); border-color: var(--brand-dark); }
        a.secondary { background: #fff; color: #334155; border-color: var(--line); }
        a.secondary:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .detail {
            margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line);
            font-size: 13px; color: var(--muted); word-break: break-word;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ink: #e2e8f0; --muted: #94a3b8; --line: #1e293b;
                --page: #0b1220; --card: #0f172a;
            }
            a.secondary { background: #0f172a; color: #cbd5e1; }
            a.secondary:hover { background: #1e293b; border-color: #334155; }
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="code">Error <?php echo e($code); ?></span>
        <h1><?php echo e($title); ?></h1>
        <?php echo e($slot); ?>


        <div class="actions">
            <?php if(($showLogin ?? false)): ?>
                <a class="btn primary" href="<?php echo e(url('/login')); ?>">Sign In</a>
            <?php else: ?>
                <a class="btn primary" href="<?php echo e(url('/')); ?>">Back To My Servers</a>
            <?php endif; ?>
            <a class="btn secondary" href="javascript:history.back()">Go Back</a>
        </div>

        <?php if(isset($detail)): ?>
            <p class="detail"><?php echo e($detail); ?></p>
        <?php endif; ?>
    </main>
</body>
</html>
<?php /**PATH /var/www/gamemgr/resources/views/errors/layout.blade.php ENDPATH**/ ?>