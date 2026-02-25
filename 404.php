<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #0d0b08;
            color: #f0ece4;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
        }
        .bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 60% 50% at 30% 30%, rgba(200,169,126,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 50% at 80% 80%, rgba(100,60,20,0.1) 0%, transparent 60%),
                #0d0b08;
        }
        .wrap {
            position: relative; z-index: 1;
            text-align: center;
            max-width: 520px;
        }
        .four-oh-four {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: clamp(80px, 18vw, 140px);
            letter-spacing: -6px;
            line-height: 1;
            background: linear-gradient(135deg, #c8a97e, rgba(200,169,126,0.3));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            animation: shimmer 4s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { filter: brightness(1); }
            50% { filter: brightness(1.3); }
        }
        h1 {
            font-family: 'Syne', sans-serif;
            font-weight: 700; font-size: 24px;
            margin-bottom: 12px;
            color: #f0ece4;
        }
        p {
            font-size: 15px;
            color: rgba(240,236,228,0.5);
            line-height: 1.65;
            margin-bottom: 36px;
        }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn-home {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #c8a97e, #8b6428);
            border: none; border-radius: 12px;
            color: #fff;
            font-family: 'Syne', sans-serif; font-weight: 700; font-size: 14px;
            cursor: pointer; text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(200,169,126,0.25);
        }
        .btn-home:hover { filter: brightness(1.1); transform: translateY(-2px); color: #fff; }
        .btn-back {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 22px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 12px;
            color: rgba(240,236,228,0.6);
            font-size: 14px;
            cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .btn-back:hover { background: rgba(255,255,255,0.06); color: #f0ece4; }
    </style>
</head>
<body>
<div class="bg"></div>
<div class="wrap">
    <div class="four-oh-four">404</div>
    <h1>Page Not Found</h1>
    <p>The page you're looking for doesn't exist, may have been moved, or the link is incorrect.</p>
    <div class="actions">
        <a href="index.php" class="btn-home">🏠 Go Home</a>
        <a href="javascript:history.back()" class="btn-back">← Go Back</a>
    </div>
</div>
</body>
</html>
