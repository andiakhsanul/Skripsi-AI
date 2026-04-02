/**
 * auth/login.js
 * Handles all interactivity for the login page:
 *  - Role switcher (Mahasiswa / Administrator) with Tailwind classes
 *  - Password visibility toggle
 *  - Form submit loading state
 *  - Error alert dismiss
 */

document.addEventListener('DOMContentLoaded', () => {

    /* ─── Elements ─────────────────────────────────────────── */
    const btnMhs     = document.getElementById('role-mahasiswa');
    const btnAdm     = document.getElementById('role-admin');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const togglePwBtn  = document.getElementById('toggle-pw');
    const pwIcon       = document.getElementById('pw-icon');
    const loginForm    = document.getElementById('login-form');
    const submitBtn    = document.getElementById('submit-btn');
    const btnText      = document.getElementById('btn-text');
    const btnSpinner   = document.getElementById('btn-spinner');
    const errorAlert   = document.getElementById('error-alert');
    const errorClose   = document.getElementById('error-close');

    /* ─── Tailwind class sets for role switcher ─────────────── */
    const ACTIVE_CLASSES   = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold transition-all duration-300 bg-surface text-primary shadow-sm';
    const INACTIVE_CLASSES = 'flex-1 py-2.5 px-4 rounded-lg text-sm font-semibold text-on-surface-variant hover:text-on-surface transition-all duration-300';

    /* ─── Role Switcher ─────────────────────────────────────── */
    if (btnMhs && btnAdm && emailInput) {
        btnMhs.addEventListener('click', () => {
            btnMhs.className = ACTIVE_CLASSES;
            btnAdm.className = INACTIVE_CLASSES;
            btnMhs.setAttribute('aria-selected', 'true');
            btnAdm.setAttribute('aria-selected', 'false');
            emailInput.placeholder = btnMhs.dataset.placeholder || 'nama@student.unair.ac.id';
        });

        btnAdm.addEventListener('click', () => {
            btnAdm.className = ACTIVE_CLASSES;
            btnMhs.className = INACTIVE_CLASSES;
            btnAdm.setAttribute('aria-selected', 'true');
            btnMhs.setAttribute('aria-selected', 'false');
            emailInput.placeholder = btnAdm.dataset.placeholder || 'admin@unair.ac.id';
        });
    }

    /* ─── Password Toggle ───────────────────────────────────── */
    if (togglePwBtn && passwordInput && pwIcon) {
        togglePwBtn.addEventListener('click', () => {
            const isVisible = passwordInput.type === 'text';
            passwordInput.type = isVisible ? 'password' : 'text';
            pwIcon.textContent  = isVisible ? 'visibility' : 'visibility_off';
            togglePwBtn.setAttribute('aria-pressed', String(!isVisible));
        });
    }

    /* ─── Error Alert Dismiss ───────────────────────────────── */
    if (errorClose && errorAlert) {
        errorClose.addEventListener('click', () => {
            errorAlert.classList.add('hidden');
            errorAlert.classList.remove('flex');
        });
    }

    /* ─── Reset input error state on change ─────────────────── */
    [emailInput, passwordInput].forEach(input => {
        if (!input) return;
        input.addEventListener('input', () => {
            input.classList.remove('ring-error');
            const icon = input.parentElement?.querySelector('.material-symbols-outlined');
            if (icon) icon.classList.remove('text-error');
        });
    });

    /* ─── Form Submit – Loading State ───────────────────────── */
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            // Hide any previous error
            if (errorAlert) {
                errorAlert.classList.add('hidden');
                errorAlert.classList.remove('flex');
            }

            // Show spinner
            if (submitBtn)  submitBtn.disabled = true;
            if (btnText)    btnText.classList.add('hidden');
            if (btnSpinner) btnSpinner.classList.remove('hidden');

            // Note: for a real Laravel form the page will reload after submit,
            // so no need to manually reset. The spinner acts as UX feedback only.
        });
    }

});
