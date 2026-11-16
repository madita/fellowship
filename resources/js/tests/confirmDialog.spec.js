import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import ConfirmDialog from '@/components/common/ConfirmDialog.vue';

/**
 * The confirm button was bound to `confirmationKeyword && …`, which is the
 * empty string when no keyword is asked for. Vue casts an empty string on a
 * Boolean prop to true, so the button sat disabled on every dialog opened
 * through the dialog store, which defaults the keyword to ''.
 */
describe('ConfirmDialog confirm button', () => {
    const stubs = {
        VDialog: { template: '<div><slot /></div>' },
        VCard: { template: '<div><slot /></div>' },
        VCardTitle: { template: '<div><slot /></div>' },
        VCardText: { template: '<div><slot /></div>' },
        VCardActions: { template: '<div><slot /></div>' },
        VSpacer: { template: '<span />' },
        // Implements v-model, so typing actually reaches the component
        VTextField: defineComponent({
            props: { modelValue: { type: String, default: '' }, label: String, variant: String },
            emits: ['update:modelValue'],
            setup: (props, { emit }) => () => h('input', {
                value: props.modelValue,
                onInput: (event) => emit('update:modelValue', event.target.value),
            }),
        }),
        // Declares `disabled` as Boolean, exactly like Vuetify's button
        VBtn: defineComponent({
            props: { disabled: Boolean, color: String, variant: String },
            setup: (props, { slots }) => () =>
                h('button', { disabled: props.disabled, class: 'vbtn' }, slots.default ? slots.default() : []),
        }),
    };

    const mountDialog = (props = {}) => mount(ConfirmDialog, {
        props: { modelValue: true, resolve: () => {}, ...props },
        global: { stubs, mocks: { $t: (key) => key } },
    });

    const confirmButton = (wrapper) => wrapper.findAllComponents(stubs.VBtn).at(-1);
    const isDisabled = (wrapper) => confirmButton(wrapper).props('disabled');

    it('is enabled when no keyword is required, including the store default of an empty string', () => {
        expect(isDisabled(mountDialog())).toBe(false);
        expect(isDisabled(mountDialog({ confirmationKeyword: '' }))).toBe(false);
    });

    it('stays disabled until a required keyword is typed', async () => {
        const wrapper = mountDialog({ confirmationKeyword: 'DELETE' });
        expect(isDisabled(wrapper)).toBe(true);

        await wrapper.find('input').setValue('DELETE');
        expect(isDisabled(wrapper)).toBe(false);
    });

    it('resolves true when confirmed', async () => {
        let answer = null;
        const wrapper = mountDialog({ resolve: (value) => { answer = value; } });

        await confirmButton(wrapper).trigger('click');

        expect(answer).toBe(true);
    });
});
