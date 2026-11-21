document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-leadership-form]');
    if (!form) return;
    const panels = [...form.querySelectorAll('[data-leadership-step]')];
    const title = document.querySelector('[data-leadership-title]');
    const uploads = [...panels[1].querySelectorAll('input[type="file"]')];
    const missingNotice = form.querySelector('[data-leadership-missing]');

    function updateMissingDocuments() {
        const missing = uploads.filter(input => input.validity.valueMissing)
            .map(input => input.labels[0].textContent.trim());
        const message = missing.length ? 'Missing required documents: ' + missing.join(', ') + '.' : '';
        missingNotice.hidden = !missing.length;
        if (missingNotice.textContent !== message) missingNotice.textContent = message;
    }

    uploads.forEach(input => {
        input.addEventListener('change', () => {
            const file = input.files[0];
            const filename = document.getElementById(input.getAttribute('aria-describedby'));
            if (filename) filename.textContent = file?.name || '';
            input.setCustomValidity(file && file.size > 5 * 1024 * 1024 ? 'Choose a file smaller than 5 MB.' : '');
            if (!input.checkValidity()) input.reportValidity();
            if (!missingNotice.hidden) updateMissingDocuments();
        });
    });
    const show = step => {
        panels.forEach((panel, index) => { panel.hidden = index !== step; });
        title.textContent = step ? 'Student Leader Validation' : 'Student Leadership Information';
    };
    form.querySelector('[data-leadership-next]').addEventListener('click', () => {
        const invalid = [...panels[0].querySelectorAll('input,select')].find(input => !input.checkValidity());
        if (invalid) return invalid.reportValidity();
        history.pushState({ leadershipStep: 1 }, '');
        show(1);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
    window.addEventListener('popstate', event => show(event.state?.leadershipStep === 1 ? 1 : 0));
    form.addEventListener('invalid', event => {
        const panel = event.target.closest('[data-leadership-step]');
        if (panel) show(Number(panel.dataset.leadershipStep));
        if (event.target.type === 'file' && event.target.validity.valueMissing) updateMissingDocuments();
    }, true);
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) event.preventDefault();
        else { submitting = true; form.setAttribute('aria-busy', 'true'); }
    });
    window.addEventListener('pageshow', () => { submitting = false; form.removeAttribute('aria-busy'); });
});
