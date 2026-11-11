const numberState = new WeakMap();
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

export const animateNumber = (element, value) => {
  const nextValue = String(value);
  const previousValue = numberState.get(element) ?? element.textContent.trim();

  if (nextValue === previousValue) return;

  numberState.set(element, nextValue);
  element.setAttribute('aria-label', nextValue);

  if (reducedMotion.matches || typeof element.animate !== 'function') {
    element.textContent = nextValue;
    return;
  }

  const scrollValue = document.createElement('span');
  scrollValue.className = 'qams-number-scroll';
  scrollValue.textContent = nextValue;
  element.replaceChildren(scrollValue);
  scrollValue.animate(
    [
      { opacity: 0.65, transform: 'translateY(-0.3em)' },
      { opacity: 1, transform: 'translateY(0)' }
    ],
    { duration: 220, easing: 'cubic-bezier(0.2, 0.7, 0.2, 1)' }
  );
};
