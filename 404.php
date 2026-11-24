<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">
    <title>Page Not Found - AvenPay</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            display: grid;
            place-items: center;
            overflow: hidden;
            color: #0f172a;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(11, 154, 116, 0.18), transparent 45%),
                radial-gradient(ellipse at 80% 80%, rgba(11, 154, 116, 0.12), transparent 40%),
                #ffffff;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .card {
            position: relative;
            width: min(100%, 520px);
            padding: 56px 40px;
            overflow: hidden;
            text-align: center;
            background: rgba(255, 255, 255, .92);
            border: 1px solid rgba(15, 23, 42, 0.08000000000000002);
            border-radius: 28px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(16px);
        }

        .card::before {
            position: absolute;
            top: 0;
            left: 15%;
            width: 70%;
            height: 1px;
            content: "";
            background: linear-gradient(90deg, transparent, #0b9a74, #0b9a74, transparent);
        }

        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 24px;
            display: grid;
            place-items: center;
            color: #0b7a5c;
            background: rgba(11, 154, 116, 0.12);
            border: 1px solid rgba(11, 154, 116, 0.25);
            border-radius: 20px;
            font-size: 28px;
        }

        h1 {
            margin: 0;
            color: #0b7a5c;
            font-size: clamp(76px, 18vw, 120px);
            font-weight: 800;
            letter-spacing: -0.09em;
            line-height: 1;
            text-shadow: 0 0 40px rgba(11, 154, 116, 0.25);
        }

        h2 {
            margin: 18px 0 10px;
            font-size: 24px;
            letter-spacing: -0.03em;
        }

        p {
            max-width: 360px;
            margin: 0 auto 30px;
            color: #64748b;
            font-size: 15px;
            line-height: 1.7;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 48px;
            padding: 0 22px;
            color: #ffffff;
            background: linear-gradient(135deg, #0b9a74, #0b9a74);
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            -webkit-tap-highlight-color: transparent;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(11, 154, 116, 0.2);
        }

        .button:focus:not(:focus-visible) {
            outline: none;
        }

        .button:focus-visible {
            outline: 3px solid white;
            outline-offset: 4px;
        }

        .hint {
            margin-top: 24px;
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .card {
                padding: 44px 24px;
                border-radius: 22px;
            }

            h2 {
                font-size: 21px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">⌕</div>
        <h1>404</h1>
        <h2>Looks like you’re lost</h2>
        <p>The page you’re looking for isn’t here. It may have moved, or the address might be incorrect.</p>

        <a class="button" href="/login">
            <span aria-hidden="true">←</span>
            Back to safety
        </a>

        <div class="hint">Check the URL or return to the login page.</div>
    </main>
</body>
</html>