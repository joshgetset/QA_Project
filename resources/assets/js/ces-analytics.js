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
  const selectors = [...analyticsView.querySelectorAll('[data-ces-filter]')];
  const allOptions = {
    campus: 'All Campuses',
    college: 'All Colleges',
    program: 'All Programs',
    year: 'Latest Academic Year'
  };
  let overviewChart;
  let requestController;
  let enrolledStudents = 0;

  const demographics = new Map(
    analytics.demographics.map(metric => {
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
      animateNumber(element, numberFormat.format(metric.value));
    });
    const enrolledMetric = metrics.find(metric => metric.key === 'total-enrolled-students');
    enrolledStudents = enrolledMetric ? enrolledMetric.value : 0;
  };

  const renderDemographics = metrics => {
    const dataByKey = new Map(metrics.map(metric => [metric.key, metric]));

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
      overviewChart.updateSeries([
        { name: 'Male', data: [...demographics.values()].map(({ metric }) => Number((dataByKey.get(metric.key) || {}).male || 0)) },
        { name: 'Female', data: [...demographics.values()].map(({ metric }) => Number((dataByKey.get(metric.key) || {}).female || 0)) }
      ], true);
    }
  };

  const renderAnalytics = payload => {
    populateFilters(payload);
    renderSummary(payload.summary);
    renderDemographics(payload.demographics);

    const unavailableCampuses = payload.campuses
      .filter(campus => campus.status !== 'available')
      .map(campus => campus.name);

    if (unavailableCampuses.length) {
      setStatus(`Partial CES data. Unavailable campus data: ${unavailableCampuses.join(', ')}.`, 'warning');
    } else {
      clearStatus();
    }
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

      setStatus('Live CES data could not be loaded. Check the CES database connection and try again.', 'danger');
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
      } else if (select.dataset.cesFilter === 'college') {
        analyticsView.querySelector('[data-ces-filter="program"]').value = '';
      }

      refreshAnalytics();
    });
  });

  refreshAnalytics();
}
