import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import en from '@/translations/en.js';
import ProfileDialog from '@/components/common/ProfileDialog.vue';

vi.mock('axios');
vi.mock('@/composables/useDialog.js', () => ({
    useDialog: () => ({ requestError: vi.fn(() => Promise.resolve()) }),
}));

const i18n = createI18n({ legacy: false, locale: 'en', messages: { en }, missingWarn: false, fallbackWarn: false });

// Each field renders as an input carrying its label and current value, so
// the test can read what the form was filled in with.
const field = tag => ({
    props: ['label', 'modelValue', 'items'],
    template: `<${tag} class="field" :data-label="label" :data-value="JSON.stringify(modelValue)" />`,
});

const stubs = {
    VDialog: { template: '<div><slot /></div>' },
    VCard: { template: '<div><slot /></div>' },
    VCardTitle: { template: '<h2><slot /></h2>' },
    VCardText: { template: '<div><slot /></div>' },
    VCardActions: { template: '<div><slot /></div>' },
    VDivider: { template: '<hr />' },
    VSpacer: { template: '<span />' },
    VBtn: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
    'v-alert': { props: ['text'], template: '<div class="alert">{{ text }}</div>' },
    'v-select': field('select'),
    'v-combobox': field('div'),
    'v-textarea': field('textarea'),
    'v-text-field': field('input'),
};

const profileOptions = {
    form: [
        { name: 'character', label: 'Character', type: 'text' },
        { name: 'diet', label: 'Diet', type: 'text' },
    ],
};

const event = {
    id: 9,
    start: '2099-01-01T10:00:00Z',
    end: '2099-01-01T18:00:00Z',
    extendedProps: { event_profile_id: 3 },
};

const render = async ({ draft = null, isGoing = null } = {}) => {
    axios.get.mockImplementation((url) => {
        if (url.includes('event-profiles')) return Promise.resolve({ data: { options: profileOptions } });
        if (url.includes('profile-draft')) return Promise.resolve({ data: { data: draft } });
        return Promise.resolve({ data: {} });
    });

    const wrapper = mount(ProfileDialog, {
        props: { modelValue: true, event, isGoing, answer: 'going', resolve: vi.fn() },
        global: { plugins: [i18n], stubs },
    });
    await flushPromises();
    return wrapper;
};

const valueOf = (wrapper, label) => {
    const input = wrapper.findAll('.field').find(node => node.attributes('data-label') === label);

    return input ? JSON.parse(input.attributes('data-value')) : undefined;
};

describe('ProfileDialog prefilling', () => {
    beforeEach(() => vi.clearAllMocks());

    it('leaves the form empty when there is nothing to carry over', async () => {
        const w = await render();

        expect(valueOf(w, 'Character')).toBe('');
        expect(w.find('.alert').exists()).toBe(false);
    });

    it('fills the form in from the last event answered, and says so', async () => {
        const w = await render({ draft: { character: 'Gandalf', diet: 'vegetarian' } });

        expect(valueOf(w, 'Character')).toBe('Gandalf');
        expect(valueOf(w, 'Diet')).toBe('vegetarian');
        expect(w.find('.alert').text()).toBe(en.profileDialog.prefilled);
    });

    // The days belong to this event's own dates, not to an earlier one
    it('never carries the days over', async () => {
        const w = await render({ draft: { character: 'Gandalf', days: ['Monday'] } });

        expect(w.vm.formData.days).toEqual([]);
    });

    it('prefers the answer already given for this event over an older one', async () => {
        const w = await render({
            draft: { character: 'Gandalf', diet: 'vegetarian' },
            isGoing: { type: 'going', profile: { character: 'Radagast', diet: 'mushrooms' } },
        });

        expect(valueOf(w, 'Character')).toBe('Radagast');
        expect(w.find('.alert').exists()).toBe(false);
    });

    // An answer given for this event must survive the form being built,
    // which happens across an await
    it('does not ask for a draft once the event has been answered', async () => {
        await render({ isGoing: { type: 'going', profile: { character: 'Radagast' } } });

        const asked = axios.get.mock.calls.some(([url]) => String(url).includes('profile-draft'));

        expect(asked).toBe(false);
    });
});
