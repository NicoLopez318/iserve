const sfuFilters = document.querySelector('[data-sfu-filters]');
sfuFilters?.addEventListener('change', (event) => {
    if (event.target.matches('select')) sfuFilters.requestSubmit();
});

const contentCarousel = document.querySelector('[data-content-carousel]');
if (contentCarousel) {
    const slides = [...contentCarousel.querySelectorAll('[data-content-slide]')];
    const dots = [...contentCarousel.querySelectorAll('[data-content-dot]')];
    const status = contentCarousel.querySelector('[data-content-slide-status]');
    let active = 0;

    function showContentSlide(index) {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => { slide.hidden = position !== active; });
        dots.forEach((dot, position) => {
            if (position === active) dot.setAttribute('aria-current', 'true');
            else dot.removeAttribute('aria-current');
        });
        status.textContent = `Preview ${active + 1} of ${slides.length}`;
    }

    contentCarousel.querySelector('[data-content-prev]')?.addEventListener('click', () => showContentSlide(active - 1));
    contentCarousel.querySelector('[data-content-next]')?.addEventListener('click', () => showContentSlide(active + 1));
    dots.forEach((dot, index) => dot.addEventListener('click', () => showContentSlide(index)));
    contentCarousel.addEventListener('keydown', (event) => {
        if (slides.length < 2 || event.target.closest('[data-content-slide]') || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        showContentSlide(active + (event.key === 'ArrowLeft' ? -1 : 1));
    });
}

document.querySelector('.slu-report-review-form')?.addEventListener('invalid', (event) => {
    const details = event.target.closest('details');
    if (details) details.open = true;
}, true);

const achievementReview = document.querySelector('[data-achievement-review]');
if (achievementReview) {
    const points = achievementReview.elements.awarded_points;
    const impact = achievementReview.querySelector('[data-achievement-impact]');
    const endorse = achievementReview.querySelector('[data-achievement-endorse]');
    const format = new Intl.NumberFormat('en-PH');

    function updateAchievementImpact() {
        const amount = points.value === '' ? Number(achievementReview.dataset.defaultPoints) : points.valueAsNumber;
        const valid = points.validity.valid && Number.isInteger(amount);
        impact.textContent = valid
            ? `${amount > 0 ? '+' : ''}${format.format(amount)} points after final approval`
            : 'Enter a valid point amount';
        if (endorse) endorse.textContent = valid ? `Endorse for ${format.format(amount)} Points` : 'Endorse to Assistant';
    }
    points.addEventListener('input', updateAchievementImpact);
    updateAchievementImpact();
}

if (document.querySelector('[data-slu-event-calendar]')) {
    function openLinkedEvent() {
        const match = window.location.hash.match(/^#(event-\d+)-(attendance|entry)$/);
        if (!match) return;
        const [, event, target] = match;
        if (target === 'entry' && document.getElementById(`${event}-entry`)) return;
        const dialog = document.getElementById(target === 'attendance' ? `${event}-attendance` : event);
        if (dialog && !dialog.open) dialog.showModal();
    }
    window.addEventListener('hashchange', openLinkedEvent);
    openLinkedEvent();
}

const organizationForm = document.querySelector('[data-slu-org-create]');
if (organizationForm) {
    const studentInput = organizationForm.elements.president_ref;
    const studentId = organizationForm.elements.president_id;
    const verify = organizationForm.querySelector('[data-verify-organization-leader]');
    const status = organizationForm.querySelector('[data-org-leader-message]');
    const records = Array.from(studentInput.list.options);
    const normalize = (value) => value.trim().toLowerCase();

    function showStudent(record, message) {
        studentId.value = record?.dataset.userId ?? '';
        organizationForm.querySelector('[data-org-leader-name]').textContent = record?.dataset.studentName ?? '—';
        organizationForm.querySelector('[data-org-leader-program]').textContent = record?.dataset.program || '—';
        organizationForm.querySelector('[data-org-leader-enrollment]').textContent = record ? 'Not recorded' : '—';
        verify.disabled = !studentInput.value.trim();
        studentInput.setCustomValidity(!record && studentInput.value.trim() ? 'Verify the student before creating the organization.' : '');
        status.textContent = message ?? (record ? 'Student account verified in iServe.' : 'Verified student records from iServe');
    }

    function restoreStudent() {
        const record = records.find((option) => option.dataset.userId === studentId.value);
        if (record && (!studentInput.value || normalize(studentInput.value) === normalize(record.value))) {
            studentInput.value = record.value;
            showStudent(record);
        } else {
            showStudent(null);
        }
    }

    studentInput.addEventListener('input', () => showStudent(null));
    verify.addEventListener('click', () => {
        const record = records.find((option) => normalize(option.value) === normalize(studentInput.value));
        if (record) studentInput.value = record.value;
        showStudent(record, record ? undefined : 'No eligible verified student matched that ID.');
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-dialog="create-organization"]')) restoreStudent();
    });
    restoreStudent();
}

const sfuValidationButton = document.querySelector('[data-dialog="sfu-validate"]');
sfuValidationButton?.addEventListener('click', () => {
    document.querySelector('#sfu-validate textarea').value = document.querySelector('#sfu-remarks').value;
});

const sfuSettings = document.querySelector('[data-sfu-settings]');
if (sfuSettings) {
    const status = sfuSettings.querySelector('[data-sfu-settings-status]');
    const text = status.querySelector('span');
    const initialText = text.textContent;
    const initialValues = new URLSearchParams(new FormData(sfuSettings)).toString();
    sfuSettings.addEventListener('input', () => {
        const changed = new URLSearchParams(new FormData(sfuSettings)).toString() !== initialValues;
        status.toggleAttribute('data-unsaved', changed);
        text.textContent = changed ? 'Unsaved changes' : initialText;
    });
}

const scholarForm = document.querySelector('#add-scholar form');
if (scholarForm) {
    const fileInputs = [...scholarForm.querySelectorAll('input[type="file"]')];
    let savedFiles = {};

    function showFile(input) {
        document.getElementById(input.getAttribute('aria-describedby')).textContent =
            input.files[0]?.name ?? savedFiles[input.name] ?? '';
    }

    function showDraftFiles(draft) {
        savedFiles = draft?.files ?? {};
        fileInputs.forEach(showFile);
    }

    fileInputs.forEach(input => input.addEventListener('change', () => showFile(input)));
    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-dialog="add-scholar"]');
        if (opener) showDraftFiles(opener.dataset.draft ? JSON.parse(opener.dataset.draft) : null);
    });

    const draftId = scholarForm.elements.draft_id.value;
    if (draftId) {
        const drafts = [...document.querySelectorAll('[data-dialog="add-scholar"][data-draft]')];
        showDraftFiles(drafts.map(button => JSON.parse(button.dataset.draft)).find(draft => draft.id === draftId));
    }

    const studentId = scholarForm.elements.student_id;
    studentId.addEventListener('change', () => {
        const record = [...studentId.list.options].find(option => option.value === studentId.value.trim());
        if (!record) return;
        for (const [input, key] of Object.entries({full_name: 'name', course: 'course', year_level: 'year'})) {
            if (record.dataset[key]) scholarForm.elements[input].value = record.dataset[key];
        }
    });
}
