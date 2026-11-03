import { describe, it, expect } from 'vitest';
import {
    settingsCategories,
    movedSettings,
    movedCategories,
    getCategoryBySlug,
    getSettingBySlug,
    resolveMovedSetting,
    getTotalSettingsCount,
} from '@/configs/settingsConfig.js';

// The settings pages, found the same way SettingsPage.vue finds them:
// the folder a page sits in is the category it belongs to
const pages = import.meta.glob('../pages/admin/settings/*/*.vue');

const pageKey = (categoryId, component) => `../pages/admin/settings/${categoryId}/${component}.vue`;

const everySetting = settingsCategories.flatMap(
    category => category.settings.map(setting => ({ category, setting }))
);

describe('the admin settings categories', () => {
    it('gives every category its own id', () => {
        const ids = settingsCategories.map(category => category.id);

        expect(new Set(ids).size).toBe(ids.length);
    });

    it('gives every setting its own id within its category', () => {
        for (const category of settingsCategories) {
            const ids = category.settings.map(setting => setting.id);

            expect(new Set(ids).size, `duplicate setting id in ${category.id}`).toBe(ids.length);
        }
    });

    it('keeps a category worth opening, rather than one holding a single item', () => {
        for (const category of settingsCategories) {
            expect(category.settings.length, `${category.id} holds too little to be a category`)
                .toBeGreaterThan(1);
        }
    });

    it('says of every setting how to open it', () => {
        for (const { category, setting } of everySetting) {
            expect(
                Boolean(setting.component) || Boolean(setting.routeName),
                `${category.id}/${setting.id} has neither a component nor a route`
            ).toBe(true);
        }
    });

    it('has a page on disk for every setting that names one', () => {
        for (const { category, setting } of everySetting) {
            if (!setting.component) continue;

            expect(pages, `${category.id}/${setting.id}`)
                .toHaveProperty(pageKey(category.id, setting.component));
        }
    });

    it('leaves no settings page behind in a folder nothing points at', () => {
        const claimed = everySetting
            .filter(({ setting }) => setting.component)
            .map(({ category, setting }) => pageKey(category.id, setting.component));

        // The Discord webhooks page has a route of its own, so the
        // category links to it by name rather than by component
        const known = [...claimed, pageKey('integrations', 'DiscordWebhooksPage')];

        expect(Object.keys(pages).filter(page => !known.includes(page))).toEqual([]);
    });

    it('finds a category and a setting by their slugs', () => {
        expect(getCategoryBySlug('appearance')?.title).toBe('Appearance');
        expect(getSettingBySlug('appearance', 'theme-mode')?.component).toBe('ThemeModePage');
        expect(getCategoryBySlug('theme')).toBeUndefined();
        expect(getTotalSettingsCount()).toBe(everySetting.length);
    });
});

describe('the settings that were regrouped', () => {
    it('sends every old address to one that exists now', () => {
        for (const [from, to] of Object.entries(movedSettings)) {
            const [category, setting] = to.split('/');

            expect(getSettingBySlug(category, setting), `${from} points at nothing`).toBeTruthy();
        }
    });

    it('never shadows a category that is still in use', () => {
        for (const old of Object.keys(movedCategories)) {
            // A redirect for a live id would make that category
            // unreachable, which is worse than a dead link
            expect(getCategoryBySlug(old), `${old} is both moved and current`).toBeUndefined();
        }
    });

    it('resolves an old address to where the page went', () => {
        expect(resolveMovedSetting('theme', 'mode')).toEqual({ category: 'appearance', setting: 'theme-mode' });
        expect(resolveMovedSetting('footer', 'custom-html')).toEqual({ category: 'layout', setting: 'footer-html' });
        expect(movedCategories.achievements).toBe('community');
    });

    it('leaves an address that never moved alone', () => {
        expect(resolveMovedSetting('seo', 'metadata')).toBeNull();
        expect(movedCategories.seo).toBeUndefined();
    });

    it('answers nothing for a category name taken from Object itself', () => {
        // The name comes out of the URL, so /admin/settings/constructor
        // must not resolve to something inherited
        expect(movedCategories.constructor).toBeUndefined();
        expect(movedCategories.toString).toBeUndefined();
    });
});
