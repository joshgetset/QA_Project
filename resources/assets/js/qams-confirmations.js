import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

const primaryColor = '#063b73';

const confirmationOptions = {
  icon: 'question',
  iconColor: primaryColor,
  showCancelButton: true,
  confirmButtonColor: primaryColor,
  cancelButtonColor: '#6c757d',
  reverseButtons: true,
  focusCancel: true
};

document.querySelectorAll('[data-confirm-pillar]').forEach(form => {
  const select = form.querySelector('[data-current-value]');

  if (!(select instanceof HTMLSelectElement)) {
    throw new Error('The pillar selector is missing its current value.');
  }

  let confirmationOpen = false;

  select.addEventListener('change', async () => {
    const previousValue = select.dataset.currentValue;
    const nextValue = select.value;

    if (confirmationOpen || !previousValue || nextValue === previousValue) return;

    confirmationOpen = true;
    select.blur();
    await new Promise(resolve => requestAnimationFrame(resolve));
    let result;

    try {
      result = await Swal.fire({
        ...confirmationOptions,
        title: 'Change pillar?',
        text: `Switch to the ${nextValue} pillar?`,
        confirmButtonText: 'Change pillar',
        cancelButtonText: 'Stay here'
      });
    } finally {
      confirmationOpen = false;
    }

    if (result.isConfirmed) {
      select.dataset.currentValue = nextValue;
      form.requestSubmit();
      return;
    }

    select.value = previousValue;
  });
});

document.querySelectorAll('[data-confirm-logout]').forEach(form => {
  let confirmationOpen = false;

  form.addEventListener('submit', async event => {
    if (confirmationOpen) {
      event.preventDefault();

      return;
    }

    event.preventDefault();
    confirmationOpen = true;
    let result;

    try {
      result = await Swal.fire({
        ...confirmationOptions,
        title: 'Log out?',
        text: 'You will need to sign in again to continue.',
        confirmButtonText: 'Log out',
        cancelButtonText: 'Cancel'
      });
    } finally {
      confirmationOpen = false;
    }

    if (result.isConfirmed) {
      HTMLFormElement.prototype.submit.call(form);
    }
  });
});
