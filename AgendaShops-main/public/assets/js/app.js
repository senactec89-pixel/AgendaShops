'use strict';
document.querySelectorAll('[data-back]').forEach(button => {
  button.addEventListener('click', () => { if (history.length > 1) history.back(); else location.assign('/'); });
});
document.querySelectorAll('[data-locate]').forEach(button => {
  button.addEventListener('click', () => {
    const form = button.closest('form');
    const status = form.querySelector('[data-location-status]');
    if (!navigator.geolocation) { status.textContent = 'Localização indisponível. Busque pelo endereço.'; return; }
    status.textContent = 'Consultando sua localização…';
    navigator.geolocation.getCurrentPosition(position => {
      form.elements.lat.value = position.coords.latitude;
      form.elements.lng.value = position.coords.longitude;
      form.requestSubmit();
    }, () => { status.textContent = 'Não foi possível obter sua localização. Use cidade, bairro ou endereço.'; }, {timeout: 10000, maximumAge: 300000});
  });
});
