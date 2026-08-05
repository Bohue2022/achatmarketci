<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: #f6f6f6; margin: 0; padding: 24px; }
        .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 28px; box-sizing: border-box; }
        h1 { font-size: 18px; color: #0f172a; margin-top: 0; }
        .code { font-size: 34px; font-weight: 700; letter-spacing: 8px; color: #f97316; background: #fff7ed; border-radius: 8px; padding: 16px; text-align: center; margin: 20px 0; }
        p { color: #334155; font-size: 14px; line-height: 1.6; }
        .muted { color: #94a3b8; font-size: 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Bienvenue sur {{ config('app.name') }}</h1>
        <p>Bonjour {{ $user->name }},</p>
        <p>Utilisez le code ci-dessous pour confirmer votre adresse e-mail. Il est valable {{ config('otp.ttl_minutes') }} minutes.</p>
        <div class="code">{{ $code }}</div>
        <p>Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail.</p>
        <p class="muted">L'équipe {{ config('app.name') }}</p>
    </div>
</body>
</html>