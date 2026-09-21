// OMK School - Main JS

// Scroll to top button
window.addEventListener('scroll', () => {
    const btn = document.getElementById('scrollTop');
    if (btn) btn.classList.toggle('visible', window.scrollY > 300);
});
document.getElementById('scrollTop')?.addEventListener('click', () => window.scrollTo({top:0,behavior:'smooth'}));

// Auto-dismiss alerts
setTimeout(() => {
    document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
        new bootstrap.Alert(el).close();
    });
}, 4000);

// Auto-generate email/password for student form
function setupStudentAutoGen() {
    const fn = document.getElementById('first_name');
    const ln = document.getElementById('last_name');
    const uid = document.getElementById('user_id_number');
    const emailField = document.getElementById('email');
    const passField = document.getElementById('password');

    function generate() {
        const f = (fn?.value || '').trim().toLowerCase();
        const l = (ln?.value || '').trim().toLowerCase();
        const id = (uid?.value || '').trim();
        if (f && l && id && emailField && !emailField.dataset.manual) {
            emailField.value = f.charAt(0) + l.charAt(0) + id + '@omk.edu';
        }
        if (f && l && passField && !passField.dataset.manual) {
            passField.value = f + l + '123';
        }
    }
    [fn, ln, uid].forEach(el => el?.addEventListener('input', generate));
    emailField?.addEventListener('input', () => emailField.dataset.manual = emailField.value ? '1' : '');
    passField?.addEventListener('input', () => passField.dataset.manual = passField.value ? '1' : '');
}

// Auto-generate email/password for staff (teacher/coordinator)
function setupStaffAutoGen() {
    const fn = document.getElementById('first_name');
    const ln = document.getElementById('last_name');
    const uid = document.getElementById('user_id_number');
    const emailField = document.getElementById('email');
    const passField = document.getElementById('password');

    function generate() {
        const f = (fn?.value || '').trim().toLowerCase();
        const l = (ln?.value || '').trim().toLowerCase();
        const id = (uid?.value || '').trim();
        if (f && id && emailField && !emailField.dataset.manual) {
            emailField.value = f + id + '@omk.edu';
        }
        if (f && l && passField && !passField.dataset.manual) {
            passField.value = f + l + '333';
        }
    }
    [fn, ln, uid].forEach(el => el?.addEventListener('input', generate));
    emailField?.addEventListener('input', () => emailField.dataset.manual = emailField.value ? '1' : '');
    passField?.addEventListener('input', () => passField.dataset.manual = passField.value ? '1' : '');
}

// Role selector on login page
document.querySelectorAll('.role-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('role_input').value = btn.dataset.role;
    });
});

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
    document.getElementById('scrollTop');
    setupStudentAutoGen();
    setupStaffAutoGen();
});
