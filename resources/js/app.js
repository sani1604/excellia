import './bootstrap';
import { ExcelliaChart } from './excellia-charts';

window.excelliaCharts = window.excelliaCharts || {};

window.renderExcelliaChart = function (canvasId, config) {
    const el = document.getElementById(canvasId);
    if (!el) {
        return null;
    }
    if (window.excelliaCharts[canvasId]) {
        window.excelliaCharts[canvasId].destroy();
    }
    const instance = new ExcelliaChart(el, config);
    window.excelliaCharts[canvasId] = instance;
    return instance;
};
