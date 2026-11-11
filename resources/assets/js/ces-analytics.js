import { ApexCharts } from '../vendor/libs/apex-charts/apexcharts.js';
import { animateNumber } from './system-number-animation.js';

const analyticsView = document.querySelector('[data-ces-analytics]');

if (analyticsView) {
  const analytics = JSON.parse(analyticsView.querySelector('[data-ces-analytics-data]').textContent);
  const demographics = new Map();
  const numberFormat = new Intl.NumberFormat();
  const cardsView = analyticsView.querySelector('[data-ces-demographic-cards]');
  const overviewView = analyticsView.querySelector('[data-ces-demographic-overview]');
  const overviewTitle = analyticsView.querySelector('[data-ces-overview-title]');
  let overviewChart;

  analyticsView.querySelectorAll('[data-ces-demographic-chart]').forEach(element => {
    const key = element.dataset.cesDemographicChart;
    const male = Number(element.dataset.male);
    const female = Number(element.dataset.female);
    const maleSegment = element.querySelector('[data-ces-male-segment]');
    const femaleSegment = element.querySelector('[data-ces-female-segment]');

    element.dataset.chartLabel = element.getAttribute('aria-label');
    demographics.set(key, {
      element,
      maleSegment,
      femaleSegment,
      male,
      female,
      filteredMale: male,
      filteredFemale: female
    });
    const total = male + female;

    element.hidden = total === 0;
    analyticsView.querySelector(`[data-ces-demographic-empty="${key}"]`).hidden = total !== 0;
  });

  const updateAnalytics = () => {
    const selectedFactors = [...analyticsView.querySelectorAll('[data-ces-filter]')].map(select => {
      const selectedOption = analytics.filters[select.dataset.cesFilter].find(option => option.value === select.value);

      return selectedOption ? selectedOption.factor : 1;
    });
    const factor = selectedFactors.reduce((product, selectedFactor) => product * selectedFactor, 1);

    analytics.summary.forEach(metric => {
      const value = Math.round(metric.value * factor);
      const element = analyticsView.querySelector(`[data-ces-summary="${metric.key}"]`);

      animateNumber(element, numberFormat.format(value));
    });

    const filteredDemographics = [...demographics.entries()].map(([key, demographic]) => ({
      key,
      demographic,
      male: Math.round(demographic.male * factor),
      female: Math.round(demographic.female * factor)
    }));
    const totalStudents = filteredDemographics.reduce((sum, item) => sum + item.male + item.female, 0);

    filteredDemographics.forEach(({ key, demographic, male: filteredMale, female: filteredFemale }) => {
      const { element, maleSegment, femaleSegment } = demographic;
      const total = filteredMale + filteredFemale;
      const maleShare = total ? (filteredMale / total) * 100 : 0;
      const femaleShare = total ? (filteredFemale / total) * 100 : 0;

      demographic.filteredMale = filteredMale;
      demographic.filteredFemale = filteredFemale;
      maleSegment.style.width = `${maleShare}%`;
      femaleSegment.style.width = `${femaleShare}%`;
      animateNumber(analyticsView.querySelector(`[data-ces-demographic-total="${key}"]`), numberFormat.format(total));
      animateNumber(analyticsView.querySelector(`[data-ces-male="${key}"]`), numberFormat.format(filteredMale));
      animateNumber(analyticsView.querySelector(`[data-ces-female="${key}"]`), numberFormat.format(filteredFemale));
      analyticsView.querySelector(`[data-ces-male-share="${key}"]`).textContent = `${numberFormat.format(maleShare)}%`;
      analyticsView.querySelector(`[data-ces-female-share="${key}"]`).textContent =
        `${numberFormat.format(femaleShare)}%`;
      analyticsView.querySelector(`[data-ces-demographic-empty="${key}"]`).hidden = total !== 0;
      element.hidden = total === 0;
      element.setAttribute(
        'aria-label',
        `${element.dataset.chartLabel}: ${numberFormat.format(filteredMale)} male, ${numberFormat.format(filteredFemale)} female`
      );
    });

    animateNumber(
      overviewTitle,
      `Male vs female counts per group — ${numberFormat.format(totalStudents)} students across special populations`
    );
    if (overviewChart) {
      const values = [...demographics.values()];
      overviewChart.updateOptions(
        {
          series: [
            { name: 'Male', data: values.map(demographic => demographic.filteredMale) },
            { name: 'Female', data: values.map(demographic => demographic.filteredFemale) }
          ],
          xaxis: { max: getAxisMax(values) }
        },
        true,
        true
      );
    }
  };

  const getAxisMax = demographicsList => {
    const largestGroup = Math.max(
      ...demographicsList.map(({ filteredMale, filteredFemale }) => filteredMale + filteredFemale),
      1
    );
    const rawInterval = largestGroup / 4;
    const magnitude = 10 ** Math.floor(Math.log10(rawInterval));
    const normalizedInterval = rawInterval / magnitude;
    const interval =
      (normalizedInterval <= 1 ? 1 : normalizedInterval <= 2 ? 2 : normalizedInterval <= 5 ? 5 : 10) * magnitude;

    return Math.ceil(largestGroup / (interval * 4)) * interval * 4;
  };

  const renderOverviewChart = () => {
    if (overviewChart) return;

    const values = [...demographics.values()];
    overviewChart = new ApexCharts(analyticsView.querySelector('[data-ces-overview-chart]'), {
      chart: {
        type: 'bar',
        height: Math.max(280, values.length * 38 + 72),
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
        { name: 'Male', data: values.map(demographic => demographic.filteredMale) },
        { name: 'Female', data: values.map(demographic => demographic.filteredFemale) }
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
        categories: [...demographics.values()].map(({ element }) => {
          const item = analytics.demographics.find(
            demographic => demographic.key === element.dataset.cesDemographicChart
          );

          return item.label;
        }),
        min: 0,
        max: getAxisMax(values),
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
      analyticsView.querySelectorAll('[data-ces-view]').forEach(viewButton => {
        const isActive = viewButton === button;

        viewButton.classList.toggle('active', isActive);
        viewButton.setAttribute('aria-pressed', String(isActive));
      });

      if (showChart) requestAnimationFrame(renderOverviewChart);
    });
  });

  analyticsView.querySelectorAll('[data-ces-filter]').forEach(select => {
    select.addEventListener('change', updateAnalytics);
  });

  updateAnalytics();
}
