document.querySelectorAll('.readmission-form').forEach(form => {
    const basic = form.querySelector('[data-basic]');
    const details = form.querySelector('[data-details]');
    const identity = [...basic.querySelectorAll('input,select')];
    const fields = details.querySelectorAll('input,select,textarea');
    const submit = details.querySelector('[data-readmission-submit]');

    function showDetails(visible) {
        if (visible) {
            form.querySelector('[data-identity-summary]').textContent = identity.map(input => input.value).join(' · ');
        }
        basic.hidden = visible;
        details.hidden = !visible;
        fields.forEach(input => { input.disabled = !visible; });
        submit.disabled = !visible;
    }

    form.querySelector('[data-next]').addEventListener('click', () => {
        if (identity.every(input => input.reportValidity())) showDetails(true);
    });
    form.querySelector('[data-back]').addEventListener('click', () => showDetails(false));
    showDetails(details.hasAttribute('data-return-to-details'));
});
document.querySelectorAll('[data-join]').forEach(button=>button.addEventListener('click',()=>{const form=document.querySelector(`[data-seminar-form="${button.dataset.join}"]`);form.hidden=false;form.scrollIntoView({behavior:'smooth'});}));
document.querySelector('[data-slot-date]')?.addEventListener('change',event=>{const time=document.querySelector('[data-slot-time]');time.value='';[...time.options].forEach(option=>{if(option.value)option.hidden=option.dataset.date!==event.target.value;});});
document.querySelectorAll('[data-expand]').forEach(button=>button.addEventListener('click',()=>{const text=button.previousElementSibling;text.classList.toggle('expanded');button.textContent=text.classList.contains('expanded')?'Show less':'Show more';}));

document.querySelector('[data-link-result]') && setTimeout(()=>window.location.replace('/community-link'),2200);

document.querySelectorAll('[data-slot-date]').forEach(input=>input.dispatchEvent(new Event('change')));
document.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{form.querySelectorAll('[data-prevent-duplicate]').forEach(button=>{button.disabled=true;button.textContent='Submitting…';});}));
