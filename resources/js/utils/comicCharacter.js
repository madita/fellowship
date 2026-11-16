/**
 * What a comic chat character is made of.
 *
 * A character is a set of part choices rather than a drawing of its own, so
 * the eight that ship with the site and anything an admin builds in the
 * character creator are described the same way and drawn by the same code.
 *
 * Everything is drawn in a 100 × 140 box: the head sits around (50, 46) and
 * the feet reach 138.
 */

export const INK = '#2b2b2b';

/**
 * How the character's own colour is mixed. The hue comes from the character;
 * the tone decides whether it reads as skin, as something dark, or as metal.
 */
export const TONES = {
    normal: { body: [52, 72], dark: [52, 58], light: [60, 86] },
    dark: { body: [14, 26], dark: [14, 18], light: [14, 38] },
    metal: { body: [12, 74], dark: [10, 56], light: [14, 88] },
    bright: { body: [78, 66], dark: [78, 52], light: [80, 82] },
};

/**
 * The colours a part is drawn with, for one character.
 */
export function paletteFor({ hue = 200, tone = 'normal' } = {}) {
    const mix = TONES[tone] || TONES.normal;
    const hsl = ([saturation, lightness]) => `hsl(${hue}, ${saturation}%, ${lightness}%)`;

    return {
        ink: INK,
        skin: hsl(mix.body),
        dark: hsl(mix.dark),
        light: hsl(mix.light),
    };
}

const round = ({ skin, ink }) => `<circle cx="50" cy="46" r="24" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`;

/**
 * The parts, each with the options it offers and how each option is drawn.
 * `label` is a plain English fallback; the UI translates by key.
 */
export const PARTS = {
    body: {
        label: 'Build',
        options: {
            slim: {
                label: 'Slim',
                draw: ({ skin, dark, ink }) => `<path d="M 50 70 Q 70 74 70 96 L 68 118 L 32 118 L 30 96 Q 30 74 50 70 Z" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`
                    + `<path d="M 38 118 L 37 132" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>`
                    + `<path d="M 62 118 L 63 132" stroke="${ink}" stroke-width="6" stroke-linecap="round"/>`
                    + `<ellipse cx="35" cy="135" rx="8" ry="4" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`
                    + `<ellipse cx="65" cy="135" rx="8" ry="4" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`,
            },
            robe: {
                label: 'Robe',
                draw: ({ skin, ink }) => `<path d="M 50 70 L 68 76 L 76 132 L 24 132 L 32 76 Z" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`,
            },
            boxy: {
                label: 'Boxy',
                draw: ({ skin, dark, light, ink }) => `<rect x="30" y="74" width="40" height="44" rx="6" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`
                    + `<rect x="38" y="84" width="24" height="14" rx="3" fill="${light}" stroke="${ink}" stroke-width="1.6"/>`
                    + `<circle cx="44" cy="91" r="2.2" fill="#e57373"/><circle cx="50" cy="91" r="2.2" fill="#fff176"/><circle cx="56" cy="91" r="2.2" fill="#81c784"/>`
                    + `<rect x="36" y="118" width="12" height="16" rx="3" fill="${dark}" stroke="${ink}" stroke-width="2"/>`
                    + `<rect x="52" y="118" width="12" height="16" rx="3" fill="${dark}" stroke="${ink}" stroke-width="2"/>`,
            },
            sturdy: {
                label: 'Sturdy',
                draw: ({ skin, dark, ink }) => `<path d="M 50 68 Q 76 74 76 100 L 72 120 L 28 120 L 24 100 Q 24 74 50 68 Z" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`
                    + `<path d="M 38 120 L 36 132 M 62 120 L 64 132" stroke="${ink}" stroke-width="7" stroke-linecap="round"/>`
                    + `<ellipse cx="34" cy="135" rx="9" ry="4" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`
                    + `<ellipse cx="66" cy="135" rx="9" ry="4" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`,
            },
        },
    },

    head: {
        label: 'Head',
        options: {
            round: { label: 'Round', draw: round },
            box: {
                label: 'Boxed',
                draw: ({ skin, dark, ink }) => `<rect x="44" y="62" width="12" height="14" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`
                    + `<rect x="28" y="26" width="44" height="40" rx="8" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`,
            },
            egg: {
                label: 'Tapered',
                draw: ({ skin, ink }) => `<path d="M 50 20 Q 78 24 74 50 Q 70 70 50 72 Q 30 70 26 50 Q 22 24 50 20 Z" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`,
            },
            helmet: {
                label: 'Helmet',
                draw: ({ skin, ink }) => `<path d="M 30 30 Q 50 22 70 30 L 70 60 Q 50 72 30 60 Z" fill="${skin}" stroke="${ink}" stroke-width="2.4"/>`
                    + `<path d="M 34 56 L 66 56" stroke="${ink}" stroke-width="1.4" opacity="0.5"/>`,
            },
        },
    },

    ears: {
        label: 'Ears',
        // Drawn behind the head, so they read as sticking out from it
        behind: true,
        options: {
            none: { label: 'None', draw: () => '' },
            cat: {
                label: 'Pointed',
                draw: ({ skin, dark, ink }) => `<path d="M 30 30 L 27 10 L 44 22 Z" fill="${skin}" stroke="${ink}" stroke-width="2.2"/>`
                    + `<path d="M 70 30 L 73 10 L 56 22 Z" fill="${skin}" stroke="${ink}" stroke-width="2.2"/>`
                    + `<path d="M 31.5 27 L 30 15 L 40 22 Z" fill="${dark}"/>`
                    + `<path d="M 68.5 27 L 70 15 L 60 22 Z" fill="${dark}"/>`,
            },
            round: {
                label: 'Round',
                draw: ({ skin, dark, ink }) => `<circle cx="28" cy="26" r="9" fill="${skin}" stroke="${ink}" stroke-width="2.2"/>`
                    + `<circle cx="72" cy="26" r="9" fill="${skin}" stroke="${ink}" stroke-width="2.2"/>`
                    + `<circle cx="28" cy="26" r="4" fill="${dark}"/><circle cx="72" cy="26" r="4" fill="${dark}"/>`,
            },
        },
    },

    // Ears that hang in front of the head, or sit beside it
    sideParts: {
        label: 'Side parts',
        options: {
            none: { label: 'None', draw: () => '' },
            floppy: {
                label: 'Floppy ears',
                draw: ({ dark, ink }) => `<path d="M 28 34 Q 14 40 20 62 Q 30 62 32 46 Z" fill="${dark}" stroke="${ink}" stroke-width="2.2"/>`
                    + `<path d="M 72 34 Q 86 40 80 62 Q 70 62 68 46 Z" fill="${dark}" stroke="${ink}" stroke-width="2.2"/>`,
            },
            panels: {
                label: 'Panels',
                draw: ({ dark, ink }) => `<rect x="22" y="40" width="6" height="14" rx="2" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`
                    + `<rect x="72" y="40" width="6" height="14" rx="2" fill="${dark}" stroke="${ink}" stroke-width="1.8"/>`,
            },
            whiskers: {
                label: 'Whiskers',
                draw: ({ ink }) => `<path d="M 26 48 L 14 45 M 26 52 L 14 53 M 74 48 L 86 45 M 74 52 L 86 53" stroke="${ink}" stroke-width="1.2" stroke-linecap="round" opacity="0.6"/>`,
            },
        },
    },

    hat: {
        label: 'Headwear',
        options: {
            none: { label: 'None', draw: () => '' },
            wizard: {
                label: 'Wizard hat',
                draw: ({ ink }) => `<path d="M 50 2 Q 58 20 74 34 L 26 34 Q 42 20 50 2 Z" fill="#4527a0" stroke="${ink}" stroke-width="2.4"/>`
                    + `<ellipse cx="50" cy="34" rx="26" ry="5" fill="#5e35b1" stroke="${ink}" stroke-width="2.2"/>`
                    + `<path d="M 50 12 l 2 5 l 5 1 l -4 4 l 1 5 l -4 -3 l -4 3 l 1 -5 l -4 -4 l 5 -1 z" fill="#ffd54f"/>`,
            },
            bandana: {
                label: 'Bandana',
                draw: ({ ink }) => `<path d="M 24 36 Q 50 12 76 36 Q 50 30 24 36 Z" fill="#c62828" stroke="${ink}" stroke-width="2.2"/>`
                    + `<path d="M 24 36 Q 50 44 76 36 L 76 39 Q 50 47 24 39 Z" fill="#8e0000"/>`,
            },
            plume: {
                label: 'Plume',
                draw: ({ ink }) => `<path d="M 50 22 Q 62 6 74 22 Q 62 16 50 24 Z" fill="#e53935" stroke="${ink}" stroke-width="1.8"/>`,
            },
            antenna: {
                label: 'Antenna',
                draw: ({ ink }) => `<line x1="50" y1="26" x2="50" y2="14" stroke="${ink}" stroke-width="2.4"/>`
                    + `<circle cx="50" cy="11" r="4.5" fill="#ef5350" stroke="${ink}" stroke-width="1.8"/>`,
            },
            feelers: {
                label: 'Feelers',
                draw: ({ ink }) => `<path d="M 36 22 L 30 8 M 64 22 L 70 8" stroke="${ink}" stroke-width="2.2" stroke-linecap="round"/>`
                    + `<circle cx="30" cy="7" r="3.6" fill="#9ccc65" stroke="${ink}" stroke-width="1.6"/>`
                    + `<circle cx="70" cy="7" r="3.6" fill="#9ccc65" stroke="${ink}" stroke-width="1.6"/>`,
            },
            crown: {
                label: 'Crown',
                draw: ({ ink }) => `<path d="M 30 30 L 30 14 L 40 22 L 50 10 L 60 22 L 70 14 L 70 30 Z" fill="#ffd54f" stroke="${ink}" stroke-width="2"/>`
                    + `<circle cx="50" cy="24" r="2.6" fill="#e53935"/>`,
            },
        },
    },

    snout: {
        label: 'Snout',
        options: {
            none: { label: 'None', draw: () => '' },
            cat: {
                label: 'Cat nose',
                draw: ({ dark, ink }) => `<path d="M 47 53 L 53 53 L 50 57 Z" fill="${dark}" stroke="${ink}" stroke-width="1.2"/>`,
            },
            dog: {
                label: 'Muzzle',
                draw: ({ light, ink }) => `<ellipse cx="50" cy="56" rx="9" ry="7" fill="${light}" stroke="${ink}" stroke-width="1.6"/>`
                    + `<ellipse cx="50" cy="52" rx="3.6" ry="2.8" fill="${ink}"/>`,
            },
            beak: {
                label: 'Beak',
                draw: ({ ink }) => `<path d="M 43 52 L 57 52 L 50 63 Z" fill="#fbc02d" stroke="${ink}" stroke-width="1.6"/>`,
            },
        },
    },

    /**
     * A covered face wears no expression, so these also decide whether the
     * emotion is drawn at all.
     */
    mask: {
        label: 'Face cover',
        options: {
            none: { label: 'Open face', draw: () => '', hidesFace: false },
            ninja: {
                label: 'Mask',
                hidesFace: true,
                draw: () => `<path d="M 26 40 Q 50 34 74 40 L 74 52 Q 50 46 26 52 Z" fill="#263238"/>`,
                // Drawn last so the eyes read through the mask
                over: () => `<circle cx="41" cy="46" r="3.2" fill="#fff"/><circle cx="59" cy="46" r="3.2" fill="#fff"/>`,
            },
            visor: {
                label: 'Visor',
                hidesFace: true,
                draw: () => `<rect x="34" y="42" width="32" height="7" rx="2" fill="#37474f"/>`
                    + `<circle cx="42" cy="45.5" r="1.8" fill="#eceff1"/><circle cx="58" cy="45.5" r="1.8" fill="#eceff1"/>`,
            },
        },
    },

    accessory: {
        label: 'Accessory',
        options: {
            none: { label: 'None', draw: () => '' },
            beard: {
                label: 'Beard',
                draw: () => `<path d="M 36 62 Q 40 84 50 88 Q 60 84 64 62 Q 50 70 36 62 Z" fill="#eceff1" stroke="#b0bec5" stroke-width="1.6"/>`,
            },
            eyepatch: {
                label: 'Eye patch',
                // Worn over the face, so drawn after the expression
                over: ({ ink }) => `<path d="M 32 39 L 20 33" stroke="${ink}" stroke-width="2" stroke-linecap="round"/>`
                    + `<circle cx="39" cy="45" r="7.5" fill="${ink}"/>`,
                draw: () => '',
            },
            scarf: {
                label: 'Scarf',
                draw: () => `<path d="M 74 42 Q 88 46 92 58" fill="none" stroke="#263238" stroke-width="4" stroke-linecap="round"/>`,
            },
            glasses: {
                label: 'Glasses',
                over: ({ ink }) => `<circle cx="39" cy="46" r="8" fill="none" stroke="${ink}" stroke-width="2"/>`
                    + `<circle cx="61" cy="46" r="8" fill="none" stroke="${ink}" stroke-width="2"/>`
                    + `<path d="M 47 46 L 53 46 M 31 44 L 24 42 M 69 44 L 76 42" stroke="${ink}" stroke-width="2" stroke-linecap="round"/>`,
                draw: () => '',
            },
        },
    },
};

export const PART_KEYS = Object.keys(PARTS);

/**
 * The characters that ship with the site, written as part choices so they
 * are no different from anything an admin builds.
 */
export const PRESETS = {
    cat: { name: 'Cat', hue: 30, tone: 'normal', body: 'slim', head: 'round', ears: 'cat', sideParts: 'whiskers', hat: 'none', snout: 'cat', mask: 'none', accessory: 'none' },
    dog: { name: 'Dog', hue: 25, tone: 'normal', body: 'slim', head: 'round', ears: 'none', sideParts: 'floppy', hat: 'none', snout: 'dog', mask: 'none', accessory: 'none' },
    robot: { name: 'Robot', hue: 200, tone: 'metal', body: 'boxy', head: 'box', ears: 'none', sideParts: 'panels', hat: 'antenna', snout: 'none', mask: 'none', accessory: 'none' },
    alien: { name: 'Alien', hue: 120, tone: 'bright', body: 'slim', head: 'egg', ears: 'none', sideParts: 'none', hat: 'feelers', snout: 'none', mask: 'none', accessory: 'none' },
    wizard: { name: 'Wizard', hue: 270, tone: 'normal', body: 'robe', head: 'round', ears: 'none', sideParts: 'none', hat: 'wizard', snout: 'none', mask: 'none', accessory: 'beard' },
    ninja: { name: 'Ninja', hue: 205, tone: 'dark', body: 'slim', head: 'round', ears: 'none', sideParts: 'none', hat: 'none', snout: 'none', mask: 'ninja', accessory: 'scarf' },
    pirate: { name: 'Pirate', hue: 45, tone: 'normal', body: 'slim', head: 'round', ears: 'none', sideParts: 'none', hat: 'bandana', snout: 'none', mask: 'none', accessory: 'eyepatch' },
    knight: { name: 'Knight', hue: 210, tone: 'metal', body: 'sturdy', head: 'helmet', ears: 'none', sideParts: 'none', hat: 'plume', snout: 'none', mask: 'visor', accessory: 'none' },
};

export const DEFAULT_SPEC = {
    hue: 200,
    tone: 'normal',
    body: 'slim',
    head: 'round',
    ears: 'none',
    sideParts: 'none',
    hat: 'none',
    snout: 'none',
    mask: 'none',
    accessory: 'none',
};

/**
 * A complete, valid spec: unknown parts and options fall back to the
 * default, so a character saved before a part existed still draws.
 */
export function normaliseSpec(spec = {}) {
    const clean = { ...DEFAULT_SPEC };

    for (const part of PART_KEYS) {
        const chosen = spec[part];
        clean[part] = PARTS[part].options[chosen] ? chosen : DEFAULT_SPEC[part];
    }

    const hue = Number(spec.hue);
    clean.hue = Number.isFinite(hue) ? ((hue % 360) + 360) % 360 : DEFAULT_SPEC.hue;
    clean.tone = TONES[spec.tone] ? spec.tone : DEFAULT_SPEC.tone;

    return clean;
}

/**
 * Whether this character's face is covered, and so wears no expression.
 */
export function hidesFace(spec) {
    return PARTS.mask.options[normaliseSpec(spec).mask]?.hidesFace === true;
}

/**
 * The character's parts as SVG, in the order they are drawn: what goes
 * behind the head, the body, the head itself, and what is worn over it.
 */
export function drawParts(spec, palette) {
    const clean = normaliseSpec(spec);
    const part = (key) => PARTS[key].options[clean[key]];

    const behind = PART_KEYS
        .filter(key => PARTS[key].behind)
        .map(key => part(key).draw?.(palette) || '')
        .join('');

    // Everything worn on the head, drawn over it in listed order
    const front = PART_KEYS
        .filter(key => !PARTS[key].behind && key !== 'body' && key !== 'head')
        .map(key => part(key).draw?.(palette) || '')
        .join('');

    const over = PART_KEYS
        .map(key => part(key).over?.(palette) || '')
        .join('');

    return {
        behind,
        body: part('body').draw(palette),
        head: PARTS.head.options[clean.head].draw(palette) + front,
        over,
    };
}

/**
 * The characters that exist right now, keyed by name.
 *
 * Drawing a character must not depend on a store: characters are drawn in
 * previews and tests that have none. The store fills this in once it has
 * loaded, and until then — and if the load fails — the built-in characters
 * stand in, so a character always draws as something.
 */
const registry = new Map();

export function registerCharacters(characters = []) {
    registry.clear();

    for (const character of characters) {
        if (character?.key) registry.set(character.key, normaliseSpec(character.spec));
    }
}

export function specForKey(key) {
    return registry.get(key) || PRESETS[key] || null;
}

/**
 * The options for one part, ready for a picker.
 */
export function optionsFor(part) {
    return Object.entries(PARTS[part]?.options || {}).map(([value, option]) => ({
        value,
        label: option.label,
    }));
}
