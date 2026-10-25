import './bootstrap';
import Chart from 'chart.js/auto';

window.Chart = Chart;

Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#475569';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 10;

window.excelliaCharts = window.excelliaCharts || {};

window.renderExcelliaChart = function (canvasId, config) {
    const el = document.getElementById(canvasId);
    if (!el) {
        return null;
    }
    if (window.excelliaCharts[canvasId]) {
        window.excelliaCharts[canvasId].destroy();
    }
    const instance = new Chart(el, config);
    window.excelliaCharts[canvasId] = instance;
    return instance;
};
