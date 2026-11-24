const sfuFilters = document.querySelector('[data-sfu-filters]');
sfuFilters?.addEventListener('change', (event) => {
    if (event.target.matches('select')) sfuFilters.requestSubmit();
});

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
