import { ApexCharts } from '../vendor/libs/apex-charts/apexcharts.js';
import { animateNumber } from './system-number-animation.js';

const dashboard = document.querySelector('[data-qams-dashboard]');

if (dashboard) {
  const refreshButton = dashboard.querySelector('[data-refresh-systems]');
  const alert = dashboard.querySelector('[data-refresh-alert]');
  const checkedAt = dashboard.querySelector('[data-dashboard-checked-at]');
  const endpoint = dashboard.dataset.systemsUrl;
  const searchInput = document.querySelector('[data-qams-system-search]');
  const categoryButtons = [...dashboard.querySelectorAll('[data-category-filter]')];
  const sections = [...dashboard.querySelectorAll('[data-category-section]')];
  const sortControl = dashboard.querySelector('[data-system-sort]');
  const trendStorageKey = 'qams-system-trends-v1';
  const trendCharts = new Map();
  let canStoreTrendHistory = true;
  let isRefreshing = false;
  let activeCategory = 'all';

  const formattedTime = value => {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString();
  };

  const readTrendHistory = () => {
    try {
      const history = JSON.parse(localStorage.getItem(trendStorageKey) || '{}');

      return history && typeof history === 'object' && !Array.isArray(history) ? history : {};
    } catch (error) {
      canStoreTrendHistory = false;
      console.warn('Unable to read saved QAMS system trends.', error);
      return {};
    }
  };

  let trendHistory = readTrendHistory();

  const saveTrendHistory = () => {
    if (!canStoreTrendHistory) return;

    try {
      localStorage.setItem(trendStorageKey, JSON.stringify(trendHistory));
    } catch (error) {
      canStoreTrendHistory = false;
      console.warn('Unable to save QAMS system trends.', error);
    }
  };

  const numericMetricValue = value => {
    const normalized = String(value).replace(/,/g, '').replace(/%$/, '');

    if (!/^[+-]?\d+(\.\d+)?$/.test(normalized)) return null;

    const number = Number(normalized);

    return Number.isFinite(number) ? number : null;
  };

  const sampleIncreasingTrend = value => {
    const progression = [0.84, 0.88, 0.87, 0.93, 0.91, 0.96, 0.95, 0.99, 0.98, 1];

    return progression.map((step, index) => (index === progression.length - 1 ? value : value * step));
  };

  const categoryColors = {
    academic: '#20c997',
    people: '#9361ff',
    finance: '#f2a100',
    health: '#f45678',
    research: '#14b8cf'
  };

  const renderTrend = (card, metric, source = card.dataset.source) => {
    const trend = card.querySelector('[data-system-trend]');
    const chartElement = trend.querySelector('[data-trend-chart]');
    const changeElement = trend.querySelector('[data-trend-change]');
    const value = numericMetricValue(metric?.value);

    if (value === null) {
      trend.hidden = true;
      return;
    }

    trend.hidden = false;
    const systemKey = card.dataset.systemCard;
    let history = trendHistory[systemKey];

    if (
      !history ||
      history.label !== metric.label ||
      history.source !== source ||
      !Array.isArray(history.values) ||
      history.values.some(value => !Number.isFinite(value)) ||
      (source === 'demo' && history.sampled !== true)
    ) {
      history = {
        label: metric.label,
        source,
        values: source === 'demo' ? sampleIncreasingTrend(value) : [],
        sampled: source === 'demo'
      };
      trendHistory[systemKey] = history;
      saveTrendHistory();
    }

    if (source !== 'demo' && history.values[history.values.length - 1] !== value) {
      history.values.push(value);
      history.values = history.values.slice(-12);
      saveTrendHistory();
    }

    const values = history.values;
    const previous = values.length > 1 ? values[values.length - 2] : null;
    const current = values[values.length - 1];
    const direction = previous === null || current === previous ? 'neutral' : current > previous ? 'up' : 'down';

    changeElement.className = `qams-system-trend-change qams-trend-${direction}`;
    if (previous === null) {
      changeElement.textContent = 'Tracking';
      changeElement.setAttribute('aria-label', 'Trend tracking started; no previous value to compare');
    } else if (current === previous) {
      changeElement.textContent = 'No change';
      changeElement.setAttribute('aria-label', 'No change since the previous refresh');
    } else {
      const percentage = previous === 0 ? null : Math.abs(((current - previous) / previous) * 100);
      const formattedChange = percentage === null ? '' : ` ${percentage.toFixed(1)}%`;

      changeElement.textContent = `${direction === 'up' ? '↑' : '↓'}${formattedChange}`;
      changeElement.setAttribute(
        'aria-label',
        `${direction === 'up' ? 'Increased' : 'Decreased'}${percentage === null ? '' : ` by ${percentage.toFixed(1)} percent`} since the previous refresh`
      );
    }

    const color = categoryColors[card.dataset.category] || '#20c997';
    const chartData = values.length === 1 ? [value, value] : values;
    const chart = trendCharts.get(chartElement);

    if (chart) {
      chart.updateOptions({ colors: [color], fill: { colors: [color] } }, false, false);
      chart.updateSeries([{ data: chartData }], false);
      return;
    }

    const newChart = new ApexCharts(chartElement, {
      chart: {
        type: 'area',
        height: 38,
        width: '100%',
        sparkline: { enabled: true },
        animations: { enabled: false },
        toolbar: { show: false },
        zoom: { enabled: false }
      },
      series: [{ data: chartData }],
      colors: [color],
      stroke: { curve: 'smooth', width: 2 },
      fill: {
        type: 'gradient',
        colors: [color],
        gradient: {
          shadeIntensity: 0,
          opacityFrom: 0.28,
          opacityTo: 0.02,
          stops: [0, 100]
        }
      },
      tooltip: { enabled: false },
      dataLabels: { enabled: false },
      markers: { size: 0 },
      grid: { show: false },
      xaxis: { crosshairs: { show: false } }
    });

    trendCharts.set(chartElement, newChart);
    newChart.render();
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
  };

  const updateSystemStatus = (card, source, sourceLabel) => {
    const status = card.querySelector('.qams-system-status');
    const validSources = ['live', 'demo', 'unavailable'];
    const currentSource = validSources.includes(source) ? source : 'unavailable';

    card.dataset.source = currentSource;
    status.classList.remove('qams-status-live', 'qams-status-demo', 'qams-status-unavailable');
    status.classList.add(`qams-status-${currentSource}`);
    status.title = sourceLabel;
    status.setAttribute('aria-label', sourceLabel);
  };

  const updateSummary = systems => {
    const liveCount = systems.filter(system => system.source === 'live').length;
    const demoCount = systems.filter(system => system.source === 'demo').length;
    const unavailableCount = systems.filter(system => system.source === 'unavailable').length;
    const liveSummaryCount = dashboard.querySelector('[data-live-summary-count]');
    if (liveSummaryCount) animateNumber(liveSummaryCount, liveCount);
    const liveConnectionSummary = dashboard.querySelector('[data-live-connection-summary]');
    if (liveConnectionSummary) {
      animateNumber(liveConnectionSummary.querySelector('[data-live-connection-count]'), liveCount);
    }
    const demoSummary = dashboard.querySelector('[data-demo-summary]');
    const unavailableSummary = dashboard.querySelector('[data-unavailable-summary]');

    if (demoSummary) demoSummary.hidden = demoCount === 0;
    if (unavailableSummary) unavailableSummary.hidden = unavailableCount === 0;

    systems.forEach(system => {
      const summaryValue = dashboard.querySelector(`[data-summary-value-for="${CSS.escape(system.key)}"]`);

      if (summaryValue) animateNumber(summaryValue, system.metrics[0]?.value || '—');
    });
  };

  const applyFilters = () => {
    const query = searchInput?.value.trim().toLocaleLowerCase() || '';
    let visibleCount = 0;

    sections.forEach(section => {
      const categoryMatches = activeCategory === 'all' || section.dataset.categorySection === activeCategory;
      let sectionVisibleCount = 0;

      section.querySelectorAll('[data-system-column]').forEach(column => {
        const card = column.querySelector('[data-system-card]');
        const matchesSearch = card.dataset.systemName.toLocaleLowerCase().includes(query);

        column.hidden = !categoryMatches || !matchesSearch;
        if (!column.hidden) sectionVisibleCount += 1;
      });

      section.hidden = sectionVisibleCount === 0;
      visibleCount += sectionVisibleCount;
    });

    dashboard.querySelector('[data-no-results]').hidden = visibleCount > 0;
  };

  const sortSystems = () => {
    const sortByName = sortControl.value === 'name';

    sections.forEach(section => {
      const grid = section.querySelector('.qams-system-grid');
      const columns = [...grid.querySelectorAll('[data-system-column]')];

      columns.sort((first, second) => {
        if (sortByName) {
          return first
            .querySelector('[data-system-card]')
            .dataset.systemName.localeCompare(second.querySelector('[data-system-card]').dataset.systemName);
        }

        return Number(first.dataset.systemOrder) - Number(second.dataset.systemOrder);
      });

      columns.forEach(column => grid.append(column));
    });
  };

  const renderSystems = systems => {
    systems.forEach(system => {
      const card = dashboard.querySelector(`[data-system-card="${CSS.escape(system.key)}"]`);

      if (!card) return;

      const metrics = system.metrics.slice(0, 1);
      renderMetrics(card.querySelector('[data-system-metrics]'), metrics);
      updateSystemStatus(card, system.source, system.source_label);
      renderTrend(card, metrics[0], system.source);
    });

    updateSummary(systems);
  };

  dashboard.querySelectorAll('[data-system-card]').forEach(card => {
    const value = card.querySelector('.qams-metric-value')?.textContent;
    const label = card.querySelector('.qams-metric-label')?.textContent;

    if (value && label) {
      renderTrend(card, { value, label });
    } else {
      card.querySelector('[data-system-trend]').hidden = true;
    }
  });

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

      renderSystems(result.systems);
      checkedAt.textContent = `Last checked ${formattedTime(result.checked_at)}`;
    } catch (error) {
      alert.textContent = 'Unable to refresh system data. Your last displayed values are unchanged; try again shortly.';
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

      applyFilters();
    });
  });

  dashboard.querySelectorAll('[data-view-mode]').forEach(button => {
    button.addEventListener('click', () => {
      const isList = button.dataset.viewMode === 'list';

      dashboard.querySelectorAll('.qams-system-grid').forEach(grid => grid.classList.toggle('qams-list-view', isList));
      dashboard.querySelectorAll('[data-view-mode]').forEach(viewButton => {
        const selected = viewButton === button;

        viewButton.classList.toggle('btn-primary', selected);
        viewButton.classList.toggle('btn-outline-secondary', !selected);
        viewButton.setAttribute('aria-pressed', String(selected));
      });
    });
  });

  searchInput?.addEventListener('input', applyFilters);
  sortControl.addEventListener('change', sortSystems);
  refreshButton.addEventListener('click', refreshSystems);

  document.addEventListener('keydown', event => {
    if (event.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
      event.preventDefault();
      searchInput?.focus();
    }

    if (event.key === 'Escape' && document.activeElement === searchInput) {
      searchInput.value = '';
      applyFilters();
      searchInput.blur();
    }
  });
}
