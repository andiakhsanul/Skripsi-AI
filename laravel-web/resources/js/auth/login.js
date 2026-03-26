/**
 * auth/login.js
 * Handles all interactivity for the login page:
 *  - Role switcher (Mahasiswa / Administrator)
 *  - Password visibility toggle
 *  - Form submit loading state
 *  - Error alert dismiss
 */

document.addEventListener('DOMContentLoaded', () => {

    /* ─── Elements ─────────────────────────────────────────── */
    const roleBtns   = document.querySelectorAll('.role-btn');
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

    /* ─── Role Switcher ─────────────────────────────────────── */
    roleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Update active state
            roleBtns.forEach(b => {
                b.classList.remove('role-btn--active');
                b.setAttribute('aria-selected', 'false');
            });
            btn.classList.add('role-btn--active');
            btn.setAttribute('aria-selected', 'true');

            // Update placeholder
            const placeholder = btn.dataset.placeholder;
            if (placeholder && emailInput) {
                emailInput.placeholder = placeholder;
            }
        });
    });

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
        });
    }

    /* ─── Reset input error state on change ─────────────────── */
    [emailInput, passwordInput].forEach(input => {
        if (!input) return;
        input.addEventListener('input', () => {
            input.classList.remove('is-error');
            const wrap = input.closest('.field-input-wrap');
            if (wrap) wrap.classList.remove('is-error');
        });
    });

    /* ─── Form Submit – Loading State ───────────────────────── */
    if (loginForm) {
        loginForm.addEventListener('submit', () => {
            // Hide any previous error
            if (errorAlert) errorAlert.classList.add('hidden');

            // Show spinner
            submitBtn.disabled = true;
            if (btnText)    btnText.classList.add('hidden');
            if (btnSpinner) btnSpinner.classList.remove('hidden');

            // Note: for a real Laravel form the page will reload after submit,
            // so no need to manually reset. The spinner acts as UX feedback only.
        });
    }

});
