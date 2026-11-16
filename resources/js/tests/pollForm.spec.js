import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import PollForm from '@/components/poll/PollForm.vue';

const stubs = {
    'v-text-field': {
        template: '<input class="v-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        props: ['modelValue', 'label', 'errorMessages', 'disabled', 'type', 'min', 'hint'],
    },
    'v-textarea': {
        template: '<textarea class="v-textarea" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        props: ['modelValue', 'label', 'errorMessages', 'disabled', 'rows'],
    },
    'v-btn-toggle': { template: '<div class="v-btn-toggle"><slot /></div>', props: ['modelValue'] },
    'v-btn': {
        template: '<button class="v-btn" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
        props: ['disabled', 'value', 'icon'],
    },
    'v-switch': { template: '<input type="checkbox" class="v-switch" />', props: ['modelValue'] },
    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
    'v-icon': { template: '<i class="v-icon" />' },
};

const createWrapper = (props = {}) =>
    mount(PollForm, {
        props: { modelValue: null, ...props },
        global: { stubs },
    });

const lastEmitted = (wrapper) => {
    const events = wrapper.emitted('update:modelValue');
    return events[events.length - 1][0];
};

describe('PollForm.vue', () => {
    it('starts with two empty options and emits a poll object right away', async () => {
        const wrapper = createWrapper();
        await nextTick();

        expect(wrapper.vm.form.options).toHaveLength(2);
        const value = lastEmitted(wrapper);
        expect(value).toMatchObject({
            title: '',
            description: null,
            type: 'single',
            anonymous: false,
            closes_at: null,
            options: [],
        });
    });

    it('fails validation when the title is missing', async () => {
        const wrapper = createWrapper();
        wrapper.vm.form.options[0].text = 'Yes';
        wrapper.vm.form.options[1].text = 'No';
        await nextTick();

        expect(wrapper.vm.validate()).toBe(false);
        expect(wrapper.vm.errors.title.length).toBeGreaterThan(0);
        expect(wrapper.vm.errors.optionsCount).toHaveLength(0);
    });

    it('needs at least two non-empty options', async () => {
        const wrapper = createWrapper();
        wrapper.vm.form.title = 'Pizza night?';
        wrapper.vm.form.options[0].text = 'Yes';
        await nextTick();

        expect(wrapper.vm.validate()).toBe(false);
        expect(wrapper.vm.errors.options[1]).toBeTruthy();
        expect(wrapper.vm.errors.optionsCount.length).toBeGreaterThan(0);
        expect(wrapper.vm.errors.title).toHaveLength(0);
    });

    it('rejects a closing date in the past', async () => {
        const wrapper = createWrapper();
        wrapper.vm.form.title = 'Pizza night?';
        wrapper.vm.form.options[0].text = 'Yes';
        wrapper.vm.form.options[1].text = 'No';
        wrapper.vm.form.closes_at = '2000-01-01T12:00';
        await nextTick();

        expect(wrapper.vm.validate()).toBe(false);
        expect(wrapper.vm.errors.closes_at.length).toBeGreaterThan(0);
    });

    it('validates and emits the API poll object when everything is filled in', async () => {
        const wrapper = createWrapper();
        wrapper.vm.form.title = '  Pizza night?  ';
        wrapper.vm.form.description = 'Friday at 7';
        wrapper.vm.form.type = 'multiple';
        wrapper.vm.form.anonymous = true;
        wrapper.vm.form.options[0].text = 'Yes';
        wrapper.vm.form.options[1].text = ' No ';
        wrapper.vm.addOption();
        wrapper.vm.form.options[2].text = 'Maybe';
        await nextTick();

        expect(wrapper.vm.validate()).toBe(true);

        const value = lastEmitted(wrapper);
        expect(value).toEqual({
            title: 'Pizza night?',
            description: 'Friday at 7',
            type: 'multiple',
            anonymous: true,
            closes_at: null,
            options: ['Yes', 'No', 'Maybe'],
        });
    });

    it('serialises the closing date as an ISO string', async () => {
        const wrapper = createWrapper();
        const future = new Date(Date.now() + 24 * 60 * 60 * 1000);
        future.setSeconds(0, 0);
        const pad = (n) => String(n).padStart(2, '0');
        const local = `${future.getFullYear()}-${pad(future.getMonth() + 1)}-${pad(future.getDate())}T${pad(future.getHours())}:${pad(future.getMinutes())}`;

        wrapper.vm.form.title = 'When?';
        wrapper.vm.form.options[0].text = 'A';
        wrapper.vm.form.options[1].text = 'B';
        wrapper.vm.form.closes_at = local;
        await nextTick();

        expect(wrapper.vm.validate()).toBe(true);
        expect(lastEmitted(wrapper).closes_at).toBe(future.toISOString());
    });

    it('caps the options at ten and keeps at least two', async () => {
        const wrapper = createWrapper();
        for (let i = 0; i < 20; i++) wrapper.vm.addOption();
        expect(wrapper.vm.form.options).toHaveLength(10);

        for (let i = 0; i < 20; i++) wrapper.vm.removeOption(0);
        expect(wrapper.vm.form.options).toHaveLength(2);
    });

    it('seeds the fields from an existing poll passed through v-model', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Existing',
                description: 'Desc',
                type: 'multiple',
                anonymous: true,
                closes_at: null,
                options: [{ option_text: 'One' }, 'Two', 'Three'],
            },
        });
        await nextTick();

        expect(wrapper.vm.form.title).toBe('Existing');
        expect(wrapper.vm.form.type).toBe('multiple');
        expect(wrapper.vm.form.anonymous).toBe(true);
        expect(wrapper.vm.form.options.map((o) => o.text)).toEqual(['One', 'Two', 'Three']);
        expect(wrapper.vm.validate()).toBe(true);
        expect(lastEmitted(wrapper).options).toEqual(['One', 'Two', 'Three']);
    });
});
