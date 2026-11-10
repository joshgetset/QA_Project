const dashboard = document.querySelector('[data-qams-dashboard]');

if (dashboard) {
  const refreshButton = dashboard.querySelector('[data-refresh-systems]');
  const alert = dashboard.querySelector('[data-refresh-alert]');
  const checkedAt = dashboard.querySelector('[data-dashboard-checked-at]');
  const endpoint = dashboard.dataset.systemsUrl;
  const refreshInterval = Number(dashboard.dataset.refreshInterval) || 30;
  let isRefreshing = false;

  const formattedTime = value => {
    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? '' : date.toLocaleTimeString();
  };

  const renderMetrics = (container, metrics) => {
    container.replaceChildren();

    if (!metrics.length) {
      const empty = document.createElement('p');
      empty.className = 'qams-empty-metrics mb-0';
      empty.textContent = 'No data is currently available.';
      container.append(empty);
      return;
    }

    metrics.forEach(metric => {
      const item = document.createElement('div');
      const label = document.createElement('span');
      const value = document.createElement('strong');

      item.className = 'qams-metric';
      label.className = 'qams-metric-label';
      value.className = 'qams-metric-value';
      value.textContent = metric.value;
      label.textContent = metric.label;
      item.append(value, label);
      container.append(item);
    });
  };

  const renderSystems = systems => {
    systems.forEach(system => {
      const card = dashboard.querySelector(`[data-system-card="${CSS.escape(system.key)}"]`);

      if (!card) return;

      renderMetrics(card.querySelector('[data-system-metrics]'), system.metrics.slice(0, 1));
    });
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

      renderSystems(result.systems);
      checkedAt.textContent = `Last checked ${formattedTime(result.checked_at)} · Refreshes every ${refreshInterval} seconds`;
    } catch (error) {
      alert.textContent = 'Unable to refresh system data. Your last displayed values are unchanged; try again shortly.';
      alert.hidden = false;
      console.error('Unable to refresh QAMS system data.', error);
    } finally {
      isRefreshing = false;
      refreshButton.disabled = false;
    }
  };

  refreshButton.addEventListener('click', refreshSystems);
  window.setInterval(refreshSystems, refreshInterval * 1000);
}
