import { describe, it, expect, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { WIDGET_TYPES, isWidgetEnabled, RETIRED_TYPES } from '@/configs/dashboardWidgets.js';
import { FEATURES } from '@/configs/features.js';
import { useSettingsStore } from '@/store/settingStore.js';

describe('the podcast dashboard widget', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('is in the registry and tied to the podcast feature', () => {
        const definition = WIDGET_TYPES.podcast;

        expect(definition).toBeTruthy();
        expect(definition.component).toBe('PodcastWidget');
        expect(definition.feature).toBe('podcast');
    });

    it('is offered while the feature is on', () => {
        const store = useSettingsStore();

        // Unset means on, so a fresh install gets the widget
        expect(isWidgetEnabled(WIDGET_TYPES.podcast, store)).toBe(true);

        store.appSettings.feature_podcast_enabled = true;
        expect(isWidgetEnabled(WIDGET_TYPES.podcast, store)).toBe(true);
    });

    it('is withheld once the feature is switched off', () => {
        const store = useSettingsStore();

        store.appSettings.feature_podcast_enabled = false;
        expect(isWidgetEnabled(WIDGET_TYPES.podcast, store)).toBe(false);

        // Saved as a string by the settings API
        store.appSettings.feature_podcast_enabled = '0';
        expect(isWidgetEnabled(WIDGET_TYPES.podcast, store)).toBe(false);
    });

    it('switching the podcast off leaves the other widgets alone', () => {
        const store = useSettingsStore();
        store.appSettings.feature_podcast_enabled = false;

        expect(isWidgetEnabled(WIDGET_TYPES.forum, store)).toBe(true);
        expect(isWidgetEnabled(WIDGET_TYPES.notifications, store)).toBe(true);
    });
});

describe('every widget is gated on something real', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    /**
     * A widget tagged with a feature that is not in the registry would
     * read as permanently enabled, because isFeatureEnabled defaults to
     * true for a setting it has never heard of — so the toggle screen
     * would show no way to turn that widget off.
     */
    it('every widget feature tag exists in the feature registry', () => {
        const known = new Set(FEATURES.map(feature => feature.key));

        for (const [type, definition] of Object.entries(WIDGET_TYPES)) {
            if (definition.feature) {
                expect(known.has(definition.feature), `widget "${type}" names unknown feature "${definition.feature}"`).toBe(true);
            }
        }
    });

    it('a widget is either feature-tagged or carries its own enabled check, or is always on', () => {
        const store = useSettingsStore();

        for (const [type, definition] of Object.entries(WIDGET_TYPES)) {
            // Every definition has to answer the question at all
            expect(typeof isWidgetEnabled(definition, store), `widget "${type}"`).toBe('boolean');
        }
    });

    it('no widget type is also listed as retired', () => {
        for (const type of Object.keys(WIDGET_TYPES)) {
            expect(RETIRED_TYPES).not.toContain(type);
        }
    });
});
