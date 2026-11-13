import { ApexCharts } from '../vendor/libs/apex-charts/apexcharts.js';
import { animateNumber } from './system-number-animation.js';

const analyticsView = document.querySelector('[data-ces-analytics]');

if (analyticsView) {
  const analytics = JSON.parse(analyticsView.querySelector('[data-ces-analytics-data]').textContent);
  const statusElement = analyticsView.querySelector('[data-ces-analytics-status]');
  const numberFormat = new Intl.NumberFormat();
  const cardsView = analyticsView.querySelector('[data-ces-demographic-cards]');
  const overviewView = analyticsView.querySelector('[data-ces-demographic-overview]');
  const overviewTitle = analyticsView.querySelector('[data-ces-overview-title]');
  const financialChartElement = analyticsView.querySelector('[data-ces-financial-chart]');
  const financialLegend = analyticsView.querySelector('[data-ces-financial-legend]');
  const financialNote = analyticsView.querySelector('[data-ces-financial-note]');
  const selectors = [...analyticsView.querySelectorAll('[data-ces-filter]')];
  const allOptions = {
    campus: 'All Campuses',
    college: 'All Faculties',
    program: 'All Programs',
    year: 'Latest Academic Year',
    semester: 'Latest Semester'
  };
  let overviewChart;
  let financialChart;
  let financialMetrics = [];
  let requestController;
  let enrolledStudents = 0;
  const demographics = new Map(
    analytics.demographics
      .filter(metric => metric.display === 'gender')
      .map(metric => {
        const element = analyticsView.querySelector(`[data-ces-demographic-chart="${metric.key}"]`);

        element.dataset.chartLabel = element.getAttribute('aria-label');

        return [
          metric.key,
          {
            metric,
            element,
            maleSegment: element.querySelector('[data-ces-male-segment]'),
            femaleSegment: element.querySelector('[data-ces-female-segment]'),
            emptyMessage: analyticsView.querySelector(`[data-ces-demographic-empty="${metric.key}"]`)
          }
        ];
      })
  );

  const selectedFilters = () =>
    selectors.reduce((filters, select) => {
      filters[select.dataset.cesFilter] = select.value;
      return filters;
    }, {});

  const populateFilters = payload => {
    selectors.forEach(select => {
      const key = select.dataset.cesFilter;
      const selectedValue = payload.selected[key] || '';
      const options = [new Option(allOptions[key], ''), ...(payload.filters[key] || []).map(
        option => new Option(option.label, option.value)
      )];

      select.replaceChildren(...options);
      select.value = selectedValue;
    });
  };

  const setStatus = (message, type) => {
    statusElement.textContent = message;
    statusElement.className = `alert alert-${type}`;
    statusElement.hidden = false;
  };

  const clearStatus = () => {
    statusElement.textContent = '';
    statusElement.hidden = true;
  };

  const renderSummary = metrics => {
    metrics.forEach(metric => {
      const element = analyticsView.querySelector(`[data-ces-summary="${metric.key}"]`);
      animateNumber(element, metric.value === null ? 'N/A' : numberFormat.format(metric.value));
    });
    const enrolledMetric = metrics.find(metric => metric.key === 'total-enrolled-students');
    enrolledStudents = enrolledMetric ? enrolledMetric.value : 0;
  };

  const renderDemographics = metrics => {
    const dataByKey = new Map(metrics.map(metric => [metric.key, metric]));
    metrics
      .filter(metric => metric.display === 'count' && !metric.financial)
      .forEach(metric => {
        const totalElement = analyticsView.querySelector(`[data-ces-demographic-total="${metric.key}"]`);
        const noteElement = analyticsView.querySelector(`[data-ces-demographic-note="${metric.key}"]`);
        animateNumber(totalElement, metric.available ? numberFormat.format(metric.total) : 'N/A');
        noteElement.textContent = metric.note || 'No mapped CES data field is available for this metric.';
      });

    demographics.forEach((demographic, key) => {
      const { element, maleSegment, femaleSegment, emptyMessage } = demographic;
      const metric = dataByKey.get(key);

      if (metric) demographic.metric = metric;

      const totalElement = analyticsView.querySelector(`[data-ces-demographic-total="${key}"]`);
      const maleElement = analyticsView.querySelector(`[data-ces-male="${key}"]`);
      const femaleElement = analyticsView.querySelector(`[data-ces-female="${key}"]`);
      const maleShareElement = analyticsView.querySelector(`[data-ces-male-share="${key}"]`);
      const femaleShareElement = analyticsView.querySelector(`[data-ces-female-share="${key}"]`);

      if (!metric || !metric.available) {
        animateNumber(totalElement, 'N/A');
        animateNumber(maleElement, '—');
        animateNumber(femaleElement, '—');
        maleShareElement.textContent = '—';
        femaleShareElement.textContent = '—';
        element.hidden = true;
        emptyMessage.hidden = false;
        emptyMessage.textContent = 'No mapped CES data field is available for this metric.';
        return;
      }

      const total = Number(metric.total);
      const male = Number(metric.male);
      const female = Number(metric.female);
      const genderTotal = male + female;
      const maleShare = genderTotal ? (male / genderTotal) * 100 : 0;
      const femaleShare = genderTotal ? (female / genderTotal) * 100 : 0;

      animateNumber(totalElement, numberFormat.format(total));
      animateNumber(maleElement, numberFormat.format(male));
      animateNumber(femaleElement, numberFormat.format(female));
      maleShareElement.textContent = `${numberFormat.format(maleShare)}%`;
      femaleShareElement.textContent = `${numberFormat.format(femaleShare)}%`;
      maleSegment.style.width = `${maleShare}%`;
      femaleSegment.style.width = `${femaleShare}%`;
      element.hidden = total === 0;
      emptyMessage.hidden = total !== 0;
      emptyMessage.textContent = 'No students recorded in this group.';
      element.setAttribute(
        'aria-label',
        `${element.dataset.chartLabel}: ${numberFormat.format(male)} male, ${numberFormat.format(female)} female`
      );
    });

    animateNumber(
      overviewTitle,
      `Male vs female counts per group — ${numberFormat.format(enrolledStudents)} enrolled students`
    );

    if (overviewChart) {
      overviewChart.destroy();
      overviewChart = undefined;
      requestAnimationFrame(renderOverviewChart);
    }
  };

  const renderFinancialChart = ({ metrics, scholarships }) => {
    financialMetrics = metrics.filter(metric => metric.financial);
    financialMetrics.push(...scholarships.map(scholarship => ({
      key: `scholarship-${scholarship.name}`,
      label: scholarship.name,
      total: scholarship.total,
      available: true
    })));
    const categories = financialMetrics.map(metric => metric.label);
    const data = financialMetrics.map(metric => metric.available ? Number(metric.total) : 0);
    const colors = financialMetrics.map((metric, index) => {
      const hue = ((238 + index * 137.508) % 360) / 60;
      const chroma = 0.66;
      const secondary = chroma * (1 - Math.abs((hue % 2) - 1));
      const offset = 0.17;
      const rgb = hue < 1 ? [chroma, secondary, 0]
        : hue < 2 ? [secondary, chroma, 0]
          : hue < 3 ? [0, chroma, secondary]
            : hue < 4 ? [0, secondary, chroma]
              : hue < 5 ? [secondary, 0, chroma]
                : [chroma, 0, secondary];

      return `#${rgb.map(channel => Math.round((channel + offset) * 255).toString(16).padStart(2, '0')).join('')}`;
    });
    const notes = financialMetrics.map(metric => metric.note).filter(Boolean);

    financialNote.textContent = notes.join(' ');
    financialLegend.replaceChildren(...financialMetrics.map((metric, index) => {
      const item = document.createElement('span');
      const swatch = document.createElement('span');
      const label = document.createElement('span');

      item.className = 'qams-ces-financial-legend-item';
      item.setAttribute('role', 'listitem');
      swatch.className = 'qams-ces-financial-legend-swatch';
      swatch.style.backgroundColor = colors[index];
      swatch.setAttribute('aria-hidden', 'true');
      label.textContent = metric.label;
      item.append(swatch, label);

      return item;
    }));

    if (financialChart) financialChart.destroy();

    financialChart = new ApexCharts(financialChartElement, {
      chart: {
        type: 'bar',
        height: Math.max(220, categories.length * 36 + 90),
        toolbar: { show: false },
        animations: { enabled: true, speed: 400 },
        parentHeightOffset: 0
      },
      series: [{ name: 'Students', data }],
      colors,
      plotOptions: {
        bar: { horizontal: true, distributed: true, barHeight: '32%', borderRadius: 4, dataLabels: { position: 'top' } }
      },
      dataLabels: {
        enabled: true,
        formatter: (value, { dataPointIndex }) => {
          const metric = financialMetrics[dataPointIndex];

          return metric && metric.available ? numberFormat.format(value) : 'N/A';
        },
        offsetX: 8,
        style: { colors: ['#566a7f'] }
      },
      tooltip: {
        y: {
          formatter: (value, { dataPointIndex }) => {
            const metric = financialMetrics[dataPointIndex];

            return metric && metric.available
              ? `${numberFormat.format(value)} students`
              : 'Unavailable';
          }
        }
      },
      grid: { borderColor: '#eef0f4', strokeDashArray: 4, padding: { left: 8, right: 24 } },
      xaxis: {
        categories,
        min: 0,
        tickAmount: 4,
        labels: { formatter: value => numberFormat.format(value), style: { colors: '#7a8699' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: { labels: { maxWidth: 250, style: { colors: '#566a7f' } } },
      legend: { show: false }
    });
    financialChart.render();
  };

  const renderAnalytics = payload => {
    populateFilters(payload);
    renderSummary(payload.summary);
    renderDemographics(payload.demographics);
    renderFinancialChart({
      metrics: payload.demographics,
      scholarships: payload.scholarships || []
    });
    clearStatus();
  };

  const refreshAnalytics = async () => {
    if (requestController) requestController.abort();
    requestController = new AbortController();
    clearStatus();

    const filters = selectedFilters();
    const query = Object.keys(filters)
      .filter(key => filters[key] !== '')
      .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(filters[key])}`)
      .join('&');
    const url = new URL(analyticsView.dataset.cesAnalyticsUrl, window.location.origin);
    url.search = query;

    try {
      const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: requestController.signal
      });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.message || 'CES analytics could not be loaded.');
      }

      renderAnalytics(payload);
    } catch (error) {
      if (error.name === 'AbortError') return;

      setStatus(error.message || 'The stored CES analytics snapshot could not be loaded.', 'danger');
    }
  };

  const renderOverviewChart = () => {
    if (overviewChart) return;

    overviewChart = new ApexCharts(analyticsView.querySelector('[data-ces-overview-chart]'), {
      chart: {
        type: 'bar',
        height: Math.max(280, demographics.size * 38 + 72),
        stacked: true,
        toolbar: { show: false },
        animations: {
          enabled: true,
          easing: 'easeinout',
          speed: 450,
          dynamicAnimation: { enabled: true, speed: 350 }
        },
        parentHeightOffset: 0
      },
      series: [
        { name: 'Male', data: [...demographics.values()].map(({ metric }) => Number(metric.male || 0)) },
        { name: 'Female', data: [...demographics.values()].map(({ metric }) => Number(metric.female || 0)) }
      ],
      colors: ['#696cff', '#ff6f91'],
      plotOptions: {
        bar: { horizontal: true, barHeight: '55%', borderRadius: 3, borderRadiusApplication: 'end' }
      },
      dataLabels: { enabled: false },
      legend: { position: 'bottom', horizontalAlign: 'center', markers: { radius: 12 } },
      tooltip: { y: { formatter: value => `${numberFormat.format(value)} students` } },
      grid: { borderColor: '#eef0f4', strokeDashArray: 4, padding: { left: 8, right: 16 } },
      xaxis: {
        categories: [...demographics.values()].map(({ metric }) => metric.label),
        min: 0,
        tickAmount: 4,
        labels: { formatter: value => numberFormat.format(value), style: { colors: '#7a8699' } },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: { labels: { maxWidth: 190, style: { colors: '#566a7f' } } }
    });
    overviewChart.render();
  };

  analyticsView.querySelectorAll('[data-ces-view]').forEach(button => {
    button.addEventListener('click', () => {
      const showChart = button.dataset.cesView === 'chart';

      cardsView.hidden = showChart;
      overviewView.hidden = !showChart;
      if (!showChart && overviewChart) {
        overviewChart.destroy();
        overviewChart = undefined;
      }
      analyticsView.querySelectorAll('[data-ces-view]').forEach(viewButton => {
        const isActive = viewButton === button;

        viewButton.classList.toggle('active', isActive);
        viewButton.setAttribute('aria-pressed', String(isActive));
      });

      if (showChart) requestAnimationFrame(renderOverviewChart);
    });
  });

  selectors.forEach(select => {
    select.addEventListener('change', () => {
      if (select.dataset.cesFilter === 'campus') {
        analyticsView.querySelector('[data-ces-filter="college"]').value = '';
        analyticsView.querySelector('[data-ces-filter="program"]').value = '';
        analyticsView.querySelector('[data-ces-filter="year"]').value = '';
        analyticsView.querySelector('[data-ces-filter="semester"]').value = '';
      } else if (select.dataset.cesFilter === 'college') {
        analyticsView.querySelector('[data-ces-filter="program"]').value = '';
      } else if (select.dataset.cesFilter === 'year') {
        analyticsView.querySelector('[data-ces-filter="semester"]').value = '';
      }

      refreshAnalytics();
    });
  });

  refreshAnalytics();
}
