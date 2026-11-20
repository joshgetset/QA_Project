import { animateNumber } from './system-number-animation.js';

const dashboard = document.querySelector('[data-qams-dashboard]');

if (dashboard) {
  const refreshButton = dashboard.querySelector('[data-refresh-systems]');
  const alert = dashboard.querySelector('[data-refresh-alert]');
  const checkedAt = dashboard.querySelector('[data-dashboard-checked-at]');
  const endpoint = dashboard.dataset.systemsUrl;
  const categoryButtons = [...dashboard.querySelectorAll('[data-category-filter]')];
  const systemSections = [...dashboard.querySelectorAll('[data-category-section]')].map(section => {
    const grid = section.querySelector('[data-system-grid]');
    return { section, grid, columns: [...grid.querySelectorAll('[data-system-column]')] };
  });
  const sortControl = dashboard.querySelector('[data-system-sort]');
  let isRefreshing = false;
  let activeCategory = 'all';

  const formattedTime = value => {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString();
  };

  const renderMetrics = (container, metrics) => {
    if (!metrics.length) {
      container.replaceChildren();
      const empty = document.createElement('p');
      empty.className = 'qams-empty-metrics mb-0';
      empty.textContent = 'No data is currently available.';
      container.append(empty);
      return;
    }

    container.querySelector('.qams-empty-metrics')?.remove();

    metrics.forEach((metric, index) => {
      const existing = container.querySelectorAll('.qams-metric')[index];

      if (existing) {
        animateNumber(existing.querySelector('.qams-metric-value'), metric.value);
        existing.querySelector('.qams-metric-label').textContent = metric.label;
        return;
      }

      const item = document.createElement('div');
      const label = document.createElement('span');
      const value = document.createElement('strong');

      item.className = 'qams-metric';
      label.className = 'qams-metric-label';
      value.className = 'qams-metric-value';
      animateNumber(value, metric.value);
      label.textContent = metric.label;
      item.append(value, label);
      container.append(item);
    });

    [...container.querySelectorAll('.qams-metric')].slice(metrics.length).forEach(item => item.remove());
  };

  const updateSystemStatus = (card, source, sourceLabel) => {
    const status = card.querySelector('.qams-system-status');
    const validSources = ['live', 'cached', 'demo', 'unavailable'];
    const currentSource = validSources.includes(source) ? source : 'unavailable';

    card.dataset.source = currentSource;
    status.classList.remove('qams-status-live', 'qams-status-cached', 'qams-status-demo', 'qams-status-unavailable');
    status.classList.add(`qams-status-${currentSource}`);
    status.title = sourceLabel;
    status.setAttribute('aria-label', sourceLabel);
  };

  const updateSummary = systems => {
    const demoCount = systems.filter(system => system.source === 'demo').length;
    const unavailableCount = systems.filter(system => system.source === 'unavailable').length;
    const demoSummary = dashboard.querySelector('[data-demo-summary]');
    const unavailableSummary = dashboard.querySelector('[data-unavailable-summary]');

    if (demoSummary) demoSummary.hidden = demoCount === 0;
    if (unavailableSummary) unavailableSummary.hidden = unavailableCount === 0;

    systems.forEach(system => {
      const summaryValue = dashboard.querySelector(`[data-summary-value-for="${CSS.escape(system.key)}"]`);
      if (!summaryValue) return;

      const metrics = Array.isArray(system.metrics) ? system.metrics : [];
      const expectedLabel = summaryValue.dataset.summaryMetricLabel;
      let metric = metrics[0];
      if (expectedLabel) {
        metric = metrics.find(({ label }) => label.trim().toLowerCase() === expectedLabel.toLowerCase());
      } else if (system.key === 'system-17') {
        metric = metrics.find(({ label }) => /^total enrolled students \(ay \d{4}-\d{4}\)$/i.test(label.trim()));
      }

      animateNumber(summaryValue, metric?.value ?? '—');
      const link = summaryValue.closest('a');
      if (link) link.title = system.source_label;
    });
  };

  const filterByCategory = () => {
    systemSections.forEach(({ section, columns }) => {
      const matchesCategory = activeCategory === 'all' || section.dataset.categorySection === activeCategory;
      section.hidden = !matchesCategory || columns.length === 0;
    });
  };

  const sortSystems = () => {
    systemSections.forEach(({ grid, columns }) => {
      const orderedColumns = [...columns];
      if (sortControl.value === 'name') {
        orderedColumns.sort((first, second) => first.querySelector('[data-system-card]').dataset.systemName
          .localeCompare(second.querySelector('[data-system-card]').dataset.systemName));
      }

      grid.append(...orderedColumns);
    });
  };

  const renderSystems = systems => {
    systems.forEach(system => {
      const card = dashboard.querySelector(`[data-system-card="${CSS.escape(system.key)}"]`);

      if (!card) return;

      const metrics = Array.isArray(system.metrics) ? system.metrics.slice(0, 1) : [];
      renderMetrics(card.querySelector('[data-system-metrics]'), metrics);
      updateSystemStatus(card, system.source, system.source_label);
    });

    updateSummary(systems);
  };

  const refreshSystems = async () => {
    if (isRefreshing) return;

    isRefreshing = true;
    refreshButton.disabled = true;
    alert.hidden = true;

    try {
      const response = await fetch(endpoint, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin'
      });

      if (!response.ok) throw new Error(`Dashboard refresh failed with status ${response.status}.`);

      const result = await response.json();

      if (!Array.isArray(result.systems)) throw new Error('The systems API returned an invalid response.');

      const universityPersonnel = Number.isInteger(result.university_personnel)
        ? new Intl.NumberFormat().format(result.university_personnel)
        : null;
      renderSystems(result.systems);
      const personnel = dashboard.querySelector('[data-university-personnel]');
      if (personnel) {
        animateNumber(personnel, universityPersonnel ?? '—');
        const link = personnel.closest('a');
        if (link) link.title = universityPersonnel === null ? 'Unavailable' : 'Warehouse snapshot';
      }
      checkedAt.textContent = `Last checked ${formattedTime(result.checked_at)}`;
    } catch (error) {
      const reason = error instanceof Error ? ` ${error.message}` : '';
      alert.textContent = `Unable to refresh system data.${reason} Your last displayed values are unchanged; try again shortly.`;
      alert.hidden = false;
      console.error('Unable to refresh QAMS system data.', error);
    } finally {
      isRefreshing = false;
      refreshButton.disabled = false;
    }
  };

  categoryButtons.forEach(button => {
    button.addEventListener('click', () => {
      activeCategory = button.dataset.categoryFilter;

      categoryButtons.forEach(filter => {
        const selected = filter === button;
        filter.classList.toggle('active', selected);
        filter.setAttribute('aria-pressed', String(selected));
      });

      filterByCategory();
    });
  });

  dashboard.querySelectorAll('[data-view-mode]').forEach(button => {
    button.addEventListener('click', () => {
      const isList = button.dataset.viewMode === 'list';

      systemSections.forEach(({ grid }) => grid.classList.toggle('qams-list-view', isList));
      dashboard.querySelectorAll('[data-view-mode]').forEach(viewButton => {
        const selected = viewButton === button;

        viewButton.classList.toggle('btn-primary', selected);
        viewButton.classList.toggle('btn-outline-secondary', !selected);
        viewButton.setAttribute('aria-pressed', String(selected));
      });
    });
  });

  sortControl.addEventListener('change', sortSystems);
  refreshButton.addEventListener('click', refreshSystems);

  const navigationType = performance.getEntriesByType?.('navigation')?.[0]?.type;
  if (navigationType === 'reload') refreshSystems();
}
