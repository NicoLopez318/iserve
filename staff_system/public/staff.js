document.addEventListener('click',event=>{const opener=event.target.closest('[data-dialog]');if(opener){const dialog=document.getElementById(opener.dataset.dialog);if(dialog){const form=dialog.querySelector('form');if(opener.dataset.draft&&form){const draft=JSON.parse(opener.dataset.draft);form.reset();for(const [name,value] of Object.entries({...draft.fields,draft_id:draft.id})){const field=form.elements.namedItem(name);if(field&&field.type!=='file')field.value=value??'';}}else if(form?.elements.namedItem('draft_id')){form.reset();form.elements.namedItem('draft_id').value='';}dialog.showModal();dialog.querySelector('input,textarea,select,button')?.focus();}}if(event.target.closest('[data-close]'))event.target.closest('dialog')?.close();if(event.target.closest('[data-print]'))window.print();});
document.querySelectorAll('dialog').forEach(dialog => {
    const closeLink = dialog.querySelector('a.close-dialog');
    function dismiss() {
        if (closeLink) window.location.assign(closeLink.href);
        else dialog.close();
    }
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right ||
            event.clientY < bounds.top || event.clientY > bounds.bottom) dismiss();
    });
    if (closeLink) dialog.addEventListener('cancel', event => {
        event.preventDefault();
        dismiss();
    });
});
document.querySelectorAll('[data-verify]').forEach(button=>button.addEventListener('click',()=>{const select=document.getElementById(button.dataset.verify);const output=document.getElementById(button.dataset.result);output.textContent=select?.selectedOptions[0]?.dataset.info||'Select a verified student record first.';}));
document.querySelectorAll('[data-submit-on-change]').forEach(control => {
    control.addEventListener('change', () => control.form.requestSubmit());
});

document.querySelectorAll('dialog[data-auto-open]').forEach(dialog => {
    if (typeof dialog.showModal !== 'function') return;
    dialog.removeAttribute('open');
    dialog.showModal();
});
document.querySelectorAll('dialog form').forEach(form=>{const field=document.createElement('input');field.type='hidden';field.name='_dialog';field.value=form.closest('dialog').id;form.append(field);});
const restore=document.getElementById('form-restore');if(restore){const state=JSON.parse(restore.textContent);const dialog=document.getElementById(state.dialog);if(dialog){for(const form of dialog.querySelectorAll('form'))for(const [name,value]of Object.entries(state.fields)){const field=form.elements.namedItem(name);if(field&&field.type!=='file'&&field.type!=='password'){if(field.type==='checkbox')field.checked=Boolean(value);else field.value=value??'';}}if(!dialog.open)dialog.showModal();}}
const communityServiceSeverity = document.querySelector('[data-community-hours]');
if (communityServiceSeverity) {
    const form = communityServiceSeverity.form;
    const hours = form.elements.namedItem('required_hours');
    const hint = document.getElementById('assignment-hour-range');
    const updateHourRange = () => {
        const { minHours, maxHours } = communityServiceSeverity.selectedOptions[0]?.dataset ?? {};
        hours.min = minHours ?? '1';
        hours.max = maxHours ?? '500';
        hint.textContent = `Enter ${hours.min} to ${hours.max} hours.`;
    };
    communityServiceSeverity.addEventListener('change', updateHourRange);
    form.closest('dialog').addEventListener('toggle', updateHourRange);
    updateHourRange();
}
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',event=>{if(form.dataset.submitting==='true'){event.preventDefault();return;}form.dataset.submitting='true';setTimeout(()=>{form.setAttribute('aria-busy','true');form.querySelectorAll('button[type="submit"],button:not([type])').forEach(button=>{button.disabled=true;});},0);}));
window.addEventListener('pageshow',()=>document.querySelectorAll('form[aria-busy]').forEach(form=>{delete form.dataset.submitting;form.removeAttribute('aria-busy');form.querySelectorAll('button').forEach(button=>button.disabled=false);}));

const venueRemovalDialog = document.getElementById('venue-removal');
venueRemovalDialog?.querySelector('[data-confirm-venue-removal]').addEventListener('click', () => venueRemovalDialog.close('delete'));

function confirmVenueRemoval(venue) {
    venueRemovalDialog.querySelector('[data-venue-to-remove]').textContent = venue;
    venueRemovalDialog.returnValue = '';
    venueRemovalDialog.showModal();
    return new Promise(resolve => {
        venueRemovalDialog.addEventListener('close', () => resolve(venueRemovalDialog.returnValue === 'delete'), { once: true });
    });
}

const venueFields = document.querySelectorAll('[data-venue-field]');
venueFields.forEach(field => {
    const choice = field.querySelector('[data-venue-choice]');
    const custom = field.querySelector('[data-venue-input]');
    const select = choice.querySelector('select');
    const input = custom.querySelector('input');
    const otherOption = select.querySelector('[data-other-venue]');
    const customFlag = field.querySelector('[name="custom_venue"]');
    const remove = field.querySelector('[data-remove-venue]');
    const feedback = field.querySelector('[data-venue-feedback]');
    const savedOption = [...select.options].find(option => option !== otherOption && option.value === input.value);
    let removing = false;

    (savedOption ?? otherOption).selected = true;
    if (savedOption) input.value = '';
    choice.hidden = false;

    function updateVenueField() {
        const isOther = select.selectedOptions[0] === otherOption;
        custom.hidden = !isOther;
        input.disabled = !isOther;
        customFlag.disabled = !isOther;
        select.required = !isOther;
        remove.disabled = removing || isOther || select.value === '';
        if (isOther) select.removeAttribute('name');
        else select.name = 'venue';
    }

    select.addEventListener('change', updateVenueField);
    field.addEventListener('venue-removed', ({ detail: venue }) => {
        const option = [...select.options].find(item => item !== otherOption && item.value === venue);
        if (!option) return;
        const wasSelected = option.selected;
        option.remove();
        if (wasSelected && input.defaultValue === venue) {
            otherOption.selected = true;
            input.value = venue;
        } else if (wasSelected) {
            select.selectedIndex = 0;
        }
        updateVenueField();
    });
    remove.addEventListener('click', async () => {
        const venue = select.value;
        if (remove.disabled || !await confirmVenueRemoval(venue)) return;
        removing = true;
        updateVenueField();
        try {
            const token = field.closest('form').elements.namedItem('_token').value;
            const response = await fetch(remove.dataset.removeVenue, {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: new URLSearchParams({ venue }),
            });
            if (!response.ok) throw new Error('Venue removal failed');
            venueFields.forEach(item => item.dispatchEvent(new CustomEvent('venue-removed', { detail: venue })));
            feedback.textContent = 'Venue removed from the list.';
        } catch {
            feedback.textContent = 'The venue could not be removed. Refresh the page and try again.';
        } finally {
            feedback.hidden = false;
            removing = false;
            updateVenueField();
        }
    });
    updateVenueField();
});
