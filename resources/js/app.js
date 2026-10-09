const status = document.querySelector('#status');

if (status) {
    status.textContent = `Servidor local ativo em ${window.location.host}`;
}
