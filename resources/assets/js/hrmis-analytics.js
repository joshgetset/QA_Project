import { SystemDashboardChart } from './system-dashboard-chart.js';

const analytics = document.querySelector('[data-hrmis-analytics]');

if (analytics) {
  const filters = analytics.querySelector('[data-hrmis-filters]');
  filters.addEventListener('change', () => filters.requestSubmit());

  const number = new Intl.NumberFormat();
  const percentage = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });
  const theme = getComputedStyle(analytics);
  const color = name => theme.getPropertyValue(`--bs-${name}`).trim();
  const genderSeries = [
    { key: 'male', name: 'Male', color: '#696cff' },
    { key: 'female', name: 'Female', color: '#ff6f91' },
    { key: 'unknown_gender', name: 'Unknown Gender', color: color('secondary') }
  ];
  const readData = element => JSON.parse(element.querySelector('script[type="application/json"]').textContent);
  const chartOptions = { fontFamily: window.config.fontFamily, toolbar: { show: false }, parentHeightOffset: 0 };

  analytics.querySelectorAll('[data-hrmis-chart]').forEach(container => {
    const rows = readData(container);
    const plot = container.querySelector('[data-hrmis-plot]');
    const isGender = container.dataset.hrmisChart === 'gender';
    const vertical = container.dataset.hrmisChartOrientation === 'vertical';
    const series = isGender
      ? genderSeries.filter(({ key }) => key !== 'unknown_gender' || rows.some(row => row.unknown_gender > 0))
      : [{ key: 'total', name: 'Employees', color: color('primary') }];
    const describeRows = rows.map(row => `${row.label}: ${number.format(row.total)}`).join('; ');
    plot.setAttribute('role', 'img');
    plot.setAttribute('aria-label', describeRows);
    const numericAxis = {
      min: 0,
      max: rows.some(row => row.total > 0) ? undefined : 4,
      tickAmount: 4,
      labels: { formatter: value => number.format(value) }
    };

    new SystemDashboardChart(plot, {
      chart: { ...chartOptions, type: 'bar', stacked: isGender, height: vertical ? 340 : Math.max(260, rows.length * 34 + 75) },
      series: series.map(({ key, name }) => ({ name, data: rows.map(row => row[key]) })),
      colors: series.map(entry => entry.color),
      plotOptions: {
        bar: {
          horizontal: !vertical,
          barHeight: '54%',
          columnWidth: '52%',
          borderRadius: 3,
          borderRadiusApplication: 'end',
          dataLabels: {
            position: isGender ? undefined : 'top',
            total: {
              enabled: isGender,
              offsetX: vertical ? 0 : 8,
              offsetY: vertical ? -6 : 0,
              formatter: value => number.format(value),
              style: { color: color('heading-color'), fontSize: '13px', fontWeight: 700 }
            }
          }
        }
      },
      dataLabels: isGender ? { enabled: false } : {
        enabled: true,
        offsetY: -18,
        formatter: value => number.format(value),
        style: { colors: [color('heading-color')], fontSize: '13px', fontWeight: 700 }
      },
      legend: { show: isGender, position: 'bottom', onItemClick: { toggleDataSeries: false } },
      tooltip: { y: { formatter: value => `${number.format(value)} employees` } },
      grid: {
        borderColor: color('border-color'),
        strokeDashArray: 4,
        xaxis: { lines: { show: !vertical } },
        padding: vertical ? { top: 16, right: 12 } : { left: 6, right: 48 }
      },
      xaxis: {
        categories: rows.map(row => {
          if (!vertical) return row.label;
          return (isGender ? row.label.replace(/-/g, '- ') : row.label).split(' ');
        }),
        ...(vertical ? { labels: { rotate: 0, hideOverlappingLabels: false } } : numericAxis),
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: vertical ? numericAxis : { labels: { maxWidth: 175 } },
      responsive: vertical ? [{
        breakpoint: isGender ? 991 : 575,
        options: {
          chart: { height: 400 },
          xaxis: {
            categories: rows.map(row => row.label),
            labels: {
              rotate: -65,
              rotateAlways: true,
              hideOverlappingLabels: false,
              maxHeight: 145,
              style: { fontSize: '10px' }
            }
          }
        }
      }] : [{ breakpoint: 575, options: { yaxis: { labels: { maxWidth: 110 } } } }]
    }).render();
  });

  const renderDonut = container => {
    const metric = readData(container);
    const series = genderSeries.filter(({ key }) => metric[key] > 0);

    new SystemDashboardChart(container.querySelector('[data-hrmis-plot]'), {
      chart: { ...chartOptions, type: 'donut', width: 216, height: 216, sparkline: { enabled: true } },
      series: series.map(({ key }) => metric[key]),
      labels: series.map(({ name }) => name),
      colors: series.map(entry => entry.color),
      stroke: { width: 0 },
      plotOptions: { pie: { customScale: 0.88, donut: { size: '50%' } } },
      dataLabels: {
        enabled: true,
        formatter: value => `${percentage.format(value)}%`,
        style: { colors: ['#fff'], fontWeight: 700 },
        dropShadow: { enabled: false }
      },
      legend: { show: false },
      tooltip: { y: { formatter: value => `${number.format(value)} employees` } }
    }).render();
  };

  analytics.querySelectorAll('[data-hrmis-donut]').forEach(renderDonut);

  const ratioPanel = analytics.querySelector('[data-hrmis-ratio]');
  if (ratioPanel) {
    const scope = ratioPanel.querySelector('[data-hrmis-ratio-scope]');
    const plot = ratioPanel.querySelector('[data-hrmis-ratio-chart]');
    const tables = ratioPanel.querySelectorAll('[data-hrmis-ratio-table]');
    const scopes = readData(ratioPanel);
    const formatRatio = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const ratioLabel = value => value === null ? 'Unavailable' : `1:${formatRatio.format(value)}`;
    let chart;

    const showScope = () => {
      const rows = scopes[scope.value];
      tables.forEach(table => { table.hidden = table.dataset.hrmisRatioTable !== scope.value; });
      plot.setAttribute('role', 'img');
      plot.setAttribute('aria-label', rows.map(row => `${row.label}: ${ratioLabel(row.ratio)}`).join('; '));

      if (chart) chart.destroy();
      chart = new SystemDashboardChart(plot, {
        chart: { ...chartOptions, type: 'bar', height: Math.max(170, rows.length * 38 + 80) },
        series: [{ name: 'Faculty to Student Ratio', data: rows.map(row => row.ratio) }],
        colors: [color('danger')],
        plotOptions: {
          bar: {
            horizontal: true,
            barHeight: '55%',
            borderRadius: 3,
            borderRadiusApplication: 'end',
            dataLabels: { position: 'top' }
          }
        },
        dataLabels: {
          enabled: true,
          textAnchor: 'start',
          offsetX: 8,
          formatter: value => value === null ? '' : ratioLabel(value),
          style: { colors: [color('heading-color')], fontWeight: 700 }
        },
        tooltip: { y: { formatter: ratioLabel } },
        grid: {
          borderColor: color('border-color'),
          strokeDashArray: 4,
          xaxis: { lines: { show: true } },
          padding: { left: 6, right: 54 }
        },
        xaxis: {
          categories: rows.map(row => row.label),
          min: 0,
          max: rows.some(row => row.ratio > 0) ? undefined : 4,
          tickAmount: 4,
          axisBorder: { show: false },
          axisTicks: { show: false }
        },
        yaxis: { labels: { maxWidth: 175 } }
      });
      chart.render();
    };

    scope.addEventListener('change', showScope);
    showScope();
  }
}
