/**
 * Excellia Native HiDPI Canvas Chart Engine
 * Zero external dependencies — supports 'pie', 'doughnut', 'bar' (grouped, stacked, horizontal), and 'line'
 * with Retina scaling, legends, hover tooltips, doughnut center totals, and ResizeObserver responsiveness.
 */

const PALETTE = [
    '#4f46e5', '#10b981', '#0ea5e9', '#f59e0b', '#f43f5e',
    '#8b5cf6', '#14b8a6', '#f97316', '#ec4899', '#64748b',
];

let sharedTooltip = null;
function getTooltipEl() {
    if (!sharedTooltip && typeof document !== 'undefined') {
        sharedTooltip = document.createElement('div');
        sharedTooltip.style.cssText = [
            'position:fixed',
            'z-index:9999',
            'pointer-events:none',
            'background:rgba(15,23,42,0.94)',
            'color:#f8fafc',
            'padding:8px 11px',
            'border-radius:10px',
            'font-size:12px',
            'line-height:1.45',
            'box-shadow:0 10px 25px -5px rgba(0,0,0,0.25)',
            'opacity:0',
            'transition:opacity 0.12s ease',
            'max-width:260px',
        ].join(';');
        document.body.appendChild(sharedTooltip);
    }
    return sharedTooltip;
}

function showTooltip(clientX, clientY, html) {
    const el = getTooltipEl();
    if (!el) return;
    el.innerHTML = html;
    el.style.opacity = '1';
    const pad = 14;
    const rect = el.getBoundingClientRect();
    let x = clientX + pad;
    let y = clientY - rect.height / 2;
    if (x + rect.width > window.innerWidth - 10) {
        x = clientX - rect.width - pad;
    }
    if (y < 10) y = 10;
    if (y + rect.height > window.innerHeight - 10) {
        y = window.innerHeight - rect.height - 10;
    }
    el.style.left = `${Math.round(x)}px`;
    el.style.top = `${Math.round(y)}px`;
}

function hideTooltip() {
    const el = getTooltipEl();
    if (el) el.style.opacity = '0';
}

function niceStep(maxVal, targetTicks = 5) {
    if (maxVal <= 0) return 1;
    const rough = maxVal / targetTicks;
    const pow = Math.pow(10, Math.floor(Math.log10(rough)));
    const frac = rough / pow;
    let niceFrac = 1;
    if (frac <= 1) niceFrac = 1;
    else if (frac <= 2) niceFrac = 2;
    else if (frac <= 5) niceFrac = 5;
    else niceFrac = 10;
    return Math.max(1, Math.ceil(niceFrac * pow));
}

function roundedRect(ctx, x, y, w, h, r) {
    if (w <= 0 || h <= 0) return;
    const radius = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + radius, y);
    ctx.lineTo(x + w - radius, y);
    ctx.quadraticCurveTo(x + w, y, x + w, y + radius);
    ctx.lineTo(x + w, y + h - radius);
    ctx.quadraticCurveTo(x + w, y + h, x + w - radius, y + h);
    ctx.lineTo(x + radius, y + h);
    ctx.quadraticCurveTo(x, y + h, x, y + h - radius);
    ctx.lineTo(x, y + radius);
    ctx.quadraticCurveTo(x, y, x + radius, y);
    ctx.closePath();
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

export class ExcelliaChart {
    constructor(canvas, config) {
        this.canvas = canvas;
        this.config = config || {};
        this.hitRegions = [];
        this.destroyed = false;

        this.boundMove = this.onPointerMove.bind(this);
        this.boundLeave = () => hideTooltip();

        this.canvas.addEventListener('mousemove', this.boundMove);
        this.canvas.addEventListener('mouseleave', this.boundLeave);

        if (typeof ResizeObserver !== 'undefined' && this.canvas.parentElement) {
            this.ro = new ResizeObserver(() => {
                if (!this.destroyed) this.draw();
            });
            this.ro.observe(this.canvas.parentElement);
        }

        this.draw();
    }

    destroy() {
        this.destroyed = true;
        this.canvas.removeEventListener('mousemove', this.boundMove);
        this.canvas.removeEventListener('mouseleave', this.boundLeave);
        if (this.ro) this.ro.disconnect();
        hideTooltip();
    }

    onPointerMove(evt) {
        const rect = this.canvas.getBoundingClientRect();
        const mx = evt.clientX - rect.left;
        const my = evt.clientY - rect.top;

        for (let i = this.hitRegions.length - 1; i >= 0; i--) {
            const region = this.hitRegions[i];
            if (region.type === 'arc') {
                const dx = mx - region.cx;
                const dy = my - region.cy;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist >= region.innerR && dist <= region.outerR) {
                    let angle = Math.atan2(dy, dx);
                    if (angle < -Math.PI / 2) angle += Math.PI * 2;
                    if (angle >= region.startAngle && angle <= region.endAngle) {
                        this.canvas.style.cursor = 'pointer';
                        showTooltip(evt.clientX, evt.clientY, region.html);
                        return;
                    }
                }
            } else if (region.type === 'rect') {
                if (mx >= region.x && mx <= region.x + region.w && my >= region.y && my <= region.y + region.h) {
                    this.canvas.style.cursor = 'pointer';
                    showTooltip(evt.clientX, evt.clientY, region.html);
                    return;
                }
            } else if (region.type === 'circle') {
                const dx = mx - region.x;
                const dy = my - region.y;
                if (dx * dx + dy * dy <= region.r * region.r) {
                    this.canvas.style.cursor = 'pointer';
                    showTooltip(evt.clientX, evt.clientY, region.html);
                    return;
                }
            }
        }

        this.canvas.style.cursor = 'default';
        hideTooltip();
    }

    draw() {
        const parent = this.canvas.parentElement;
        const width = Math.max(220, parent ? parent.clientWidth : (this.canvas.clientWidth || 360));
        const height = Math.max(180, parent ? parent.clientHeight : (this.canvas.clientHeight || 240));
        const dpr = Math.min(window.devicePixelRatio || 1, 2);

        this.canvas.width = Math.round(width * dpr);
        this.canvas.height = Math.round(height * dpr);
        this.canvas.style.width = `${width}px`;
        this.canvas.style.height = `${height}px`;

        const ctx = this.canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);
        ctx.font = "12px 'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

        this.hitRegions = [];
        const type = this.config.type || 'bar';
        const labels = this.config.data?.labels || [];
        const datasets = this.config.data?.datasets || [];

        if (!labels.length || !datasets.length) {
            this.drawEmpty(ctx, width, height);
            return;
        }

        if (type === 'pie' || type === 'doughnut') {
            this.drawPieOrDoughnut(ctx, width, height, labels, datasets[0], type === 'doughnut');
        } else if (type === 'line') {
            this.drawLineChart(ctx, width, height, labels, datasets);
        } else if (this.config.options?.indexAxis === 'y') {
            this.drawHorizontalBar(ctx, width, height, labels, datasets);
        } else {
            this.drawVerticalBar(ctx, width, height, labels, datasets);
        }
    }

    drawEmpty(ctx, w, h) {
        ctx.fillStyle = '#94a3b8';
        ctx.font = "500 13px 'Instrument Sans', ui-sans-serif, sans-serif";
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('No data points for current selection', w / 2, h / 2);
    }

    drawPieOrDoughnut(ctx, w, h, labels, dataset, isDoughnut) {
        const values = (dataset?.data || []).map((v) => Math.max(0, Number(v) || 0));
        const total = values.reduce((a, b) => a + b, 0);
        const colors = Array.isArray(dataset?.backgroundColor)
            ? dataset.backgroundColor
            : labels.map((_, i) => PALETTE[i % PALETTE.length]);

        const legendRight = w >= 340;
        const chartAreaW = legendRight ? w * 0.56 : w;
        const chartAreaH = legendRight ? h : h * 0.68;

        const cx = chartAreaW / 2;
        const cy = chartAreaH / 2;
        const outerR = Math.max(36, Math.min(chartAreaW, chartAreaH) / 2 - 14);
        const innerR = isDoughnut ? outerR * 0.58 : 0;

        if (total <= 0) {
            ctx.beginPath();
            ctx.arc(cx, cy, outerR, 0, Math.PI * 2);
            ctx.fillStyle = '#e2e8f0';
            ctx.fill();
            if (innerR > 0) {
                ctx.beginPath();
                ctx.arc(cx, cy, innerR, 0, Math.PI * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
            }
        } else {
            let angle = -Math.PI / 2;
            values.forEach((val, idx) => {
                if (val <= 0) return;
                const sliceAngle = (val / total) * Math.PI * 2;
                const endAngle = angle + sliceAngle;
                const color = colors[idx % colors.length];

                ctx.beginPath();
                ctx.moveTo(cx + Math.cos(angle) * innerR, cy + Math.sin(angle) * innerR);
                ctx.arc(cx, cy, outerR, angle, endAngle);
                if (innerR > 0) {
                    ctx.arc(cx, cy, innerR, endAngle, angle, true);
                } else {
                    ctx.lineTo(cx, cy);
                }
                ctx.closePath();
                ctx.fillStyle = color;
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = '#ffffff';
                ctx.stroke();

                const pct = ((val / total) * 100).toFixed(1);
                this.hitRegions.push({
                    type: 'arc',
                    cx,
                    cy,
                    innerR,
                    outerR,
                    startAngle: angle,
                    endAngle,
                    html: `<div style="font-weight:700;margin-bottom:2px">${escapeHtml(labels[idx])}</div><div>Count: <b>${val}</b> (${pct}%)</div>`,
                });

                angle = endAngle;
            });

            if (isDoughnut) {
                ctx.fillStyle = '#0f172a';
                ctx.font = "700 20px 'Instrument Sans', ui-sans-serif, sans-serif";
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(String(total), cx, cy - 6);
                ctx.fillStyle = '#64748b';
                ctx.font = "500 11px 'Instrument Sans', ui-sans-serif, sans-serif";
                ctx.fillText('Total', cx, cy + 12);
            }
        }

        // Draw Legend
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        ctx.font = "500 11.5px 'Instrument Sans', ui-sans-serif, sans-serif";

        const maxItems = Math.min(labels.length, 8);
        if (legendRight) {
            const startX = chartAreaW + 8;
            const rowH = Math.min(24, (h - 20) / Math.max(1, maxItems));
            const startY = (h - rowH * maxItems) / 2 + rowH / 2;

            for (let i = 0; i < maxItems; i++) {
                const y = startY + i * rowH;
                ctx.fillStyle = colors[i % colors.length];
                ctx.beginPath();
                ctx.arc(startX + 5, y, 4.5, 0, Math.PI * 2);
                ctx.fill();

                ctx.fillStyle = '#334155';
                const lbl = String(labels[i] ?? '');
                const shortLbl = lbl.length > 18 ? lbl.slice(0, 17) + '…' : lbl;
                ctx.fillText(`${shortLbl} (${values[i] ?? 0})`, startX + 15, y);
            }
        } else {
            const startY = chartAreaH + 10;
            let curX = 12;
            let curY = startY;
            for (let i = 0; i < maxItems; i++) {
                const txt = `${labels[i]} (${values[i] ?? 0})`;
                const itemW = ctx.measureText(txt).width + 22;
                if (curX + itemW > w - 10 && curX > 12) {
                    curX = 12;
                    curY += 18;
                }
                ctx.fillStyle = colors[i % colors.length];
                ctx.beginPath();
                ctx.arc(curX + 4, curY, 4, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#334155';
                ctx.fillText(txt, curX + 12, curY);
                curX += itemW;
            }
        }
    }

    drawLegendTop(ctx, w, datasets) {
        const showLegend = this.config.options?.plugins?.legend?.display !== false && datasets.length > 0;
        if (!showLegend) return 10;

        ctx.font = "500 11.5px 'Instrument Sans', ui-sans-serif, sans-serif";
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';

        let x = 12;
        let y = 12;
        datasets.forEach((ds, i) => {
            const color = Array.isArray(ds.backgroundColor)
                ? ds.backgroundColor[0]
                : (ds.borderColor || ds.backgroundColor || PALETTE[i % PALETTE.length]);
            const lbl = String(ds.label || `Series ${i + 1}`);
            const itemW = ctx.measureText(lbl).width + 24;
            if (x + itemW > w - 12 && x > 12) {
                x = 12;
                y += 18;
            }
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.arc(x + 5, y, 4.5, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = '#475569';
            ctx.fillText(lbl, x + 14, y);
            x += itemW;
        });

        return y + 16;
    }

    drawVerticalBar(ctx, w, h, labels, datasets) {
        const stacked = Boolean(this.config.options?.scales?.x?.stacked || this.config.options?.scales?.y?.stacked);
        const topPad = this.drawLegendTop(ctx, w, datasets);
        const bottomPad = labels.length > 6 ? 44 : 30;
        const leftPad = 38;
        const rightPad = 14;

        const plotW = Math.max(60, w - leftPad - rightPad);
        const plotH = Math.max(60, h - topPad - bottomPad);

        let maxVal = 0;
        labels.forEach((_, idx) => {
            if (stacked) {
                const sum = datasets.reduce((acc, ds) => acc + Math.max(0, Number(ds.data?.[idx]) || 0), 0);
                if (sum > maxVal) maxVal = sum;
            } else {
                datasets.forEach((ds) => {
                    const v = Math.max(0, Number(ds.data?.[idx]) || 0);
                    if (v > maxVal) maxVal = v;
                });
            }
        });

        const step = niceStep(maxVal || 4, 4);
        const yMax = Math.max(step, Math.ceil((maxVal || 1) / step) * step);

        // Y-axis gridlines & labels
        ctx.font = "11px 'Instrument Sans', ui-sans-serif, sans-serif";
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (let v = 0; v <= yMax; v += step) {
            const y = topPad + plotH - (v / yMax) * plotH;
            ctx.strokeStyle = v === 0 ? '#cbd5e1' : '#f1f5f9';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(leftPad, y);
            ctx.lineTo(leftPad + plotW, y);
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(String(v), leftPad - 7, y);
        }

        const catW = plotW / labels.length;
        labels.forEach((rawLbl, idx) => {
            const groupX = leftPad + idx * catW;
            const innerPad = Math.max(4, catW * 0.18);
            const availW = Math.max(8, catW - innerPad * 2);

            const tooltipLines = [`<div style="font-weight:700;margin-bottom:3px">${escapeHtml(rawLbl)}</div>`];
            datasets.forEach((ds, dsIdx) => {
                const val = Math.max(0, Number(ds.data?.[idx]) || 0);
                const c = Array.isArray(ds.backgroundColor)
                    ? ds.backgroundColor[idx % ds.backgroundColor.length]
                    : (ds.backgroundColor || PALETTE[dsIdx % PALETTE.length]);
                tooltipLines.push(`<div><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${c};margin-right:6px"></span>${escapeHtml(ds.label || 'Count')}: <b>${val}</b></div>`);
            });
            const combinedHtml = tooltipLines.join('');

            if (stacked) {
                const barW = Math.min(44, availW);
                const barX = groupX + (catW - barW) / 2;
                let curBottom = topPad + plotH;

                datasets.forEach((ds, dsIdx) => {
                    const val = Math.max(0, Number(ds.data?.[idx]) || 0);
                    if (val <= 0) return;
                    const barH = (val / yMax) * plotH;
                    const c = Array.isArray(ds.backgroundColor)
                        ? ds.backgroundColor[idx % ds.backgroundColor.length]
                        : (ds.backgroundColor || PALETTE[dsIdx % PALETTE.length]);

                    ctx.fillStyle = c;
                    ctx.fillRect(barX, curBottom - barH, barW, barH);
                    curBottom -= barH;
                });
            } else {
                const seriesCount = Math.max(1, datasets.length);
                const gap = seriesCount > 1 ? 2 : 0;
                const barW = Math.min(38, Math.max(4, (availW - gap * (seriesCount - 1)) / seriesCount));
                const totalBarsW = barW * seriesCount + gap * (seriesCount - 1);
                let startX = groupX + (catW - totalBarsW) / 2;

                datasets.forEach((ds, dsIdx) => {
                    const val = Math.max(0, Number(ds.data?.[idx]) || 0);
                    const barH = (val / yMax) * plotH;
                    const c = Array.isArray(ds.backgroundColor)
                        ? ds.backgroundColor[idx % ds.backgroundColor.length]
                        : (ds.backgroundColor || PALETTE[dsIdx % PALETTE.length]);

                    if (barH > 0) {
                        ctx.fillStyle = c;
                        roundedRect(ctx, startX, topPad + plotH - barH, barW, barH, 4);
                        ctx.fill();
                    }
                    startX += barW + gap;
                });
            }

            this.hitRegions.push({
                type: 'rect',
                x: groupX,
                y: topPad,
                w: catW,
                h: plotH,
                html: combinedHtml,
            });

            // X-axis label
            ctx.save();
            ctx.fillStyle = '#475569';
            ctx.font = "11px 'Instrument Sans', ui-sans-serif, sans-serif";
            const shortLbl = String(rawLbl).length > 13 ? String(rawLbl).slice(0, 12) + '…' : String(rawLbl);
            const cx = groupX + catW / 2;
            if (labels.length > 7) {
                ctx.translate(cx, topPad + plotH + 8);
                ctx.rotate(-0.35);
                ctx.textAlign = 'right';
                ctx.textBaseline = 'top';
                ctx.fillText(shortLbl, 0, 0);
            } else {
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText(shortLbl, cx, topPad + plotH + 8);
            }
            ctx.restore();
        });
    }

    drawHorizontalBar(ctx, w, h, labels, datasets) {
        const topPad = this.drawLegendTop(ctx, w, datasets);
        const bottomPad = 24;
        const leftPad = Math.min(150, Math.max(95, w * 0.34));
        const rightPad = 36;

        const plotW = Math.max(60, w - leftPad - rightPad);
        const plotH = Math.max(60, h - topPad - bottomPad);

        let maxVal = Number(this.config.options?.scales?.x?.max) || 0;
        if (!maxVal) {
            datasets.forEach((ds) => {
                (ds.data || []).forEach((v) => {
                    const num = Math.max(0, Number(v) || 0);
                    if (num > maxVal) maxVal = num;
                });
            });
        }
        const step = niceStep(maxVal || 10, 4);
        const xMax = Number(this.config.options?.scales?.x?.max) || Math.max(step, Math.ceil((maxVal || 1) / step) * step);

        // X gridlines
        ctx.font = "11px 'Instrument Sans', ui-sans-serif, sans-serif";
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        for (let v = 0; v <= xMax; v += step) {
            const x = leftPad + (v / xMax) * plotW;
            ctx.strokeStyle = v === 0 ? '#cbd5e1' : '#f1f5f9';
            ctx.beginPath();
            ctx.moveTo(x, topPad);
            ctx.lineTo(x, topPad + plotH);
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(String(v), x, topPad + plotH + 6);
        }

        const rowH = plotH / labels.length;
        labels.forEach((rawLbl, idx) => {
            const rowY = topPad + idx * rowH;
            const seriesCount = Math.max(1, datasets.length);
            const availH = Math.max(6, rowH * 0.68);
            const barH = Math.min(20, availH / seriesCount);
            let curY = rowY + (rowH - barH * seriesCount) / 2;

            const tooltipLines = [`<div style="font-weight:700;margin-bottom:3px">${escapeHtml(rawLbl)}</div>`];
            datasets.forEach((ds, dsIdx) => {
                const val = Math.max(0, Number(ds.data?.[idx]) || 0);
                const barW = (val / xMax) * plotW;
                const c = Array.isArray(ds.backgroundColor)
                    ? ds.backgroundColor[idx % ds.backgroundColor.length]
                    : (ds.backgroundColor || PALETTE[dsIdx % PALETTE.length]);

                if (barW > 0) {
                    ctx.fillStyle = c;
                    roundedRect(ctx, leftPad, curY, barW, barH, 4);
                    ctx.fill();
                }

                if (seriesCount === 1) {
                    ctx.fillStyle = '#334155';
                    ctx.font = "600 11px 'Instrument Sans', ui-sans-serif, sans-serif";
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    const suffix = String(ds.label || '').includes('%') ? '%' : '';
                    ctx.fillText(`${val}${suffix}`, leftPad + barW + 5, curY + barH / 2);
                }

                tooltipLines.push(`<div>${escapeHtml(ds.label || 'Value')}: <b>${val}</b></div>`);
                curY += barH;
            });

            this.hitRegions.push({
                type: 'rect',
                x: 0,
                y: rowY,
                w,
                h: rowH,
                html: tooltipLines.join(''),
            });

            ctx.fillStyle = '#334155';
            ctx.font = "11px 'Instrument Sans', ui-sans-serif, sans-serif";
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            const shortLbl = String(rawLbl).length > 22 ? String(rawLbl).slice(0, 21) + '…' : String(rawLbl);
            ctx.fillText(shortLbl, leftPad - 8, rowY + rowH / 2);
        });
    }

    drawLineChart(ctx, w, h, labels, datasets) {
        const topPad = this.drawLegendTop(ctx, w, datasets);
        const bottomPad = 30;
        const leftPad = 38;
        const rightPad = 18;

        const plotW = Math.max(60, w - leftPad - rightPad);
        const plotH = Math.max(60, h - topPad - bottomPad);

        let maxVal = 0;
        datasets.forEach((ds) => {
            (ds.data || []).forEach((v) => {
                const n = Math.max(0, Number(v) || 0);
                if (n > maxVal) maxVal = n;
            });
        });

        const step = niceStep(maxVal || 4, 4);
        const yMax = Math.max(step, Math.ceil((maxVal || 1) / step) * step);

        ctx.font = "11px 'Instrument Sans', ui-sans-serif, sans-serif";
        ctx.textAlign = 'right';
        ctx.textBaseline = 'middle';
        for (let v = 0; v <= yMax; v += step) {
            const y = topPad + plotH - (v / yMax) * plotH;
            ctx.strokeStyle = v === 0 ? '#cbd5e1' : '#f1f5f9';
            ctx.beginPath();
            ctx.moveTo(leftPad, y);
            ctx.lineTo(leftPad + plotW, y);
            ctx.stroke();

            ctx.fillStyle = '#64748b';
            ctx.fillText(String(v), leftPad - 7, y);
        }

        const xForIndex = (i) => (labels.length === 1 ? leftPad + plotW / 2 : leftPad + (i / (labels.length - 1)) * plotW);

        datasets.forEach((ds, dsIdx) => {
            const stroke = ds.borderColor || ds.backgroundColor || PALETTE[dsIdx % PALETTE.length];
            const pts = labels.map((_, i) => {
                const val = Math.max(0, Number(ds.data?.[i]) || 0);
                return { x: xForIndex(i), y: topPad + plotH - (val / yMax) * plotH, val };
            });

            if (ds.fill && pts.length > 1) {
                ctx.beginPath();
                ctx.moveTo(pts[0].x, topPad + plotH);
                pts.forEach((p) => ctx.lineTo(p.x, p.y));
                ctx.lineTo(pts[pts.length - 1].x, topPad + plotH);
                ctx.closePath();
                ctx.fillStyle = typeof ds.backgroundColor === 'string' && ds.backgroundColor.startsWith('rgba')
                    ? ds.backgroundColor
                    : 'rgba(79,70,229,0.10)';
                ctx.fill();
            }

            ctx.beginPath();
            pts.forEach((p, idx) => {
                if (idx === 0) ctx.moveTo(p.x, p.y);
                else ctx.lineTo(p.x, p.y);
            });
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2.5;
            ctx.stroke();

            pts.forEach((p, idx) => {
                ctx.beginPath();
                ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.lineWidth = 2;
                ctx.strokeStyle = stroke;
                ctx.stroke();

                this.hitRegions.push({
                    type: 'circle',
                    x: p.x,
                    y: p.y,
                    r: 10,
                    html: `<div style="font-weight:700">${escapeHtml(labels[idx])}</div><div>${escapeHtml(ds.label || 'Series')}: <b>${p.val}</b></div>`,
                });
            });
        });

        ctx.fillStyle = '#475569';
        ctx.font = "11px 'Instrument Sans', ui-sans-serif, sans-serif";
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        labels.forEach((lbl, idx) => {
            ctx.fillText(String(lbl), xForIndex(idx), topPad + plotH + 8);
        });
    }
}
