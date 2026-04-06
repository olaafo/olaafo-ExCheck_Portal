/* global JS for EntEx Portal */
(function () {
    'use strict';

    /* ---- Mobile sidebar toggle ---- */
    const menuBtn = document.querySelector('.topbar-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    /* ---- Login page: toggle student / admin view ---- */
    const adminToggleBtn = document.getElementById('adminToggleBtn');
    const studentForm    = document.getElementById('studentLoginForm');
    const adminForm      = document.getElementById('adminLoginForm');
    const modeLabel      = document.getElementById('loginModeLabel');
    const modeBadge      = document.getElementById('loginModeBadge');
    const cardHeaderSub  = document.getElementById('cardHeaderSub');

    if (adminToggleBtn) {
        let isAdmin = (new URLSearchParams(window.location.search).get('mode') === 'admin');

        function applyMode() {
            if (isAdmin) {
                studentForm.style.display = 'none';
                adminForm.style.display   = 'block';
                adminToggleBtn.textContent = 'Student Login';
                if (modeLabel)   modeLabel.textContent  = '🔐 Admin Login';
                if (modeBadge)   modeBadge.textContent  = 'Admin';
                if (cardHeaderSub) cardHeaderSub.textContent = 'Admin Portal Access';
            } else {
                studentForm.style.display = 'block';
                adminForm.style.display   = 'none';
                adminToggleBtn.textContent = '🔑 Admin';
                if (modeLabel)   modeLabel.textContent  = '🎓 Student Login';
                if (modeBadge)   modeBadge.textContent  = 'Student';
                if (cardHeaderSub) cardHeaderSub.textContent = 'Prospective Student Portal';
            }
        }

        applyMode();

        adminToggleBtn.addEventListener('click', () => {
            isAdmin = !isAdmin;
            applyMode();
            const url = new URL(window.location);
            isAdmin ? url.searchParams.set('mode', 'admin') : url.searchParams.delete('mode');
            window.history.replaceState({}, '', url);
        });
    }

    /* ---- Tabs ---- */
    document.querySelectorAll('.tabs').forEach(tabGroup => {
        const btns  = tabGroup.querySelectorAll('.tab-btn');
        const panes = document.querySelectorAll('.tab-pane');
        btns.forEach(btn => {
            btn.addEventListener('click', () => {
                btns.forEach(b => b.classList.remove('active'));
                panes.forEach(p => p.classList.remove('active'));
                btn.classList.add('active');
                const target = document.getElementById(btn.dataset.target);
                if (target) target.classList.add('active');
            });
        });
    });

    /* ---- Radio option visual ---- */
    document.querySelectorAll('.radio-option').forEach(opt => {
        const inp = opt.querySelector('input[type="radio"]');
        if (!inp) return;
        if (inp.checked) opt.classList.add('selected');
        inp.addEventListener('change', () => {
            document.querySelectorAll('.radio-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
        });
    });

    /* ---- Show/hide dependent fields ---- */
    function toggleDependent(trigger, targetId, showWhen) {
        const el = document.getElementById(targetId);
        if (!el) return;
        function upd() { el.style.display = (trigger.value === showWhen) ? 'block' : 'none'; }
        trigger.addEventListener('change', upd);
        upd();
    }

    /* Schedule mode toggle */
    const scheduleMode = document.getElementById('scheduleMode');
    if (scheduleMode) {
        toggleDependent(scheduleMode, 'batchScheduleArea', 'batch');
        toggleDependent(scheduleMode, 'oneoffScheduleArea', 'oneoff');
    }

    /* Result batch or oneoff */
    const resultMode = document.getElementById('resultMode');
    if (resultMode) {
        toggleDependent(resultMode, 'resultBatchArea', 'batch');
        toggleDependent(resultMode, 'resultOneoffArea', 'oneoff');
    }

    /* Registration type toggle */
    const regType = document.getElementById('registrationType');
    if (regType) {
        toggleDependent(regType, 'batchSelectArea', 'batch');
        toggleDependent(regType, 'oneoffDateArea', 'oneoff');
    }

    /* Resit date show/hide */
    document.querySelectorAll('[name="resit"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const resitDateDiv = document.getElementById('resitDateArea');
            if (resitDateDiv) {
                resitDateDiv.style.display = (radio.value === '1' && radio.checked) ? 'block' : 'none';
            }
        });
    });

    /* ---- Confirm dangerous actions ---- */
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', function (e) {
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });

    /* ---- Auto-dismiss alerts ---- */
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity .5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    /* ---- Modal open/close ---- */
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.modal);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay').classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    /* ---- Select all checkboxes ---- */
    const selectAll = document.getElementById('selectAllStudents');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.student-check').forEach(cb => cb.checked = this.checked);
        });
    }

    /* ---- CSV upload preview ---- */
    const csvInput = document.getElementById('csvUpload');
    if (csvInput) {
        csvInput.addEventListener('change', function () {
            const label = document.getElementById('csvLabel');
            if (label && this.files[0]) label.textContent = this.files[0].name;
        });
    }

    /* ---- Photo preview ---- */
    const photoInput = document.getElementById('studentPhoto');
    if (photoInput) {
        photoInput.addEventListener('change', function () {
            const preview = document.getElementById('photoPreview');
            if (preview && this.files[0]) {
                preview.src = URL.createObjectURL(this.files[0]);
                preview.style.display = 'block';
            }
        });
    }

})();
