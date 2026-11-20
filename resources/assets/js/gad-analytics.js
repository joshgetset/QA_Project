import { SystemDashboardChart } from './system-dashboard-chart.js';

const dashboard = document.querySelector('[data-gad-analytics]');

if (dashboard) {
  const filters = dashboard.querySelector('[data-gad-filters]');
  const campus = filters.elements.namedItem('campus');
  const department = filters.elements.namedItem('department');
  filters.addEventListener('change', event => {
    if (!(event.target instanceof HTMLSelectElement)) return;
    if (event.target === campus) {
      department.value = '';
      department.disabled = true;
    }
    filters.requestSubmit();
  });

  const { months, monthCounts, campuses, campusCounts } = JSON.parse(dashboard.dataset.chartData);
  const styles = getComputedStyle(dashboard);
  const color = name => styles.getPropertyValue(name).trim();
  const colors = {
    submitted: color('--bs-primary'),
    approved: color('--bs-indigo'),
    ongoing: color('--qams-sky-blue'),
    overdue: color('--bs-danger'),
    completed: color('--qams-forest-green')
  };
  const number = new Intl.NumberFormat();
  const renderChart = (container, labels, buckets, statuses, horizontal = false) => {
    const maxCount = Math.max(0, ...buckets.flatMap(bucket => statuses.map(status => bucket[status])));
    const minimumBarValue = horizontal ? 0 : maxCount * 0.06;
    const actualCount = (value, { seriesIndex, dataPointIndex } = {}) =>
      buckets[dataPointIndex]?.[statuses[seriesIndex]] ?? value;

    const chart = new SystemDashboardChart(container, {
      chart: {
        type: 'bar',
        height: horizontal ? Math.max(340, labels.length * 104) : 360,
        fontFamily: styles.fontFamily,
        foreColor: color('--bs-body-color'),
        toolbar: { show: false },
        parentHeightOffset: 0
      },
      colors: statuses.map(status => colors[status]),
      series: statuses.map(status => ({
        name: status.charAt(0).toUpperCase() + status.slice(1),
        data: buckets.map(bucket => {
          const count = bucket[status];
          return count > 0 ? Math.max(count, minimumBarValue) : 0;
        })
      })),
      plotOptions: {
        bar: {
          horizontal,
          borderRadius: 2,
          ...(horizontal ? { barHeight: '94%' } : { columnWidth: `${statuses.length * 32}%` }),
          dataLabels: { position: 'top' }
        }
      },
      stroke: {
        show: !horizontal,
        width: 1,
        colors: [color('--bs-paper-bg')]
      },
      xaxis: {
        categories: labels,
        ...(!horizontal && { tickPlacement: 'between' })
      },
      dataLabels: {
        enabled: true,
        ...(horizontal ? { textAnchor: 'start', offsetX: 8 } : { offsetY: -18 }),
        formatter: (value, point) => {
          const count = actualCount(value, point);
          return count > 0 ? number.format(count) : '';
        },
        style: { colors: [color('--bs-heading-color')], fontSize: '11px', fontWeight: 700 },
        background: { enabled: false }
      },
      tooltip: { y: { formatter: (value, point) => number.format(actualCount(value, point)) } },
      legend: { position: 'bottom' },
      grid: {
        borderColor: color('--bs-border-color'),
        xaxis: { lines: { show: true } },
        padding: horizontal ? { right: 48 } : { top: 20 }
      }
    });
    chart.render();
  };

  dashboard.querySelectorAll('[data-gad-monthly-chart]').forEach(plot => {
    renderChart(plot, months, monthCounts, plot.dataset.gadMonthlyChart.split(' '));
  });
  renderChart(dashboard.querySelector('[data-gad-campus-chart]'), campuses, campusCounts, Object.keys(colors), true);
}
