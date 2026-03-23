// pwa.js — TamizhMart PWA Installer
// Include this in every shop page (via shop_foot.php)

(function () {
    'use strict';

    // ── 1. Register Service Worker ───────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker
                .register('/tamizhmart/sw.js', { scope: '/tamizhmart/' })
                .then(reg => {
                    console.log('[PWA] Service Worker registered:', reg.scope);

                    // Check for updates
                    reg.addEventListener('updatefound', () => {
                        const newWorker = reg.installing;
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                showUpdateBanner();
                            }
                        });
                    });
                })
                .catch(err => console.warn('[PWA] SW registration failed:', err));
        });
    }

    // ── 2. Install Prompt (A2HS) ─────────────────────────────
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        deferredPrompt = e;

        // Only show if not already installed & not dismissed in last 7 days
        const lastDismissed = localStorage.getItem('pwa_dismissed');
        const sevenDays     = 7 * 24 * 60 * 60 * 1000;
        if (lastDismissed && (Date.now() - parseInt(lastDismissed)) < sevenDays) return;

        // Delay showing — don't interrupt immediately
        setTimeout(() => showInstallBanner(), 4000);
    });

    function showInstallBanner() {
        if (!deferredPrompt) return;
        if (document.getElementById('pwaInstallBanner')) return;

        const primary = getComputedStyle(document.documentElement)
            .getPropertyValue('--primary').trim() || '#c8a97e';

        const banner = document.createElement('div');
        banner.id = 'pwaInstallBanner';
        banner.style.cssText = `
            position: fixed;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 999;
            background: var(--text, #1a1208);
            color: var(--bg, #faf7f2);
            border-radius: 16px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
            max-width: 360px;
            width: calc(100vw - 32px);
            animation: slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);
            font-family: inherit;
        `;
        banner.innerHTML = `
            <style>
                @keyframes slideUp { from { opacity:0; transform: translateX(-50%) translateY(20px); } to { opacity:1; transform: translateX(-50%) translateY(0); } }
            </style>
            <div style="width:40px;height:40px;border-radius:10px;background:${primary};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;">🛍</div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;font-size:14px;margin-bottom:2px;">Install as App</div>
                <div style="font-size:12.5px;opacity:0.6;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">Shop faster — add to home screen</div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
                <button id="pwaInstallBtn" style="padding:8px 16px;border-radius:8px;background:${primary};border:none;color:#fff;font-weight:700;font-size:13px;cursor:pointer;">Install</button>
                <button id="pwaDismissBtn" style="padding:8px;border-radius:8px;background:rgba(255,255,255,0.1);border:none;color:inherit;font-size:14px;cursor:pointer;line-height:1;">✕</button>
            </div>
        `;
        document.body.appendChild(banner);

        document.getElementById('pwaInstallBtn').addEventListener('click', async () => {
            if (!deferredPrompt) return;
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('[PWA] User choice:', outcome);
            deferredPrompt = null;
            banner.remove();
        });

        document.getElementById('pwaDismissBtn').addEventListener('click', () => {
            localStorage.setItem('pwa_dismissed', Date.now().toString());
            banner.style.animation = 'none';
            banner.style.opacity   = '0';
            banner.style.transform = 'translateX(-50%) translateY(20px)';
            banner.style.transition = 'all 0.3s';
            setTimeout(() => banner.remove(), 300);
        });

        // Auto-dismiss after 8 seconds
        setTimeout(() => banner?.remove(), 8000);
    }

    // ── 3. Update Available Banner ───────────────────────────
    function showUpdateBanner() {
        if (document.getElementById('pwaUpdateBanner')) return;
        const banner = document.createElement('div');
        banner.id = 'pwaUpdateBanner';
        banner.style.cssText = `
            position:fixed; top:16px; right:16px; z-index:9999;
            background:#1a1208; color:#f0ece4;
            border-radius:12px; padding:14px 18px;
            display:flex; align-items:center; gap:12px;
            box-shadow:0 8px 32px rgba(0,0,0,0.3);
            font-family:inherit; font-size:13.5px;
            animation: fadeInRight 0.3s ease;
            max-width:300px;
        `;
        banner.innerHTML = `
            <style>@keyframes fadeInRight{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}</style>
            <span>🔄</span>
            <div style="flex:1;">
                <div style="font-weight:600;">Update available</div>
                <div style="opacity:0.6;font-size:12px;">Refresh for the latest version</div>
            </div>
            <button onclick="window.location.reload()" style="padding:6px 12px;border-radius:8px;background:#c8a97e;border:none;color:#fff;font-size:12px;font-weight:700;cursor:pointer;">Update</button>
            <button onclick="this.parentElement.remove()" style="background:none;border:none;color:rgba(240,236,228,0.5);cursor:pointer;font-size:16px;padding:2px;">✕</button>
        `;
        document.body.appendChild(banner);
    }

    // ── 4. Online/Offline indicator ──────────────────────────
    function updateOnlineStatus() {
        const indicator = document.getElementById('onlineIndicator');
        if (!indicator) return;
        if (navigator.onLine) {
            indicator.style.display = 'none';
        } else {
            indicator.style.display = 'flex';
        }
    }

    // Create offline indicator element
    const offlineBar = document.createElement('div');
    offlineBar.id = 'onlineIndicator';
    offlineBar.style.cssText = `
        display:none; position:fixed; top:0; left:0; right:0; z-index:9998;
        background:#ef4444; color:#fff; text-align:center;
        padding:8px; font-size:13px; font-weight:600;
        align-items:center; justify-content:center; gap:8px;
        font-family:inherit;
    `;
    offlineBar.innerHTML = '⚡ You\'re offline — some features may be unavailable';
    document.body.appendChild(offlineBar);

    window.addEventListener('online',  updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();

    // ── 5. App installed event ───────────────────────────────
    window.addEventListener('appinstalled', () => {
        console.log('[PWA] App installed!');
        deferredPrompt = null;
        // Show a thank-you toast if available
        if (typeof showToast === 'function') {
            showToast('App installed! Enjoy shopping offline 🎉');
        }
    });

})();