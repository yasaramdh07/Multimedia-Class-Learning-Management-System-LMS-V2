/**
 * ============================================================
 *  Multimedia Class — Base JavaScript
 * ============================================================
 *  Utility functions & global initializations.
 *  Belum ada fitur spesifik — ini pondasi awal.
 * ============================================================
 */

'use strict';

// ── App Namespace ────────────────────────────────────────────
const MC = {
    /** Base URL yang diambil dari <meta name="base-url"> di HTML */
    baseUrl: document.querySelector('meta[name="base-url"]')?.content?.replace(/\/$/, '') ?? '',

    /**
     * Fetch wrapper dengan default JSON headers & CSRF support.
     * @param {string} url
     * @param {RequestInit} options
     * @returns {Promise<Response>}
     */
    async fetch(url, options = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
            },
        };
        return fetch(url, { ...defaults, ...options, headers: { ...defaults.headers, ...(options.headers ?? {}) } });
    },

    /**
     * POST form data sebagai application/x-www-form-urlencoded.
     */
    async postForm(url, formData) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
            },
            body: formData,
        });
    },
};


// ── Toast Notification ───────────────────────────────────────
const Toast = (() => {
    let container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    const icons = {
        success: 'bi-check-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        danger:  'bi-x-circle-fill',
        info:    'bi-info-circle-fill',
    };

    const colors = {
        success: '#22c55e',
        warning: '#f59e0b',
        danger:  '#ef4444',
        info:    '#6366f1',
    };

    /**
     * Tampilkan toast.
     * @param {string} message
     * @param {'success'|'warning'|'danger'|'info'} type
     * @param {number} duration  ms sebelum auto-dismiss (0 = manual)
     */
    function show(message, type = 'info', duration = 4000) {
        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <i class="bi ${icons[type] ?? icons.info}" style="color:${colors[type] ?? colors.info}; font-size:1.1rem; flex-shrink:0;"></i>
            <span style="flex:1;">${message}</span>
            <button class="btn btn-ghost btn-sm" style="padding:2px 6px; flex-shrink:0;" aria-label="Tutup">
                <i class="bi bi-x"></i>
            </button>
        `;

        el.querySelector('button').addEventListener('click', () => dismiss(el));
        getContainer().appendChild(el);

        if (duration > 0) {
            setTimeout(() => dismiss(el), duration);
        }

        return el;
    }

    function dismiss(el) {
        el.classList.add('hiding');
        el.addEventListener('animationend', () => el.remove(), { once: true });
    }

    return { show, dismiss };
})();


// ── Polling (Near Real-Time) ─────────────────────────────────
const Poller = (() => {
    let intervalId = null;
    const DEFAULT_INTERVAL = 15_000; // 15 detik

    async function poll() {
        try {
            const res = await fetch(
                `${MC.baseUrl}/includes/polling-handler.php?action=ping`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            if (!res.ok) return;
            const data = await res.json();
            // Dispatch custom event agar komponen lain bisa listen
            document.dispatchEvent(new CustomEvent('mc:poll', { detail: data }));
        } catch (_) {
            // Diam-diam abaikan error jaringan saat polling
        }
    }

    function start(interval = DEFAULT_INTERVAL) {
        if (intervalId) return;
        poll(); // langsung poll sekali
        intervalId = setInterval(poll, interval);
    }

    function stop() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    return { start, stop };
})();


// ── Sidebar Toggle ───────────────────────────────────────────
function initSidebarToggle() {
    const toggle  = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay?.classList.toggle('active');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });
}


// ── Active Nav Item ──────────────────────────────────────────
function initActiveNav() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-item[href]').forEach(link => {
        if (link.getAttribute('href') && currentPath.endsWith(link.getAttribute('href'))) {
            link.classList.add('active');
        }
    });
}


// ── Table Live Search ──────────────────────────────────────────
function initTableSearch() {
    document.querySelectorAll('.table-search').forEach(function(input) {

        // Cegah form submit jika input ada di dalam <form> (ketika user tekan Enter)
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') e.preventDefault();
        });

        input.addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase();

            // Cari table dengan cara walk-up DOM secara manual
            // agar tidak bergantung pada nama class wrapper
            var table = null;
            var node = this.parentElement;
            while (node && node !== document.body) {
                table = node.querySelector('table');
                if (table) break;
                node = node.parentElement;
            }

            if (!table) return;

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                // Lewati baris "tidak ada data" (colspan)
                if (row.querySelector('td[colspan]')) return;

                var match = row.textContent.toLowerCase().indexOf(searchTerm) !== -1;
                row.style.display = match ? '' : 'none';
            });
        });
    });
}


// ── DOMContentLoaded ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initActiveNav();
    initTableSearch();

    // Mulai polling hanya jika user sudah login (ada meta user-id)
    if (document.querySelector('meta[name="user-id"]')) {
        Poller.start();
    }

    // Auto-dismiss alert elements
    document.querySelectorAll('[data-auto-dismiss]').forEach(el => {
        const delay = parseInt(el.dataset.autoDismiss, 10) || 4000;
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, delay);
    });
});

// Stop polling saat tab tidak aktif, resume saat aktif kembali
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        Poller.stop();
    } else {
        if (document.querySelector('meta[name="user-id"]')) {
            Poller.start();
        }
    }
});

// ── Global Polling Listener ──────────────────────────────────
let currentTaId = null;

document.addEventListener('mc:poll', (e) => {
    const data = e.detail;
    
    // Deteksi perubahan Tahun Ajaran
    if (data.tahun_ajaran_id !== undefined) {
        if (currentTaId === null) {
            currentTaId = data.tahun_ajaran_id; // inisialisasi awal
        } else if (currentTaId !== data.tahun_ajaran_id) {
            // Tahun ajaran berubah!
            currentTaId = data.tahun_ajaran_id;
            Toast.show('Periode Tahun Ajaran Aktif telah berubah. Memuat ulang halaman...', 'warning', 3000);
            setTimeout(() => window.location.reload(), 3000);
        }
    }
});
