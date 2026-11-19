import { ApexCharts } from '../vendor/libs/apex-charts/apexcharts.js';

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

// Use for dashboard charts so their entrance animation starts when the plot is visible.
export class SystemDashboardChart {
  constructor(element, options) {
    this.element = element;
    this.options = options;
  }

  render() {
    const mount = (animate = true) => {
      this.stopObserving();
      const { chart = {} } = this.options;
      this.chart = new ApexCharts(this.element, {
        ...this.options,
        chart: {
          ...chart,
          animations: {
            ...chart.animations,
            enabled: animate && !reducedMotion.matches && chart.animations?.enabled !== false
          }
        }
      });
      return this.chart.render();
    };

    if (this.chart || this.observer) return;
    if (!window.IntersectionObserver) return mount(false);

    // Keep space for the plot so offscreen charts have an observable area.
    this.originalMinHeight = this.element.style.minHeight;
    const height = this.options.chart?.height;
    this.element.style.minHeight = `${typeof height === 'number' ? height : 180}px`;
    this.observer = new IntersectionObserver(([entry]) => {
      if (this.observer && entry.isIntersecting && entry.intersectionRatio > 0) mount();
    });
    this.observer.observe(this.element);
  }

  updateSeries(series, animate = true) {
    this.options = { ...this.options, series };
    if (!this.chart) return;

    const bounds = this.element.getBoundingClientRect();
    const visible = bounds.width > 0 && bounds.height > 0
      && bounds.bottom > 0 && bounds.right > 0
      && bounds.top < window.innerHeight && bounds.left < window.innerWidth;
    return this.chart.updateSeries(series, animate && visible && !reducedMotion.matches);
  }

  destroy() {
    this.stopObserving();
    this.chart?.destroy();
    this.chart = undefined;
  }

  stopObserving() {
    if (!this.observer) return;
    this.observer.disconnect();
    this.observer = undefined;
    this.element.style.minHeight = this.originalMinHeight;
  }
}
