{{-- resources/views/emails/standard.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        /* Keep all your existing styles */
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         'Helvetica Neue', Arial, sans-serif;
            color: #333;
        }

        .email-container {
            position: relative;
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .email-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb 0%, #059669 100%);
            z-index: 1;
        }

        .email-header {
            background: linear-gradient(90deg, #2563eb 0%, #059669 100%);
            color: #ffffff;
            padding: 32px 24px 24px;
            text-align: center;
        }

        .logo-rounded {
            border-radius: 50%;
            max-height: 64px;
            margin-bottom: 12px;
            background-color: white;
            padding: 4px;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .brand-section { margin-bottom: 16px; }
        .parent-brand { font-size: 13px; letter-spacing: 1px; text-transform: uppercase; opacity: .9; margin-bottom: 4px; }
        .brand-name { font-size: 24px; font-weight: 700; color: white; margin-bottom: 4px; line-height: 1.2; }
        .tagline { font-size: 14px; opacity: .9; font-weight: 400; margin-bottom: 20px; }

        .email-header h1 {
            margin: 20px 0 0;
            font-size: 20px;
            font-weight: 500;
            opacity: .95;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 20px;
        }

        .email-body {
            padding: 36px 28px;
            font-size: 16px;
            line-height: 1.75;
            color: #111827;
        }

        .email-body p { margin-bottom: 1.5em; }

        .cta-button {
            display: inline-block;
            margin: 24px 0;
            padding: 14px 32px;
            background: linear-gradient(90deg, #2563eb 0%, #059669 100%);
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            border-radius: 30px;
            box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2);
        }

        .email-tip {
            background: linear-gradient(90deg, #eff6ff 0%, #ecfdf5 100%);
            border-left: 4px solid #059669;
            padding: 20px;
            margin: 24px 0;
            border-radius: 8px;
            font-size: 15px;
            color: #065f46;
        }

        .email-tip strong { color: #2563eb; }

        .email-footer {
            background: linear-gradient(90deg, #2563eb 0%, #059669 100%);
            padding: 28px 24px;
            text-align: center;
            font-size: 14px;
            color: white;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-message { margin-bottom: 12px; opacity: .9; }
        .footer-message strong { color: white; font-weight: 700; }
        .copyright { font-size: 12px; opacity: .7; margin-top: 12px; }

        @media only screen and (max-width: 620px) {
            .email-container { margin: 20px 10px; }
            .email-body { padding: 24px 16px; }
            .email-header h1 { font-size: 18px; }
            .brand-name { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="email-container">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="email-header">
            {{-- FIX 1: Check if logoPath exists and file exists before embedding --}}
            @php
                $logoToUse = $logoPath ?? public_path('images/continuousLogoLight.png');
                $logoExists = file_exists($logoToUse);
            @endphp

            @if($logoExists)
                <img src="{{ $message->embed($logoToUse) }}"
                     alt="{{ config('app.name') }}"
                     class="logo-rounded">
            @else
                {{-- Fallback to text logo if image doesn't exist --}}
                <div style="font-size: 32px; font-weight: bold; margin-bottom: 12px;">📧</div>
            @endif

            <div class="brand-section">
                <div class="parent-brand">From the makers of</div>
                <div class="brand-name">Custospark</div>
                <div class="tagline">Innovation That Powers Excellence</div>
            </div>

            <h1>{{ $title }}</h1>
        </div>

        {{-- ── Body ───────────────────────────────────────────────────── --}}
        <div class="email-body">

            {{-- Main content: render HTML or escape plain text --}}
            @if($isHtml)
                {!! $mailBody !!}
            @else
                <p>{!! nl2br(e($mailBody)) !!}</p>
            @endif

            {{-- Optional pro-tip callout --}}
            @isset($tip)
                <div class="email-tip">
                    <strong>💡 Pro Tip:</strong> {{ $tip }}
                </div>
            @endisset

            {{-- Optional CTA button --}}
            @isset($ctaUrl)
                <div style="text-align: center;">
                    <a href="{{ $ctaUrl }}"
                       class="cta-button"
                       target="_blank"
                       rel="noopener noreferrer">
                        {{ $ctaLabel ?? 'Get Started' }}
                    </a>
                </div>
            @endisset

        </div>

        {{-- ── Footer ─────────────────────────────────────────────────── --}}
        <div class="email-footer">
            <div class="footer-message">
                You're receiving this because you use <strong>Custocare</strong>,<br>
                a product of <strong>Custospark</strong> — where innovation meets excellence.
            </div>
            <div class="copyright">
                &copy; {{ now()->year }} Custospark Company Ltd. All rights reserved.
            </div>
        </div>

    </div>
</body>
</html>