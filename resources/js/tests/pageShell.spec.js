import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import LoadingState from '@/components/common/LoadingState.vue';

const stubs = {
    'v-container': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button><slot /></button>' },
    'v-icon': { template: '<i><slot /></i>' },
    'v-empty-state': { props: ['icon', 'title', 'text'], template: '<div class="stub-empty"><h2>{{ title }}</h2><p>{{ text }}</p><slot /><slot name="actions" /></div>' },
    'v-progress-circular': { template: '<span class="spinner" />' },
};

describe('page shell components', () => {
    it('PageHeader renders title, subtitle and actions with the band by default', () => {
        const w = mount(PageHeader, {
            props: { title: 'Forum', subtitle: 'Talk', icon: 'mdi-forum' },
            slots: { actions: '<button class="cta">New</button>' },
            global: { stubs },
        });
        expect(w.find('h1').text()).toBe('Forum');
        expect(w.find('h1').classes()).toContain('text-gradient');
        expect(w.text()).toContain('Talk');
        expect(w.find('.cta').exists()).toBe(true);
        expect(w.find('header').classes()).toContain('page-header--band');
    });

    it('PageHeader can drop the band and show a back button', () => {
        const w = mount(PageHeader, { props: { title: 'X', band: false, backTo: '/wiki' }, global: { stubs } });
        expect(w.find('header').classes()).not.toContain('page-header--band');
        expect(w.find('button').exists()).toBe(true);
    });

    it('EmptyState passes title/text through and renders actions', () => {
        const w = mount(EmptyState, {
            props: { title: 'Nothing here', text: 'Add something' },
            slots: { actions: '<button class="act">Add</button>' },
            global: { stubs },
        });
        expect(w.text()).toContain('Nothing here');
        expect(w.text()).toContain('Add something');
        expect(w.find('.act').exists()).toBe(true);
    });

    it('LoadingState shows a spinner and optional text', () => {
        const w = mount(LoadingState, { props: { text: 'Loading…' }, global: { stubs } });
        expect(w.find('.spinner').exists()).toBe(true);
        expect(w.text()).toContain('Loading…');
        expect(w.classes()).toContain('py-12');
    });
});
