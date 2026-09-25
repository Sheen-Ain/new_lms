<?php

/**
 * auth/footer.php — Shared footer for all auth pages
 * Includes toast.js + app.js + lucide init + aurora canvas animation
 */
?>
<script src="<?= BASE_PATH ?>/assets/js/toast.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/app.js"></script>
<script>
    /* ── Init Lucide icons ─────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', () => {
        if (window.lucide) lucide.createIcons();
    });

    /* ── Aurora canvas animation (left panel) ──────────────── */
    function initAurora(canvasId) {
        const canvas = document.getElementById(canvasId || 'auth-aurora');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        function resize() {
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
        }
        resize();
        const ro = new ResizeObserver(resize);
        ro.observe(canvas.parentElement || document.body);

        const orbs = [{
                x: .2,
                y: .3,
                r: .4,
                dx: .0002,
                dy: .0003,
                hue: 240
            },
            {
                x: .8,
                y: .7,
                r: .35,
                dx: -.0003,
                dy: .0002,
                hue: 270
            },
            {
                x: .5,
                y: .1,
                r: .3,
                dx: .0003,
                dy: .0002,
                hue: 200
            },
            {
                x: .1,
                y: .8,
                r: .28,
                dx: .0002,
                dy: -.0003,
                hue: 280
            },
            {
                x: .9,
                y: .2,
                r: .32,
                dx: -.0002,
                dy: .0003,
                hue: 190
            },
        ];

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            orbs.forEach(o => {
                o.x += o.dx;
                o.y += o.dy;
                if (o.x < 0 || o.x > 1) o.dx *= -1;
                if (o.y < 0 || o.y > 1) o.dy *= -1;
                const cx = o.x * canvas.width,
                    cy = o.y * canvas.height;
                const r = o.r * Math.max(canvas.width, canvas.height);
                const g = ctx.createRadialGradient(cx, cy, 0, cx, cy, r);
                g.addColorStop(0, `hsla(${o.hue},80%,60%,.18)`);
                g.addColorStop(1, `hsla(${o.hue},80%,60%,0)`);
                ctx.fillStyle = g;
                ctx.beginPath();
                ctx.arc(cx, cy, r, 0, Math.PI * 2);
                ctx.fill();
            });
            requestAnimationFrame(draw);
        }
        draw();
    }

    /* ── Password strength meter ───────────────────────────── */
    function checkPwdStrength(val) {
        let score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        const colors = ['#ef4444', '#f97316', '#f59e0b', '#10b981', '#6366f1'];
        const labels = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'];
        const widths = ['20%', '40%', '60%', '80%', '100%'];
        return {
            score,
            color: colors[score - 1] || '#e2e8f0',
            label: labels[score - 1] || '',
            width: widths[score - 1] || '0%'
        };
    }

    /* ── Toggle password visibility ────────────────────────── */
    function toggleEye(inputId, btnId) {
        const inp = document.getElementById(inputId);
        const btn = document.getElementById(btnId);
        if (!inp || !btn) return;
        if (inp.type === 'password') {
            inp.type = 'text';
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
        } else {
            inp.type = 'password';
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        }
    }
</script>
</body>

</html>