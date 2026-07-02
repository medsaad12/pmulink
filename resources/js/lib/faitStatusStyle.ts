const HEX6 = /^#[0-9A-Fa-f]{6}$/;

export function isHexColor(value: string | undefined | null): value is string {
    return typeof value === 'string' && HEX6.test(value);
}

export type RgbTuple = [number, number, number];

export function hexToRgb(hex: string): RgbTuple | null {
    const match = /^#([0-9A-Fa-f]{6})$/.exec(hex);

    if (!match) {
        return null;
    }

    const n = Number.parseInt(match[1], 16);

    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

/** Équivalent sRGB de `color-mix(in srgb, hex N%, blend)`. */
function colorMixSrgb(hex: string, hexPercent: number, blend: RgbTuple): RgbTuple {
    const source = hexToRgb(hex);

    if (!source) {
        return blend;
    }

    const w = hexPercent / 100;
    const o = 1 - w;

    return [
        Math.round(source[0] * w + blend[0] * o),
        Math.round(source[1] * w + blend[1] * o),
        Math.round(source[2] * w + blend[2] * o),
    ];
}

/** Pastille statut pour export PDF (même logique que `badgePillFromStatus`). */
export function badgePillRgbForPdf(hex: string | undefined | null): {
    fillColor: RgbTuple;
    textColor: RgbTuple;
} {
    if (!isHexColor(hex)) {
        return { fillColor: [255, 255, 255], textColor: [64, 64, 64] };
    }

    return {
        fillColor: colorMixSrgb(hex, 26, [255, 255, 255]),
        textColor: colorMixSrgb(hex, 78, [0, 0, 0]),
    };
}

/** Pastille / badge (fond pastel, texte plus soutenu). Sans couleur valide : fond blanc neutre. */
export function badgePillFromStatus(hex: string | undefined | null): {
    backgroundColor: string;
    color: string;
    boxShadow?: string;
} {
    if (!isHexColor(hex)) {
        return {
            backgroundColor: '#ffffff',
            color: '#404040',
            boxShadow: 'inset 0 0 0 1px #e5e7eb',
        };
    }
    return {
        backgroundColor: `color-mix(in srgb, ${hex} 26%, white)`,
        color: `color-mix(in srgb, ${hex} 78%, black)`,
    };
}

/** Fond du post-it (teinte à partir du statut). `hex` doit être valide. */
export function stickyTintFromStatus(hex: string, mixPercent = 14): string {
    return `color-mix(in srgb, ${hex} ${mixPercent}%, white)`;
}

export function stickyTintBorder(hex: string): string {
    return `color-mix(in srgb, ${hex} 32%, white)`;
}

/** Fond post-it sur le canvas (pastel type feuille autocollante). */
export function stickyCanvasNoteBg(hex: string, mixPercent = 22): string {
    return `color-mix(in srgb, ${hex} ${mixPercent}%, white)`;
}

/** Texte foncé lisible sur le pastel (titre, statut). */
export function stickyCanvasInk(hex: string): string {
    return `color-mix(in srgb, ${hex} 68%, #0f172a)`;
}
