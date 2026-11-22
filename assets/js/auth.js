/**
 * ============================================================
 *  Multimedia Class — Auth JS
 *  Interaksi untuk semua halaman auth.
 * ============================================================
 */
'use strict';

// ── Toggle Password Visibility ───────────────────────────────
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.closest('.input-icon-wrap').querySelector('input');
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.querySelector('i').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

// ── Password Strength Meter ──────────────────────────────────
const pwInput = document.getElementById('password');
const strengthFill = document.querySelector('.pw-strength-fill');
const strengthText = document.querySelector('.pw-strength-text');

if (pwInput && strengthFill) {
    pwInput.addEventListener('input', () => {
        const val = pwInput.value;
        let score = 0;
        if (val.length >= 6)                    score++;
        if (val.length >= 10)                   score++;
        if (/[A-Z]/.test(val))                  score++;
        if (/[0-9]/.test(val))                  score++;
        if (/[^A-Za-z0-9]/.test(val))           score++;

        const levels = [
            { pct: '0%',   color: 'transparent',       label: '' },
            { pct: '25%',  color: '#ef4444',            label: 'Lemah' },
            { pct: '50%',  color: '#f59e0b',            label: 'Sedang' },
            { pct: '75%',  color: '#6366f1',            label: 'Kuat' },
            { pct: '100%', color: '#22c55e',            label: 'Sangat Kuat' },
        ];

        const idx = Math.min(score, 4);
        strengthFill.style.width = levels[idx].pct;
        strengthFill.style.backgroundColor = levels[idx].color;
        if (strengthText) strengthText.textContent = levels[idx].label;
    });
}

// ── Login Tab Switcher ───────────────────────────────────────
const tabs = document.querySelectorAll('.auth-tab');
const tabPanels = document.querySelectorAll('.tab-panel');

tabs.forEach(tab => {
    tab.addEventListener('click', () => {
        const target = tab.dataset.tab;

        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        tabPanels.forEach(panel => {
            panel.hidden = panel.dataset.tabPanel !== target;
        });

        // Update hidden input role
        const roleInput = document.getElementById('role-input');
        if (roleInput) roleInput.value = target;
    });
});

// ── Form Submit Spinner ──────────────────────────────────────
document.querySelectorAll('form[data-loading]').forEach(form => {
    form.addEventListener('submit', function () {
        const btn = form.querySelector('button[type="submit"]');
        const textEl = btn?.querySelector('.btn-text');
        const spinEl = btn?.querySelector('.spinner');

        if (textEl) textEl.textContent = 'Memproses...';
        if (spinEl) spinEl.classList.remove('d-none');
        if (btn)    btn.disabled = true;
    });
});

// ── Auto-dismiss alert ───────────────────────────────────────
document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => {
    const delay = parseInt(el.dataset.autoDismiss) || 5000;
    setTimeout(() => {
        el.style.transition = 'opacity 400ms';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, delay);
});

// ── Konfirmasi password match ────────────────────────────────
const confirmPwInput = document.getElementById('password_confirm');
if (confirmPwInput && pwInput) {
    function checkMatch() {
        const match = pwInput.value === confirmPwInput.value;
        confirmPwInput.classList.toggle('is-invalid', confirmPwInput.value && !match);
        const fb = document.getElementById('pw-confirm-feedback');
        if (fb) fb.textContent = (confirmPwInput.value && !match) ? 'Password tidak cocok.' : '';
    }
    confirmPwInput.addEventListener('input', checkMatch);
    pwInput.addEventListener('input', checkMatch);
}
