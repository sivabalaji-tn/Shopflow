<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're Offline &mdash; TamizhMart</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #c8a97e;
            --bg: #faf7f2;
            --text: #1a1208;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow: hidden;
        }

        /* Animated background circles */
        .bg-circles {
            position: fixed; inset: 0; z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .circle {
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
            background: var(--primary);
            animation: float 8s ease-in-out infinite;
        }
        .circle-1 { width: 400px; height: 400px; top: -100px; left: -100px; animation-delay: 0s; }
        .circle-2 { width: 300px; height: 300px; bottom: -80px; right: -80px; animation-delay: -3s; }
        .circle-3 { width: 200px; height: 200px; top: 50%; right: 15%; animation-delay: -5s; opacity: 0.05; }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.05); }
        }

        .wrap {
            position: relative; z-index: 1;
            text-align: center;
            max-width: 460px;
        }

        /* Animated signal icon */
        .icon-wrap {
            position: relative;
            width: 100px; height: 100px;
            margin: 0 auto 32px;
        }
        .icon-main {
            width: 100px; height: 100px;
            border-radius: 28px;
            background: linear-gradient(135deg,
                rgba(200,169,126,0.15),
                rgba(200,169,126,0.05)
            );
            border: 1.5px solid rgba(200,169,126,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 44px;
            animation: pulse-icon 3s ease-in-out infinite;
        }
        @keyframes pulse-icon {
            0%, 100% { box-shadow: 0 0 0 0 rgba(200,169,126,0.2); }
            50% { box-shadow: 0 0 0 16px rgba(200,169,126,0); }
        }

        /* Signal bars animation */
        .signal-bars {
            position: absolute;
            bottom: -2px; right: -8px;
            display: flex; gap: 3px; align-items: flex-end;
        }
        .bar {
            width: 6px; background: var(--primary);
            border-radius: 2px 2px 0 0;
            opacity: 0.3;
        }
        .bar-1 { height: 8px;  animation: bar-fade 2s ease-in-out infinite 0.1s; }
        .bar-2 { height: 14px; animation: bar-fade 2s ease-in-out infinite 0.3s; opacity: 0; }
        .bar-3 { height: 20px; animation: bar-fade 2s ease-in-out infinite 0.5s; opacity: 0; }
        @keyframes bar-fade {
            0%, 40%, 100% { opacity: 0.2; }
            20% { opacity: 1; }
        }

        h1 {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 36px;
            letter-spacing: -1px;
            margin-bottom: 12px;
            color: var(--text);
        }
        .subtitle {
            font-size: 16px;
            color: rgba(26,18,8,0.55);
            line-height: 1.65;
            margin-bottom: 36px;
        }

        .actions {
            display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;
        }
        .btn-retry {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 26px;
            background: var(--primary);
            border: none; border-radius: 12px;
            color: #fff;
            font-family: 'Syne', sans-serif; font-weight: 700; font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 20px rgba(200,169,126,0.3);
        }
        .btn-retry:hover { filter: brightness(1.1); transform: translateY(-2px); }
        .btn-retry:active { transform: translateY(0); }
        .btn-retry.loading { opacity: 0.7; pointer-events: none; }

        .tip {
            margin-top: 32px;
            padding: 16px 20px;
            background: rgba(200,169,126,0.07);
            border: 1px solid rgba(200,169,126,0.18);
            border-radius: 12px;
            font-size: 13.5px;
            color: rgba(26,18,8,0.55);
            line-height: 1.6;
        }
        .tip strong { color: var(--text); }

        /* Status dot */
        .status-row {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-size: 13px; color: rgba(26,18,8,0.45);
            margin-bottom: 28px;
        }
        .status-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #ef4444;
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }
    </style>
</head>
<body>
    <div class="bg-circles">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
        <div class="circle circle-3"></div>
    </div>

    <div class="wrap">
        <div class="icon-wrap">
            <div class="icon-main">📡</div>
            <div class="signal-bars">
                <div class="bar bar-1"></div>
                <div class="bar bar-2"></div>
                <div class="bar bar-3"></div>
            </div>
        </div>

        <div class="status-row">
            <div class="status-dot"></div>
            No internet connection
        </div>

        <h1>You're Offline</h1>
        <p class="subtitle">
            It looks like you've lost your internet connection. 
            Don't worry — check your connection and try again.
        </p>

        <div class="actions">
            <button class="btn-retry" onclick="retryConnection(this)">
                <span id="retryIcon">🔄</span>
                <span id="retryText">Try Again</span>
            </button>
        </div>

        <div class="tip">
            <strong>Tip:</strong> Some pages may still be available from your cache.
            Try navigating back to previously visited pages — they might load offline.
        </div>
    </div>

    <script>
    function retryConnection(btn) {
        btn.classList.add('loading');
        document.getElementById('retryIcon').textContent = '⏳';
        document.getElementById('retryText').textContent = 'Checking...';

        setTimeout(() => {
            if (navigator.onLine) {
                window.location.reload();
            } else {
                btn.classList.remove('loading');
                document.getElementById('retryIcon').textContent = '🔄';
                document.getElementById('retryText').textContent = 'Try Again';
                // Shake animation
                btn.style.animation = 'shake 0.4s ease';
                setTimeout(() => btn.style.animation = '', 400);
            }
        }, 1500);
    }

    // Auto-retry when back online
    window.addEventListener('online', () => {
        document.querySelector('.status-dot').style.background = '#22c55e';
        document.querySelector('.status-row').lastChild.textContent = ' Back online! Reloading...';
        setTimeout(() => window.location.reload(), 800);
    });

    // Add shake keyframe
    const style = document.createElement('style');
    style.textContent = '@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }';
    document.head.appendChild(style);
    </script>
</body>
</html>