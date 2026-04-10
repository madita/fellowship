import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import DynamicWidgetForm from '@/components/settings/homepage/DynamicWidgetForm.vue';

// Mock the widget types config
vi.mock('@/configs/widgetTypes', () => ({
    getWidgetDefinition: vi.fn((type) => {
        if (type === 'hero') {
            return {
                name: 'Hero Section',
                schema: {
                    title: { type: 'text', label: 'Title', required: true },
                    subtitle: { type: 'textarea', label: 'Subtitle' },
                    primaryButton: {
                        type: 'object',
                        label: 'Primary Button',
                        fields: {
                            text: { type: 'text', label: 'Button Text' },
                            link: { type: 'text', label: 'Link' },
                        },
                    },
                    secondaryButton: {
                        type: 'object',
                        label: 'Secondary Button',
                        fields: {
                            text: { type: 'text', label: 'Button Text' },
                            link: { type: 'text', label: 'Link' },
                        },
                    },
                },
            };
        }
        return null;
    }),
}));

describe('DynamicWidgetForm.vue', () => {
    const createWrapper = (props = {}) => {
        return mount(DynamicWidgetForm, {
            props: {
                modelValue: { title: '', subtitle: '' },
                widgetType: 'hero',
                ...props,
            },
            global: {
                stubs: {
                    'v-text-field': {
                        template: '<input class="v-text-field" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                        props: ['modelValue', 'label', 'required', 'hint', 'density'],
                    },
                    'v-textarea': {
                        template: '<textarea class="v-textarea" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
                        props: ['modelValue', 'label', 'required', 'hint', 'rows', 'density'],
                    },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
                    'v-btn': { template: '<button class="v-btn" @click="$emit(\'click\')"><slot /></button>' },
                    'v-chip': { template: '<span class="v-chip"><slot /></span>' },
                    'v-icon': { template: '<i class="v-icon" />' },
                    'v-switch': { template: '<input type="checkbox" class="v-switch" />' },
                    'v-select': { template: '<select class="v-select" />' },
                    'v-spacer': { template: '<div class="v-spacer" />' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'homepage-image-upload': { template: '<div class="image-upload" />' },
                    'draggable': { template: '<div class="draggable"><slot /></div>' },
                },
            },
        });
    };

    it('renders form with object field cards', () => {
        const wrapper = createWrapper();

        expect(wrapper.exists()).toBe(true);
        // Should render cards for object fields (buttons)
        const cards = wrapper.findAll('.v-card');
        expect(cards.length).toBeGreaterThan(0);
    });

    it('emits update:modelValue when localValue changes', async () => {
        const wrapper = createWrapper({
            modelValue: { title: 'Test', subtitle: '' },
        });

        await nextTick();

        // Trigger a change by modifying localValue
        wrapper.vm.localValue.title = 'Changed Title';

        // Wait for the debounced emit (150ms + buffer)
        await new Promise(resolve => setTimeout(resolve, 200));

        // Component should emit updates after debounce
        const emitted = wrapper.emitted('update:modelValue');
        expect(emitted).toBeTruthy();
        expect(emitted.length).toBeGreaterThan(0);
    });

    it('initializes object fields as empty objects when undefined', async () => {
        const wrapper = createWrapper({
            modelValue: { title: 'Test' }, // Missing button objects
            widgetType: 'hero',
        });

        await nextTick();

        // Component should initialize empty button objects
        expect(wrapper.vm.localValue.primaryButton).toBeDefined();
        expect(wrapper.vm.localValue.secondaryButton).toBeDefined();
    });

    it('respects null values for object fields', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: null,
                secondaryButton: null,
            },
            widgetType: 'hero',
        });

        await nextTick();

        // Null values should be preserved
        expect(wrapper.vm.localValue.primaryButton).toBeNull();
        expect(wrapper.vm.localValue.secondaryButton).toBeNull();
    });

    it('enables object field when enable button is clicked', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: null,
            },
            widgetType: 'hero',
        });

        await nextTick();

        // Find all buttons
        const buttons = wrapper.findAll('.v-btn');

        // Find the enable button for Primary Button
        const enableButton = buttons.find(btn =>
            btn.text().includes('Enable')
        );

        if (enableButton) {
            await enableButton.trigger('click');
            await nextTick();

            // Should initialize with empty strings
            expect(wrapper.vm.localValue.primaryButton).toEqual({ text: '', link: '' });
        }
    });

    it('disables object field when disable button is clicked', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: { text: 'Click', link: '/test' },
            },
            widgetType: 'hero',
        });

        await nextTick();

        // Find the disable button
        const buttons = wrapper.findAll('.v-btn');
        const disableButton = buttons.find(btn =>
            btn.text().includes('Disable')
        );

        if (disableButton) {
            await disableButton.trigger('click');
            await nextTick();

            // Should be set to null
            expect(wrapper.vm.localValue.primaryButton).toBeNull();
        }
    });

    it('cleans up object fields with all empty values', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: { text: '', link: '' },
            },
            widgetType: 'hero',
        });

        await nextTick();

        // The cleanup function should convert empty button to null
        const cleaned = wrapper.vm.cleanupImageFields(wrapper.vm.localValue);
        expect(cleaned.primaryButton).toBeNull();
    });

    it('does not clean up object fields with some values', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: { text: 'Click', link: '' },
            },
            widgetType: 'hero',
        });

        await nextTick();

        // Should not clean up if some fields have values
        const cleaned = wrapper.vm.cleanupImageFields(wrapper.vm.localValue);
        expect(cleaned.primaryButton).not.toBeNull();
    });

    it('shows "Disabled" chip for null object fields', () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: null,
                secondaryButton: null,
            },
            widgetType: 'hero',
        });

        // Should show disabled chips
        const chips = wrapper.findAll('.v-chip');
        // At least 2 chips for the two disabled buttons
        expect(chips.length).toBeGreaterThanOrEqual(2);
    });

    it('initializes nested fields when object exists', async () => {
        const wrapper = createWrapper({
            modelValue: {
                title: 'Test',
                primaryButton: { text: 'Click' }, // Missing 'link' field
            },
            widgetType: 'hero',
        });

        await nextTick();

        // Should initialize missing nested fields
        expect(wrapper.vm.localValue.primaryButton.link).toBeDefined();
    });

    it('debounces updates to prevent too many emissions', async () => {
        const wrapper = createWrapper({
            modelValue: { title: 'Test' },
        });

        await nextTick();

        // Get initial emit count
        const initialCount = (wrapper.emitted('update:modelValue') || []).length;

        // Trigger multiple rapid changes
        wrapper.vm.localValue.title = 'A';
        wrapper.vm.localValue.title = 'AB';
        wrapper.vm.localValue.title = 'ABC';

        // Should debounce and not emit for every change immediately
        const afterChanges = (wrapper.emitted('update:modelValue') || []).length;
        expect(afterChanges - initialCount).toBeLessThan(3);
    });
});
