import { SystemDashboardChart } from './system-dashboard-chart.js';
import { animateNumber } from './system-number-animation.js';

const analyticsView = document.querySelector('[data-ces-analytics]');

if (analyticsView) {
  const analytics = JSON.parse(analyticsView.querySelector('[data-ces-analytics-data]').textContent);
  const statusElement = analyticsView.querySelector('[data-ces-analytics-status]');
  const numberFormat = new Intl.NumberFormat();
  const cardsView = analyticsView.querySelector('[data-ces-demographic-cards]');
  const overviewView = analyticsView.querySelector('[data-ces-demographic-overview]');
  const overviewTitle = analyticsView.querySelector('[data-ces-overview-title]');
  const overviewChartElement = analyticsView.querySelector('[data-ces-overview-chart]');
  const financialChartElement = analyticsView.querySelector('[data-ces-financial-chart]');
  const financialLegend = analyticsView.querySelector('[data-ces-financial-legend]');
  const financialContent = analyticsView.querySelector('[data-ces-financial-content]');
  const financialLoading = analyticsView.querySelector('[data-ces-financial-loading]');
  const studentGenderPlot = analyticsView.querySelector('[data-ces-student-gender-chart]');
  const studentGenderStatus = analyticsView.querySelector('[data-ces-student-gender-status]');
  const trendPlot = analyticsView.querySelector('[data-ces-enrollment-trend-chart]');
  const trendStatus = analyticsView.querySelector('[data-ces-enrollment-trend-status]');
  const selectors = [...analyticsView.querySelectorAll('[data-ces-filter]')];
  const allOptions = {
    campus: 'All Campuses',
    college: 'All Faculties',
    program: 'All Programs',
    year: 'Latest Academic Year',
    semester: 'Latest Semester'
  };
  let overviewChart;
  let overviewMetrics = [];
  let financialChart;
  let studentGenderChart;
  let enrollmentTrendChart;
  let requestController;
  let enrolledStudents = 0;
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const percentageFormat = new Intl.NumberFormat(undefined, { maximumFractionDigits: 1 });
  const demographics = new Map(
    analytics.demographics
      .filter(metric => metric.display === 'gender')
      .map(metric => {
        const element = analyticsView.querySelector(`[data-ces-demographic-chart="${metric.key}"]`);
        return [metric.key, { element, label: element.getAttribute('aria-label'), series: null, chart: null }];
      })
  );

  const destroyDemographicDonut = demographic => {
    demographic.chart?.destroy();
    demographic.chart = null;
  };

  const renderDemographicDonut = demographic => {
    if (cardsView.hidden) return;
    if (!demographic.series) {
      destroyDemographicDonut(demographic);
      return;
    }

    const animate = !reducedMotion.matches;
    if (demographic.chart) {
      demographic.chart.updateSeries(demographic.series, animate);
      return;
    }

    demographic.chart = new SystemDashboardChart(demographic.element, {
      chart: {
        type: 'donut',
        width: 216,
        height: 216,
        sparkline: { enabled: true },
        toolbar: { show: false },
        animations: { enabled: animate, speed: 650, dynamicAnimation: { enabled: animate, speed: 450 } },
        parentHeightOffset: 0
      },
      series: demographic.series,
      labels: ['Male', 'Female'],
      colors: ['#696cff', '#ff6f91'],
      stroke: { width: 0 },
      dataLabels: {
        enabled: true,
        formatter: value => percentageFormat.format(value) + '%',
        style: { fontSize: '12px', fontWeight: 700, colors: ['#fff'] },
        dropShadow: { enabled: false }
      },
      legend: { show: false },
      plotOptions: {
        pie: {
          expandOnClick: true,
          customScale: 0.88,
          donut: { size: '50%' }
        }
      },
      states: { active: { filter: { type: 'none' } } },
      tooltip: {
        enabled: true,
        cssClass: 'qams-ces-demographic-tooltip',
        y: { formatter: value => `${numberFormat.format(value)} students` }
      }
    });
    demographic.chart.render();
  };

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
    overviewMetrics = metrics.filter(metric => !metric.financial);
    overviewMetrics.forEach(metric => {
      const card = cardsView.querySelector('[data-ces-demographic-card="' + metric.key + '"]');
      const count = card.querySelector('[data-ces-demographic-count]');
      const total = metric.available ? Number(metric.total) : 0;
      const male = Number(metric.male || 0);
      const female = Number(metric.female || 0);
      const demographic = demographics.get(metric.key);
      const hasBreakdown = Boolean(demographic && total && (male || female));
      const fallbackLabel = card.querySelector('[data-ces-demographic-empty-label]');
      const fallbackRing = card.querySelector('[data-ces-demographic-empty]');

      count.hidden = total === 0;
      animateNumber(count.querySelector('[data-ces-demographic-total]'), numberFormat.format(total));
      fallbackLabel.hidden = hasBreakdown;
      fallbackLabel.textContent = total > 0 ? 'Student records' : 'No Student Record';
      fallbackRing.hidden = hasBreakdown;

      if (demographic) {
        const { element } = demographic;
        demographic.series = hasBreakdown ? [male, female] : null;
        element.hidden = !hasBreakdown;
        card.querySelector('[data-ces-gender-values]').hidden = !hasBreakdown;
        if (hasBreakdown) {
          animateNumber(card.querySelector('[data-ces-male]'), numberFormat.format(male));
          animateNumber(card.querySelector('[data-ces-female]'), numberFormat.format(female));
          element.setAttribute('aria-label',
            demographic.label + ': ' + numberFormat.format(male) + ' male, ' +
            numberFormat.format(female) + ' female');
        }
        renderDemographicDonut(demographic);
      }
    });

    animateNumber(
      overviewTitle,
      `Student counts per group — ${numberFormat.format(enrolledStudents)} enrolled students`
    );

    if (overviewChart) {
      overviewChart.destroy();
      overviewChart = undefined;
    }
    if (!overviewView.hidden) requestAnimationFrame(renderOverviewChart);
  };

  const renderFinancialChart = ({ metrics, aidBreakdown }) => {
    const aid = metrics.find(metric => metric.key === 'financial-aid-recipients');
    const income = metrics.find(metric => metric.key === 'household-income');
    const colors = [
      'success', 'cyan', 'indigo', 'pink', 'danger', 'warning', 'primary-text-emphasis', 'primary', 'teal', 'dark',
      'purple'
    ];
    const aidSeries = aidBreakdown
      .map((entry, index) => ({ ...entry, color: colors[index] }))
      .filter(entry => entry.total > 0);
    const minimumSegment = aid.total * 0.02;
    const addedWidth = aidSeries.reduce((sum, entry) => sum + Math.max(0, minimumSegment - entry.total), 0);
    const flexibleWidth = aidSeries.reduce((sum, entry) => sum + Math.max(0, entry.total - minimumSegment), 0);
    const theme = getComputedStyle(financialChartElement);
    const themeColor = name => theme.getPropertyValue('--bs-' + name).trim();
    const incomeSeries = { name: 'Reported Annual Income', total: income.available ? income.total : 0, color: 'info' };
    const legendEntries = [...aidSeries, incomeSeries].filter(entry => entry.total > 0);
    financialLegend.replaceChildren(...legendEntries.map(entry => {
      const item = document.createElement('li');
      const swatch = document.createElement('span');
      item.className = 'd-flex align-items-center gap-1';
      swatch.className = 'qams-ces-financial-swatch rounded-1';
      swatch.style.backgroundColor = themeColor(entry.color);
      swatch.setAttribute('aria-hidden', 'true');
      item.append(swatch, entry.name);
      return item;
    }));
    financialLegend.hidden = legendEntries.length === 0;
    const categories = [aid, income].map(metric => metric.label + (metric.available ? '' : ' (N/A)'));
    const series = [
      ...aidSeries.map(entry => ({
        name: entry.name,
        data: [
          entry.total < minimumSegment
            ? minimumSegment
            : entry.total - (addedWidth * (entry.total - minimumSegment)) / (flexibleWidth || 1),
          0
        ]
      })),
      { name: incomeSeries.name, data: [0, incomeSeries.total] }
    ];
    const tooltips = [[aid, aidSeries], [income, [incomeSeries]]].map(([metric, entries]) => {
      const tooltip = document.createElement('div');
      const title = document.createElement('div');
      title.className = 'apexcharts-tooltip-title';
      title.textContent = metric.label;
      tooltip.append(title);

      entries.forEach(entry => {
        const row = document.createElement('div');
        const swatch = document.createElement('span');
        const text = document.createElement('div');
        const label = document.createElement('span');
        const value = document.createElement('span');
        row.className = 'apexcharts-tooltip-series-group d-flex';
        swatch.className = 'apexcharts-tooltip-marker';
        swatch.style.backgroundColor = themeColor(entry.color);
        text.className = 'apexcharts-tooltip-text apexcharts-tooltip-y-group';
        label.className = 'apexcharts-tooltip-text-y-label';
        label.textContent = entry.name + ':';
        value.className = 'apexcharts-tooltip-text-y-value';
        value.textContent = numberFormat.format(entry.total) + ' students';
        text.append(label, value);
        row.append(swatch, text);
        tooltip.append(row);
      });

      return tooltip.outerHTML;
    });

    financialLoading.hidden = true;
    financialContent.hidden = false;

    if (financialChart) financialChart.destroy();
    financialChartElement.setAttribute('aria-label', [aid, income].map(metric =>
      metric.label + ': ' + (metric.available ? numberFormat.format(metric.total) : 'N/A')
    ).join('. '));

    financialChart = new SystemDashboardChart(financialChartElement, {
      chart: {
        type: 'bar',
        height: 190,
        stacked: true,
        fontFamily: window.config.fontFamily,
        foreColor: themeColor('body-color'),
        toolbar: { show: false },
        animations: { speed: 350 },
        parentHeightOffset: 0
      },
      series,
      colors: [...aidSeries, incomeSeries].map(entry => themeColor(entry.color)),
      plotOptions: {
        bar: {
          horizontal: true,
          barHeight: '55%',
          borderRadius: 3,
          borderRadiusApplication: 'end',
          dataLabels: {
            total: {
              enabled: true,
              offsetX: 8,
              formatter: (_total, { dataPointIndex }) => {
                const metric = [aid, income][dataPointIndex];
                return metric.available ? numberFormat.format(metric.total) : '';
              },
              style: { color: themeColor('heading-color'), fontSize: '13px', fontWeight: 700 }
            }
          }
        }
      },
      fill: { opacity: 1 },
      states: {
        hover: { filter: { type: 'darken' } },
        active: { filter: { type: 'none' } }
      },
      dataLabels: { enabled: false },
      legend: { show: false },
      tooltip: {
        shared: false,
        intersect: true,
        custom: ({ dataPointIndex }) => tooltips[dataPointIndex] || ''
      },
      grid: {
        borderColor: themeColor('border-color'),
        strokeDashArray: 4,
        xaxis: { lines: { show: true } },
        padding: { left: 8, right: 52 }
      },
      xaxis: {
        categories,
        min: 0,
        max: aid.total > 0 || (income.available && income.total > 0) ? undefined : 4,
        tickAmount: 4,
        labels: { formatter: value => numberFormat.format(value) },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: { labels: { maxWidth: 250 } },
      responsive: [{
        breakpoint: 575,
        options: {
          yaxis: {
            labels: {
              maxWidth: 108,
              formatter: value => value === categories[0] ? 'Financial Aid' : (income.available ? 'Annual Income' : 'Income (N/A)')
            }
          }
        }
      }]
    });
    financialChart.render();
  };

  const renderStudentGender = gender => {
    const entries = [
      { label: 'Male', total: gender?.male ?? 0, color: '#696cff' },
      { label: 'Female', total: gender?.female ?? 0, color: '#ff6f91' },
      { label: 'Unknown Gender', total: gender?.unknown_gender ?? 0, color: window.config.colors.secondary }
    ].filter(entry => entry.total > 0);

    studentGenderChart?.destroy();
    studentGenderChart = undefined;
    studentGenderPlot.hidden = entries.length === 0;
    studentGenderStatus.hidden = entries.length > 0;
    if (entries.length === 0) {
      studentGenderStatus.textContent = gender === null ? 'Student gender data is unavailable.' : 'No students recorded.';
      return;
    }

    studentGenderPlot.setAttribute('role', 'img');
    studentGenderPlot.setAttribute('aria-label', entries.map(entry =>
      `${entry.label}: ${numberFormat.format(entry.total)}`
    ).join(', '));
    studentGenderChart = new SystemDashboardChart(studentGenderPlot, {
      chart: { type: 'donut', height: 300, parentHeightOffset: 0, fontFamily: window.config.fontFamily, toolbar: { show: false } },
      series: entries.map(entry => entry.total),
      labels: entries.map(entry => entry.label),
      colors: entries.map(entry => entry.color),
      stroke: { width: 0 },
      plotOptions: { pie: { customScale: 0.9, donut: { size: '58%' } } },
      dataLabels: {
        formatter: value => `${numberFormat.format(Math.round(value * 10) / 10)}%`,
        dropShadow: { enabled: false }
      },
      legend: {
        position: 'bottom',
        onItemClick: { toggleDataSeries: false },
        formatter: (label, { seriesIndex }) => `${label} ${numberFormat.format(entries[seriesIndex].total)}`
      },
      tooltip: { y: { formatter: value => `${numberFormat.format(value)} students` } }
    });
    studentGenderChart.render();
  };

  const renderEnrollmentTrend = (periods, semester) => {
    enrollmentTrendChart?.destroy();
    enrollmentTrendChart = undefined;
    trendPlot.hidden = periods.length === 0;
    if (periods.length === 0) {
      trendStatus.textContent = 'No enrollment history is available for these filters.';
      return;
    }

    const term = { '1': 'Semester 1', '2': 'Semester 2', '9': 'Summer' }[semester] ?? 'Selected semester';
    const partialCoverage = periods.some(period => period.partial);
    trendStatus.textContent = partialCoverage
      ? `${term} by academic year. * Incomplete campus coverage.`
      : `${term} by academic year.`;
    trendPlot.setAttribute('role', 'img');
    trendPlot.setAttribute('aria-label', periods.map(period =>
      `${period.label}: ${numberFormat.format(period.total)} students${period.partial ? ', incomplete campus coverage' : ''}`
    ).join('; '));
    enrollmentTrendChart = new SystemDashboardChart(trendPlot, {
      chart: { type: 'area', height: 275, parentHeightOffset: 0, fontFamily: window.config.fontFamily, toolbar: { show: false }, zoom: { enabled: false } },
      series: [{ name: 'Enrolled students', data: periods.map(period => period.total) }],
      colors: [window.config.colors.primary],
      stroke: { curve: 'straight', width: 3 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.04 } },
      markers: { size: 4 },
      dataLabels: { enabled: false },
      grid: { borderColor: window.config.colors.borderColor, strokeDashArray: 4 },
      xaxis: {
        categories: periods.map(period => period.label + (period.partial ? '*' : '')),
        axisTicks: { show: false }
      },
      yaxis: { min: 0, forceNiceScale: true, labels: { formatter: value => numberFormat.format(value) } },
      tooltip: { y: { formatter: value => `${numberFormat.format(value)} students` } }
    });
    enrollmentTrendChart.render();
  };

  const renderAnalytics = payload => {
    populateFilters(payload);
    renderSummary(payload.summary);
    renderStudentGender(payload.student_gender ?? null);
    renderEnrollmentTrend(payload.enrollment_trend ?? [], payload.selected.semester);
    renderDemographics(payload.demographics);
    renderFinancialChart({
      metrics: payload.demographics,
      aidBreakdown: payload.financial_aid_breakdown || []
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

      financialLoading.hidden = true;
      if (!studentGenderChart) studentGenderStatus.textContent = 'Student gender data is unavailable.';
      if (!enrollmentTrendChart) trendStatus.textContent = 'Enrollment history is unavailable.';
      setStatus(error.message || 'CES warehouse analytics could not be loaded.', 'danger');
    }
  };

  const renderOverviewChart = () => {
    if (overviewChart || overviewView.hidden || overviewMetrics.length === 0) return;

    const male = overviewMetrics.map(metric => metric.available ? Number(metric.male || 0) : 0);
    const female = overviewMetrics.map(metric => metric.available ? Number(metric.female || 0) : 0);
    const noBreakdown = overviewMetrics.map((metric, index) =>
      metric.available ? Math.max(0, Number(metric.total) - male[index] - female[index]) : 0
    );
    const series = [{ name: 'Male', data: male }, { name: 'Female', data: female }];
    const colors = ['#696cff', '#ff6f91'];
    if (noBreakdown.some(total => total > 0)) {
      series.push({ name: 'Without gender breakdown', data: noBreakdown });
      colors.push(window.config.colors.success);
    }

    overviewChart = new SystemDashboardChart(overviewChartElement, {
      chart: {
        type: 'bar',
        height: Math.max(280, overviewMetrics.length * 38 + 72),
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
      series,
      colors,
      plotOptions: {
        bar: {
          horizontal: true,
          barHeight: '55%',
          borderRadius: 3,
          borderRadiusApplication: 'end',
          dataLabels: {
            total: {
              enabled: true,
              offsetX: 8,
              formatter: total => numberFormat.format(total),
              style: { color: window.config.colors.headingColor, fontSize: '13px', fontWeight: 700 }
            }
          }
        }
      },
      dataLabels: { enabled: false },
      legend: { position: 'bottom', horizontalAlign: 'center', markers: { radius: 12 } },
      tooltip: { y: { formatter: value => `${numberFormat.format(value)} students` } },
      grid: {
        borderColor: window.config.colors.borderColor,
        strokeDashArray: 4,
        xaxis: { lines: { show: true } },
        padding: { left: 8, right: 52 }
      },
      xaxis: {
        categories: overviewMetrics.map(metric => metric.label),
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
      if (cardsView.hidden === showChart) return;

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

      if (showChart) {
        demographics.forEach(destroyDemographicDonut);
        requestAnimationFrame(renderOverviewChart);
      } else {
        requestAnimationFrame(() => demographics.forEach(renderDemographicDonut));
      }
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
