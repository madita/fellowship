import { describe, it, expect, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { FEATURES, featureSettingKey } from '@/configs/features.js';
import { useSettingsStore } from '@/store/settingStore.js';
import navigation from '@/configs/navigation.js';
import toolbar from '@/configs/toolbar.js';

describe('feature registry', () => {
    it('every feature has a key, label and at least one route prefix', () => {
        for (const feature of FEATURES) {
            expect(feature.key).toBeTruthy();
            expect(feature.label).toBeTruthy();
            expect(Array.isArray(feature.routePrefixes)).toBe(true);
            expect(feature.routePrefixes.length).toBeGreaterThan(0);
        }
    });

    it('derives the setting key from the feature key', () => {
        expect(featureSettingKey('forum')).toBe('feature_forum_enabled');
    });

    it('feature keys are unique', () => {
        const keys = FEATURES.map(f => f.key);
        expect(new Set(keys).size).toBe(keys.length);
    });

    const collectTags = (items) => (items || []).flatMap(item => [
        ...(item.feature ? [item.feature] : []),
        ...collectTags(item.items),
    ]);

    it('every feature tag used in the navigation exists in the registry', () => {
        const known = new Set(FEATURES.map(f => f.key));
        const used = navigation.menu.flatMap(section => collectTags(section.items));
        expect(used.length).toBeGreaterThan(0);
        for (const tag of used) {
            expect(known.has(tag), `unknown feature tag "${tag}" in navigation`).toBe(true);
        }
    });

    it('every feature tag used in the toolbar user menu exists in the registry', () => {
        const known = new Set(FEATURES.map(f => f.key));
        for (const tag of collectTags(toolbar.user)) {
            expect(known.has(tag), `unknown feature tag "${tag}" in toolbar`).toBe(true);
        }
    });

    it('admin section items are never feature-tagged (admin stays available)', () => {
        const adminSection = navigation.menu.find(section => section.role === 'admin');
        expect(adminSection).toBeTruthy();
        expect(collectTags(adminSection.items)).toEqual([]);
    });

    it('no feature blocks admin routes', () => {
        for (const feature of FEATURES) {
            for (const prefix of feature.routePrefixes) {
                expect(prefix.startsWith('/admin'), `${feature.key} blocks admin route ${prefix}`).toBe(false);
            }
        }
    });
});

describe('settingsStore.isFeatureEnabled', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('defaults to enabled when the setting is missing', () => {
        const store = useSettingsStore();
        expect(store.isFeatureEnabled('forum')).toBe(true);
    });

    it('handles boolean and string representations', () => {
        const store = useSettingsStore();

        store.appSettings.feature_forum_enabled = false;
        expect(store.isFeatureEnabled('forum')).toBe(false);

        store.appSettings.feature_forum_enabled = true;
        expect(store.isFeatureEnabled('forum')).toBe(true);

        store.appSettings.feature_forum_enabled = '0';
        expect(store.isFeatureEnabled('forum')).toBe(false);

        store.appSettings.feature_forum_enabled = '1';
        expect(store.isFeatureEnabled('forum')).toBe(true);

        store.appSettings.feature_forum_enabled = 'false';
        expect(store.isFeatureEnabled('forum')).toBe(false);

        store.appSettings.feature_forum_enabled = 'true';
        expect(store.isFeatureEnabled('forum')).toBe(true);
    });

    it('toggles are independent per feature', () => {
        const store = useSettingsStore();
        store.appSettings.feature_forum_enabled = false;
        expect(store.isFeatureEnabled('forum')).toBe(false);
        expect(store.isFeatureEnabled('wiki')).toBe(true);
    });
});
