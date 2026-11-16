import { describe, it, expect } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/translations/en.js';
import de from '@/translations/de.js';
import { useActivityText } from '@/composables/useActivityText.js';

/**
 * How an activity entry reads.
 *
 * This replaced three copies: ForumActivityFeed switched on the event with
 * i18n, the landing ForumWidget switched on the same four events with
 * hard-coded English, and neither covered anything outside the forum.
 */
// One instance per locale — a fresh createI18n per call re-registers the
// plugin's components and buries the output in warnings.
const i18ns = {
    en: createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false }),
    de: createI18n({ legacy: false, locale: 'de', messages: { de }, missingWarn: false, fallbackWarn: false }),
};

const harness = (locale = 'en') => {
    let api;
    mount(
        defineComponent({
            setup() {
                api = useActivityText();

                return () => h('div');
            },
        }),
        { global: { plugins: [i18ns[locale]] } }
    );

    return api;
};

describe('useActivityText', () => {
    it('reads an event as a sentence, with the subject title in it', () => {
        const { activityText } = harness();

        const text = activityText({ event: 'thread_created', title: 'Council of Elrond' });

        expect(text).toContain('Council of Elrond');
        expect(text).not.toContain('activity.events');
    });

    /**
     * The whole point of the shared feed: a wiki or ticket entry gets
     * wording too, which the forum-only switch never gave it.
     */
    it('covers the features beyond the forum', () => {
        const { activityText } = harness();

        expect(activityText({ event: 'page_created', title: 'Rivendell' })).toContain('Rivendell');
        expect(activityText({ event: 'ticket_created', title: 'Broken lamp' })).toContain('Broken lamp');
    });

    it('fills in the extras an event carries', () => {
        const { activityText } = harness();

        const text = activityText({
            event: 'ticket_status_changed',
            title: 'Broken lamp',
            properties: { status: 'resolved' },
        });

        expect(text).toContain('resolved');
    });

    /**
     * An event with no wording yet still reads — as the English the
     * backend stored, rather than as a raw key or an empty row.
     */
    it('falls back to the stored description for an event it does not know', () => {
        const { activityText } = harness();

        expect(activityText({ event: 'something_new', description: 'something new' }))
            .toBe('something new');
    });

    it('survives an entry that is missing altogether', () => {
        const { activityText } = harness();

        expect(activityText(null)).toBe('');
        expect(activityText({})).toBe('');
    });

    it('is translated, not English with a German wrapper', () => {
        const english = harness('en').activityText({ event: 'thread_created', title: 'X' });
        const german = harness('de').activityText({ event: 'thread_created', title: 'X' });

        expect(german).not.toBe(english);
        expect(german).toContain('X');
    });

    describe('icons', () => {
        it('gives each log its own icon', () => {
            const { activityIcon } = harness();

            expect(activityIcon({ log: 'wiki' })).toBe(en.activity.icons.wiki);
            expect(activityIcon({ log: 'tickets' })).not.toBe(en.activity.icons.wiki);
        });

        it('falls back rather than rendering nothing', () => {
            const { activityIcon } = harness();

            expect(activityIcon({ log: 'not-a-log' })).toBe('mdi-pulse');
            expect(activityIcon(null)).toBe('mdi-pulse');
        });
    });
});
