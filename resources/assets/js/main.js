/**
 * Main
 */

'use strict';

let menu, animate;
document.addEventListener('DOMContentLoaded', function () {
  // class for ios specific styles
  if (navigator.userAgent.match(/iPhone|iPad|iPod/i)) {
    document.body.classList.add('ios');
  }
});

(function () {
  // Button & Pagination Waves effect
  if (typeof Waves !== 'undefined') {
    Waves.init();
    Waves.attach(".btn[class*='btn-']:not(.position-relative):not([class*='btn-outline-'])", ['waves-light']);
    Waves.attach("[class*='btn-outline-']:not(.position-relative)");
    Waves.attach('.pagination .page-item .page-link');
    Waves.attach('.dropdown-menu .dropdown-item');
    Waves.attach('[data-bs-theme="light"] .list-group .list-group-item-action');
    Waves.attach('.nav-tabs:not(.nav-tabs-widget) .nav-item .nav-link');
    Waves.attach('.nav-pills .nav-item .nav-link', ['waves-light']);
  }

  // Initialize menu
  //-----------------

  let layoutMenuEl = document.querySelectorAll('#layout-menu');
  const sidebarPreference = 'qams-sidebar-collapsed';
  if (layoutMenuEl.length && sessionStorage.getItem(sidebarPreference) === 'true') {
    document.documentElement.classList.add('layout-menu-collapsed');
  }
  layoutMenuEl.forEach(function (element) {
    menu = new Menu(element, {
      orientation: 'vertical',
      closeChildren: false
    });
    // Change parameter to true if you want scroll animation
    window.Helpers.scrollToActive((animate = false));
    window.Helpers.mainMenu = menu;
  });

  // Initialize menu togglers and bind click on each
  let menuToggler = document.querySelectorAll('.layout-menu-toggle');
  menuToggler.forEach(item => {
    item.addEventListener('click', event => {
      event.preventDefault();
      const helpers = window.Helpers;
      const collapsed = !helpers.isCollapsed();
      helpers.setCollapsed(collapsed);
      if (!helpers.isSmallScreen()) sessionStorage.setItem(sidebarPreference, String(collapsed));
    });
  });

  const sidebar = document.getElementById('layout-menu');
  if (sidebar) {
    const helpers = window.Helpers;
    const railIsVisible = () => !helpers.isSmallScreen() && helpers.isCollapsed();

    const tooltips = [...sidebar.querySelectorAll('.menu-inner .menu-link')].map(link => {
      const title = link.querySelector('div').textContent.trim();
      const tooltip = new bootstrap.Tooltip(link, { title, placement: 'right', container: 'body' });
      link.addEventListener('show.bs.tooltip', event => {
        if (!railIsVisible()) event.preventDefault();
      });

      if (link.classList.contains('menu-toggle')) {
        link.addEventListener('click', event => {
          if (!railIsVisible()) return;
          event.preventDefault();
          event.stopPropagation();
          tooltip.hide();
          menu.toggle(link);
        });
      }
      return tooltip;
    });

    const updateSidebar = () => {
      const expanded = !helpers.isCollapsed();
      menuToggler.forEach(toggle => {
        if (toggle.tagName !== 'BUTTON') return;
        toggle.setAttribute('aria-expanded', String(expanded));
        toggle.setAttribute('aria-label', expanded ? 'Collapse sidebar' : 'Expand sidebar');
      });
      const icon = sidebar.querySelector('.layout-menu-toggle .icon-base');
      icon.classList.toggle('ri-menu-fold-line', expanded);
      icon.classList.toggle('ri-menu-unfold-line', !expanded);
      if (railIsVisible()) return;
      tooltips.forEach(tooltip => tooltip.hide());
    };
    helpers.on('toggle.qamsSidebar', updateSidebar);
    helpers.on('resize.qamsSidebar', updateSidebar);
    updateSidebar();
  }

  // Display in main menu when menu scrolls
  let menuInnerContainer = document.getElementsByClassName('menu-inner'),
    menuInnerShadow = document.getElementsByClassName('menu-inner-shadow')[0];
  if (menuInnerContainer.length > 0 && menuInnerShadow) {
    menuInnerContainer[0].addEventListener('ps-scroll-y', function () {
      if (this.querySelector('.ps__thumb-y').offsetTop) {
        menuInnerShadow.style.display = 'block';
      } else {
        menuInnerShadow.style.display = 'none';
      }
    });
  }

  // Init helpers & misc
  // --------------------

  // Init BS Tooltip
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  const fullscreenItem = document.querySelector('[data-navbar-fullscreen]');
  if (fullscreenItem && document.fullscreenEnabled) {
    const button = fullscreenItem.querySelector('button');
    const icon = button.querySelector('i');
    const updateFullscreenButton = () => {
      const isFullscreen = Boolean(document.fullscreenElement);
      const label = isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen';
      icon.classList.toggle('ri-fullscreen-line', !isFullscreen);
      icon.classList.toggle('ri-fullscreen-exit-line', isFullscreen);
      button.setAttribute('aria-label', label);
      button.title = label;
    };

    fullscreenItem.classList.remove('d-none');
    updateFullscreenButton();
    document.addEventListener('fullscreenchange', updateFullscreenButton);
    button.addEventListener('click', async () => {
      try {
        if (document.fullscreenElement) {
          await document.exitFullscreen();
        } else {
          await document.documentElement.requestFullscreen();
        }
      } catch (error) {
        console.warn('Unable to change fullscreen mode.', error);
      }
    });
  }

  // Accordion active class and previous-active class
  const accordionActiveFunction = function (e) {
    if (e.type == 'show.bs.collapse' || e.type == 'show.bs.collapse') {
      e.target.closest('.accordion-item').classList.add('active');
      e.target.closest('.accordion-item').previousElementSibling?.classList.add('previous-active');
    } else {
      e.target.closest('.accordion-item').classList.remove('active');
      e.target.closest('.accordion-item').previousElementSibling?.classList.remove('previous-active');
    }
  };

  const accordionTriggerList = [].slice.call(document.querySelectorAll('.accordion'));
  const accordionList = accordionTriggerList.map(function (accordionTriggerEl) {
    accordionTriggerEl.addEventListener('show.bs.collapse', accordionActiveFunction);
    accordionTriggerEl.addEventListener('hide.bs.collapse', accordionActiveFunction);
  });

  // Auto update layout based on screen size
  window.Helpers.setAutoUpdate(true);

  // Toggle Password Visibility
  window.Helpers.initPasswordToggle();

  // Speech To Text
  window.Helpers.initSpeechToText();

})();
