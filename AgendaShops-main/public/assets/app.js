document.querySelectorAll('[data-back]').forEach(b=>b.addEventListener('click',()=>history.back()));
document.querySelectorAll('[data-confirm]').forEach(f=>f.addEventListener('submit',e=>{if(!confirm(f.dataset.confirm))e.preventDefault()}));
document.querySelectorAll('[data-copy]').forEach(b=>b.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(b.dataset.copy);b.textContent='Link copiado ✓'}catch{b.textContent='Selecione o link acima para copiar'}}));
const menu=document.querySelector('.mobile-menu');if(menu)menu.addEventListener('click',()=>{const open=document.querySelector('.sidebar').classList.toggle('open');menu.setAttribute('aria-expanded',String(open))});
const form=document.querySelector('#booking-form');
if(form){
 const service=form.querySelector('#service'),pro=form.querySelector('#professional'),date=form.querySelector('#booking-date'),slots=form.querySelector('#slots'),error=form.querySelector('#booking-error'),summary=form.querySelector('#booking-summary'),review=form.querySelector('#review-booking'),submit=form.querySelector('#confirm-booking');
 let controller=null,selection=null,reviewed=false;
 function resetReview(){reviewed=false;summary.hidden=true;submit.hidden=true;review.hidden=false;error.textContent=''}
 function selectedProName(){if(!selection)return '';return selection.professional}
 async function load(){
  resetReview();selection=null;
  if(controller)controller.abort();controller=new AbortController();
  slots.replaceChildren();
  if(!service.value||!date.value){slots.textContent='Selecione um serviço e uma data.';return}
  slots.textContent='Buscando horários disponíveis…';slots.setAttribute('aria-busy','true');
  try{
   const params=new URLSearchParams({service:service.value,professional:pro.value,date:date.value});
   const r=await fetch(form.dataset.base+'api/disponibilidade/'+form.dataset.slug+'?'+params,{signal:controller.signal});const data=await r.json();if(!r.ok)throw Error(data.error||'Não foi possível buscar os horários.');
   slots.replaceChildren();const seen=new Set();
   for(const slot of data){if(seen.has(slot.time))continue;seen.add(slot.time);const label=document.createElement('label');label.className='slot';const radio=document.createElement('input');radio.type='radio';radio.name='time';radio.value=slot.time;radio.required=true;const span=document.createElement('span');span.textContent=slot.time;radio.addEventListener('change',()=>{selection=slot;resetReview()});label.append(radio,span);slots.append(label)}
   if(!data.length)slots.textContent='Nenhum horário disponível nesta data. Tente outro dia ou profissional.';
  }catch(e){if(e.name!=='AbortError'){slots.textContent='';error.textContent=e.message}}finally{slots.removeAttribute('aria-busy')}
 }
 service.addEventListener('change',()=>{for(const o of pro.options){if(o.value==='0')continue;o.hidden=o.dataset.service!==service.value;o.disabled=o.hidden}pro.value='0';document.querySelectorAll('[data-service-detail]').forEach(el=>el.hidden=el.dataset.serviceDetail!==service.value);load()});pro.addEventListener('change',load);date.addEventListener('change',load);
 form.querySelectorAll('input:not([type=radio])').forEach(el=>el.addEventListener('input',resetReview));
 review.addEventListener('click',()=>{if(!form.reportValidity())return;if(!selection){error.textContent='Escolha um horário disponível.';return}const opt=service.selectedOptions[0];summary.textContent=opt.textContent+' · '+selectedProName()+' · '+date.value.split('-').reverse().join('/')+' às '+selection.time+'. Confira seus dados e confirme a reserva.';summary.hidden=false;review.hidden=true;submit.hidden=false;reviewed=true;submit.focus()});
 form.addEventListener('submit',async e=>{
  e.preventDefault();if(!reviewed){review.click();return}if(!selection)return;submit.disabled=true;submit.textContent='Confirmando…';error.textContent='';
  try{const data=new FormData(form);data.set('professional',String(selection.professional_id));const r=await fetch(form.dataset.base+'api/reservar/'+form.dataset.slug,{method:'POST',body:data});const result=await r.json();if(!r.ok)throw Error(result.error||'Não foi possível reservar.');location.href=result.redirect}catch(e){await load();error.textContent=e.message}finally{submit.disabled=false;submit.textContent='Confirmar agendamento ✓'}
 });
}
