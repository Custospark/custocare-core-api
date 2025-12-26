<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Custocare Core API</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top, #eef2ff, #f8fafc);
            color: #1f2937;
        }

        .container {
            background: #ffffff;
            padding: 2rem 2.5rem;
            border-radius: 12px;
            box-shadow: 
                0 10px 30px rgba(0, 0, 0, 0.08),
                0 2px 8px rgba(0, 0, 0, 0.04);
            text-align: center;
            max-width: 360px;
            width: 100%;
        }

        .title {
            font-size: 1.35rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .divider {
            width: 48px;
            height: 3px;
            background: linear-gradient(90deg, #4f46e5, #6366f1);
            border-radius: 999px;
            margin: 0.75rem auto 1.25rem;
        }

        .version {
            font-size: 0.95rem;
            color: #4b5563;
            background: #f1f5f9;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="title">Custocare Core API</div>
        <div class="divider"></div>
        <p class="version">
            App Version {{ config('app.version') }}
        </p>
    </div>

</body>
</html>
