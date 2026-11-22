document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('[data-password]').forEach(b=>b.addEventListener('click',()=>{const i=b.parentElement.querySelector('input');i.type=i.type==='password'?'text':'password';b.setAttribute('aria-label',i.type==='password'?'Show password':'Hide password');}));
 document.querySelectorAll('[data-menu]').forEach(b=>b.addEventListener('click',()=>{const el=document.getElementById(b.dataset.menu);if(el.id==='sidebar'){el.classList.toggle('mobile-open');b.setAttribute('aria-expanded',String(el.classList.contains('mobile-open')));}else{el.hidden=!el.hidden;b.setAttribute('aria-expanded',String(!el.hidden));}}));
 const select=document.getElementById('account-type');if(select){const update=()=>document.querySelectorAll('[data-account]').forEach(el=>{el.hidden=el.dataset.account!==select.value;el.querySelectorAll('input,select').forEach(i=>{i.disabled=el.hidden;});});select.addEventListener('change',update);update();}
 document.querySelectorAll('[data-tab]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-tab]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('[data-tab-panel]').forEach(x=>x.hidden=x.dataset.tabPanel!==b.dataset.tab);}));
 document.querySelectorAll('form[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault();}));
 document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
  const button = event.submitter;
  if (!button) return;
  setTimeout(() => {
   if (event.defaultPrevented || !form.checkValidity()) return;
   button.dataset.original = button.textContent;
   button.disabled = true;
   button.textContent = 'Please wait…';
  });
 }));
 document.querySelectorAll('input[type=file]').forEach(i=>i.addEventListener('change',()=>{i.setCustomValidity([...i.files].some(f=>f.size>Number(i.dataset.maxMb||5)*1024*1024)?`Each file must be under ${i.dataset.maxMb||5} MB.`:'');i.reportValidity();}));
});

window.addEventListener('pageshow', () => {
 document.querySelectorAll('button[data-original]').forEach(button => {
  button.textContent = button.dataset.original;
  delete button.dataset.original;
  button.disabled = false;
 });
});

document.addEventListener('DOMContentLoaded', () => {
 document.querySelectorAll('form[data-attendance-window]').forEach(form => {
  const button = form.querySelector('button[type="submit"]');
  const before = form.querySelector('[data-before-window]');
  const after = form.querySelector('[data-after-window]');
  const loadedAt = performance.now();
  const start = Number(form.dataset.start);
  const end = Number(form.dataset.end);

  function update() {
   const now = Number(form.dataset.serverTime) + (performance.now() - loadedAt) / 1000;
   before.hidden = now >= start;
   after.hidden = now <= end;
   button.disabled = !before.hidden || !after.hidden || 'original' in button.dataset;
   if (now <= end) setTimeout(update, 1000);
  }

  update();
 });
});

document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('[data-transaction-wizard]').forEach(form=>{
  let step=0; const groups=[...form.querySelectorAll('[data-form-step]')];
  const next=form.querySelector('[data-wizard-next]'),back=form.querySelector('[data-wizard-back]');
  const show=()=>{groups.forEach(el=>{el.hidden=Number(el.dataset.formStep)!==step;});next.hidden=step!==0;back.hidden=step!==1;form.closest('.blue-card').querySelectorAll('.steps span').forEach((el,i)=>el.classList.toggle('active',i===step));};
  next.addEventListener('click',()=>{const invalid=[...form.querySelectorAll('[data-form-step="0"] input,[data-form-step="0"] select')].find(el=>!el.checkValidity());if(invalid){invalid.reportValidity();return;}step=1;show();});
  back.addEventListener('click',()=>{step=0;show();});
  form.addEventListener('invalid',event=>{const group=event.target.closest('[data-form-step]');if(group){step=Number(group.dataset.formStep);show();}},true);
  show();
 });
});
