document.addEventListener('DOMContentLoaded', () => {
  const googleLoginLink = document.querySelector('[data-google-login]');

  if (!(googleLoginLink instanceof HTMLAnchorElement)) {
    throw new Error('The Google sign-in link is missing.');
  }

  const loginLabel = googleLoginLink.querySelector('[data-login-label]');
  const googleMark = googleLoginLink.querySelector('[data-login-google-mark]');
  const loginSpinner = googleLoginLink.querySelector('[data-login-spinner]');

  if (!(loginLabel instanceof HTMLElement) || !(googleMark instanceof SVGElement) || !(loginSpinner instanceof HTMLElement)) {
    throw new Error('The sign-in link is missing its loading-state elements.');
  }

  googleLoginLink.addEventListener('click', () => {
    googleLoginLink.setAttribute('aria-busy', 'true');
    googleMark.hidden = true;
    loginLabel.textContent = 'Signing In...';
    loginSpinner.hidden = false;
  });

  document.querySelectorAll('[data-static-link]').forEach((link) => {
    link.addEventListener('click', (event) => event.preventDefault());
  });
});
