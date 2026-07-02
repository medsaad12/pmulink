import { jsPDF } from 'jspdf';
import autoTable from 'jspdf-autotable';
import { badgePillRgbForPdf, hexToRgb, isHexColor } from '@/lib/faitStatusStyle';

export type WhiteboardPdfExportRow = {
    title: string;
    status: string;
    /** Couleur hex du statut (comme en base / tableau). */
    statusColor?: string | null;
    responsable: string;
    createdAt: string;
    deadline: string;
};

const PDF_STATUS_COLUMN_INDEX = 1;

export type WhiteboardPdfExportGroup = {
    title: string;
    rows: WhiteboardPdfExportRow[];
};

export type WhiteboardPdfMeteoSummary = {
    total: number;
    meteo1: number;
    meteo2: number;
    meteo3: number;
};

export type WhiteboardPdfKpiItem = {
    label: string;
    count: number;
    statusColor?: string | null;
};

export type WhiteboardTablePdfExportOptions = {
    groups: WhiteboardPdfExportGroup[];
    /** Ex. `2 juin 2026 - 8 juin 2026` */
    weekRangeLabel: string;
    meteoSummary: WhiteboardPdfMeteoSummary;
    kpiItems: WhiteboardPdfKpiItem[];
    /** Nom de fichier sans extension */
    fileBaseName?: string;
};

const PDF_COLUMNS = [
    'Sujet',
    'Statut',
    'Responsable action',
    'Date création',
    'Deadline',
] as const;

const MARGIN_X = 14;
/** Limite verticale utile (A4 paysage, h ≈ 210 mm). */
const PAGE_BOTTOM = 200;

const RAPPORT_LOGO_SRC = '/logos/logo_rapport.png';
const RAPPORT_LOGO_HEIGHT_MM = 10;
const RAPPORT_LOGO_TITLE_GAP_MM = 8;

const DONUT_SLICE_HEX = ['#16a34a', '#eab308', '#dc2626'] as const;

/** Espacements horizontaux du bloc météo (mm), alignés sur la carte UI. */
const METEO_CARD_PAD_X = 3.5;
const METEO_DONUT_LEGEND_GAP = 4;
const METEO_DONUT_SIZE_MM = 30;
const METEO_ICON_MM = 5.5;
const METEO_LEGEND_ROW_GAP = 1.6;

function todayFileStamp(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${y}-${m}-${day}`;
}

function ensurePageSpace(doc: jsPDF, y: number, needed: number): number {
    if (y + needed > PAGE_BOTTOM) {
        doc.addPage();
        return 18;
    }

    return y;
}

function meteoPct(n: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.round((100 * n) / total);
}

/** Donut météo (canvas → PNG) pour l’en-tête PDF. */
function renderMeteoDonutPng(
    meteo1: number,
    meteo2: number,
    meteo3: number,
    total: number,
    sizePx = 120,
): string | null {
    if (typeof document === 'undefined' || total <= 0) {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = sizePx;
    canvas.height = sizePx;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return null;
    }

    const cx = sizePx / 2;
    const cy = sizePx / 2;
    const rOut = sizePx * 0.47;
    const rIn = sizePx * 0.3;
    const counts = [meteo1, meteo2, meteo3];

    ctx.clearRect(0, 0, sizePx, sizePx);

    ctx.beginPath();
    ctx.arc(cx, cy, (rOut + rIn) / 2, 0, Math.PI * 2);
    ctx.lineWidth = rOut - rIn;
    ctx.strokeStyle = '#e2e8f0';
    ctx.stroke();

    const fullIdx = counts.findIndex((c) => c === total);

    if (fullIdx >= 0) {
        ctx.beginPath();
        ctx.arc(cx, cy, rOut, 0, Math.PI * 2);
        ctx.arc(cx, cy, rIn, 0, Math.PI * 2, true);
        ctx.fillStyle = DONUT_SLICE_HEX[fullIdx];
        ctx.fill('evenodd');

        return canvas.toDataURL('image/png');
    }

    let angle = -Math.PI / 2;

    for (let i = 0; i < counts.length; i++) {
        const count = counts[i];

        if (count <= 0) {
            continue;
        }

        const span = (count / total) * Math.PI * 2;

        ctx.beginPath();
        ctx.arc(cx, cy, rOut, angle, angle + span);
        ctx.arc(cx, cy, rIn, angle + span, angle, true);
        ctx.closePath();
        ctx.fillStyle = DONUT_SLICE_HEX[i];
        ctx.fill();
        angle += span;
    }

    return canvas.toDataURL('image/png');
}

function loadMeteoIconDataUrl(meteoNum: 1 | 2 | 3): Promise<string | null> {
    if (typeof document === 'undefined') {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        const img = new Image();

        img.onload = () => {
            const canvas = document.createElement('canvas');
            const size = Math.max(img.naturalWidth, img.naturalHeight, 1);

            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');

            if (!ctx) {
                resolve(null);

                return;
            }

            ctx.clearRect(0, 0, size, size);
            ctx.drawImage(img, 0, 0, size, size);
            resolve(canvas.toDataURL('image/png'));
        };

        img.onerror = () => resolve(null);
        img.src = `/meteo/${meteoNum}.png`;
    });
}

function loadRapportLogo(): Promise<{ dataUrl: string; aspectRatio: number } | null> {
    if (typeof document === 'undefined') {
        return Promise.resolve(null);
    }

    return new Promise((resolve) => {
        const img = new Image();

        img.onload = () => {
            const w = img.naturalWidth;
            const h = img.naturalHeight;

            if (!w || !h) {
                resolve(null);

                return;
            }

            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');

            if (!ctx) {
                resolve(null);

                return;
            }

            ctx.drawImage(img, 0, 0);
            resolve({ dataUrl: canvas.toDataURL('image/png'), aspectRatio: w / h });
        };

        img.onerror = () => resolve(null);
        img.src = RAPPORT_LOGO_SRC;
    });
}

async function loadMeteoIconDataUrls(): Promise<[string | null, string | null, string | null]> {
    const icons = await Promise.all([
        loadMeteoIconDataUrl(1),
        loadMeteoIconDataUrl(2),
        loadMeteoIconDataUrl(3),
    ]);

    return icons;
}

function kpiColorsForPdf(item: WhiteboardPdfKpiItem): {
    fillColor: [number, number, number];
    textColor: [number, number, number];
} {
    if (isHexColor(item.statusColor)) {
        return badgePillRgbForPdf(item.statusColor);
    }

    return badgePillRgbForPdf('#16a34a');
}

/**
 * Rangée météo + KPI (comme `WhiteboardMeteoKpiRow` dans l’app).
 * @returns Position Y sous le bloc.
 */
function drawMeteoKpiHeader(
    doc: jsPDF,
    startY: number,
    tableWidth: number,
    meteo: WhiteboardPdfMeteoSummary,
    kpiItems: WhiteboardPdfKpiItem[],
    meteoIcons: [string | null, string | null, string | null],
): number {
    const rowH = 36;
    const gap = 3;
    const donutColW = tableWidth * 0.24;
    const kpiAreaW = tableWidth - donutColW - gap;
    const kpiCount = Math.max(kpiItems.length, 1);
    const kpiGap = 2;
    const kpiW = (kpiAreaW - kpiGap * (kpiCount - 1)) / kpiCount;
    const x0 = MARGIN_X;
    let y = startY;

    doc.setDrawColor(226, 232, 240);
    doc.setLineWidth(0.2);
    doc.setFillColor(255, 255, 255);
    doc.roundedRect(x0, y, donutColW, rowH, 1.5, 1.5, 'FD');

    const counts = [meteo.meteo1, meteo.meteo2, meteo.meteo3];
    const donutImg = renderMeteoDonutPng(
        meteo.meteo1,
        meteo.meteo2,
        meteo.meteo3,
        meteo.total,
        140,
    );
    const donutX = x0 + METEO_CARD_PAD_X;
    const donutY = y + (rowH - METEO_DONUT_SIZE_MM) / 2;

    if (donutImg) {
        doc.addImage(donutImg, 'PNG', donutX, donutY, METEO_DONUT_SIZE_MM, METEO_DONUT_SIZE_MM);
    } else if (meteo.total <= 0) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7);
        doc.setTextColor(148, 163, 184);
        doc.text('—', x0 + donutColW / 2, y + rowH / 2 + 2, { align: 'center' });
    }

    const legendX =
        donutX + METEO_DONUT_SIZE_MM + METEO_DONUT_LEGEND_GAP;
    const legendBlockH = 3 * METEO_ICON_MM + 2 * METEO_LEGEND_ROW_GAP;
    let legendRowY = y + (rowH - legendBlockH) / 2;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8);

    for (let i = 0; i < 3; i++) {
        const rgb = hexToRgb(DONUT_SLICE_HEX[i]) ?? [200, 200, 200];
        const icon = meteoIcons[i];
        let textX = legendX;

        if (icon) {
            doc.addImage(icon, 'PNG', legendX, legendRowY, METEO_ICON_MM, METEO_ICON_MM);
            textX = legendX + METEO_ICON_MM + 1.8;
        }

        doc.setTextColor(51, 65, 85);
        doc.text(`${meteoPct(counts[i], meteo.total)}%`, textX, legendRowY + METEO_ICON_MM * 0.72);

        const swatchX = textX + 9;
        const swatchY = legendRowY + METEO_ICON_MM * 0.55;

        doc.setFillColor(rgb[0], rgb[1], rgb[2]);
        doc.roundedRect(swatchX, swatchY, 5, 1.4, 0.35, 0.35, 'F');

        legendRowY += METEO_ICON_MM + METEO_LEGEND_ROW_GAP;
    }

    kpiItems.forEach((item, index) => {
        const kx = x0 + donutColW + gap + index * (kpiW + kpiGap);
        const colors = kpiColorsForPdf(item);

        doc.setDrawColor(226, 232, 240);
        doc.setFillColor(colors.fillColor[0], colors.fillColor[1], colors.fillColor[2]);
        doc.roundedRect(kx, y, kpiW, rowH, 1.5, 1.5, 'FD');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(15);
        doc.setTextColor(colors.textColor[0], colors.textColor[1], colors.textColor[2]);
        doc.text(String(item.count), kx + kpiW / 2, y + 11, { align: 'center' });

        doc.setFontSize(6.5);
        const labelLines = doc.splitTextToSize(item.label, kpiW - 4) as string[];

        doc.text(labelLines, kx + kpiW / 2, y + 18, { align: 'center', maxWidth: kpiW - 4 });
    });

    doc.setTextColor(0, 0, 0);

    return y + rowH + 6;
}

/**
 * Génère et télécharge un PDF groupé par département (comme la vue tableau).
 */
export async function downloadWhiteboardTablePdf(options: WhiteboardTablePdfExportOptions): Promise<void> {
    const { groups, weekRangeLabel, meteoSummary, kpiItems } = options;
    const nonEmpty = groups.filter((g) => g.rows.length > 0);

    if (nonEmpty.length === 0) {
        return;
    }

    const [meteoIcons, rapportLogo] = await Promise.all([loadMeteoIconDataUrls(), loadRapportLogo()]);
    const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const tableWidth = pageWidth - MARGIN_X * 2;
    let y = 10;

    if (rapportLogo) {
        const logoW = RAPPORT_LOGO_HEIGHT_MM * rapportLogo.aspectRatio;
        doc.addImage(rapportLogo.dataUrl, 'PNG', MARGIN_X, y, logoW, RAPPORT_LOGO_HEIGHT_MM);
        y += RAPPORT_LOGO_HEIGHT_MM + RAPPORT_LOGO_TITLE_GAP_MM;
    } else {
        y = 16;
    }

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(16);
    doc.text('Rapport - Sujets de la semaine', MARGIN_X, y);

    y += 8;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.setTextColor(80, 80, 80);
    doc.text(weekRangeLabel, MARGIN_X, y);
    doc.setTextColor(0, 0, 0);
    y += 6;

    y = drawMeteoKpiHeader(doc, y, tableWidth, meteoSummary, kpiItems, meteoIcons);

    for (const group of nonEmpty) {
        y = ensurePageSpace(doc, y, 14);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(12);
        doc.setTextColor(26, 77, 58);
        doc.text(`${group.title.toUpperCase()} (${group.rows.length})`, MARGIN_X, y);
        doc.setTextColor(0, 0, 0);
        y += 6;

        autoTable(doc, {
            startY: y,
            tableWidth,
            margin: { left: MARGIN_X, right: MARGIN_X },
            head: [PDF_COLUMNS.slice()],
            body: group.rows.map((row) => [
                row.title,
                row.status,
                row.responsable,
                row.createdAt,
                row.deadline,
            ]),
            styles: {
                font: 'helvetica',
                fontSize: 9,
                cellPadding: 2.5,
                overflow: 'linebreak',
                valign: 'top',
            },
            headStyles: {
                fillColor: [26, 77, 58],
                textColor: 255,
                fontStyle: 'bold',
                fontSize: 9,
            },
            alternateRowStyles: {
                fillColor: [248, 250, 252],
            },
            columnStyles: {
                0: { cellWidth: tableWidth * 0.46 },
                1: { cellWidth: tableWidth * 0.14, halign: 'center' },
                2: { cellWidth: tableWidth * 0.18 },
                3: { cellWidth: tableWidth * 0.11 },
                4: { cellWidth: tableWidth * 0.11 },
            },
            didParseCell: (data) => {
                if (data.section !== 'body' || data.column.index !== PDF_STATUS_COLUMN_INDEX) {
                    return;
                }

                const row = group.rows[data.row.index];

                if (!row) {
                    return;
                }

                const pill = badgePillRgbForPdf(row.statusColor);

                data.cell.styles.fillColor = pill.fillColor;
                data.cell.styles.textColor = pill.textColor;
                data.cell.styles.fontStyle = 'bold';
                data.cell.styles.halign = 'center';
            },
            didDrawPage: () => {
                const pageCount = doc.getNumberOfPages();
                const page = doc.getCurrentPageInfo().pageNumber;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8);
                doc.setTextColor(120, 120, 120);
                doc.text(`Page ${page} / ${pageCount}`, doc.internal.pageSize.getWidth() - MARGIN_X, 204, {
                    align: 'right',
                });
                doc.setTextColor(0, 0, 0);
            },
        });

        const finalY = (doc as jsPDF & { lastAutoTable?: { finalY: number } }).lastAutoTable?.finalY;

        y = (finalY ?? y) + 10;
    }

    const base = options.fileBaseName ?? `rapport-sujets-${todayFileStamp()}`;
    doc.save(`${base}.pdf`);
}
