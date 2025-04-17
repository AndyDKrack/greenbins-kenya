// popup.js
function downloadFile() {
  const link = document.createElement('a');
  link.href = 'assets/AgreementForm.pdf';
  link.download = 'AgreementForm.pdf';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  const popup = document.getElementById('popup');
  popup.classList.add('show');

  setTimeout(() => {
    popup.classList.add('hide');
    setTimeout(() => {
      popup.classList.remove('show', 'hide');
    }, 1000);
  }, 3000);
}
