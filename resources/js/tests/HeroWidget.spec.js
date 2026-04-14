import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HeroWidget from '@/components/landing/widgets/HeroWidget.vue';

describe('HeroWidget.vue', () => {
    const defaultProps = {
        content: {
            title: 'Welcome to our site',
            subtitle: 'This is a great platform',
            primaryButton: { text: 'Get Started', link: '/signup' },
            secondaryButton: { text: 'Learn More', link: '/about' },
        },
        config: {},
    };

    const createStubs = () => ({
        'v-sheet': { template: '<div><slot /></div>' },
        'v-container': { template: '<div><slot /></div>' },
        'v-btn': { template: '<button><slot /></button>', props: ['to', 'size', 'color', 'class'] },
    });

    it('renders title and subtitle', () => {
        const wrapper = mount(HeroWidget, {
            props: defaultProps,
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).toContain('Welcome to our site');
        expect(wrapper.text()).toContain('This is a great platform');
    });

    it('removes asterisks from title', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome to our *awesome* site',
                    subtitle: 'Test',
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).toContain('Welcome to our awesome site');
        expect(wrapper.text()).not.toContain('*');
    });

    it('renders primary button when text and link are provided', () => {
        const wrapper = mount(HeroWidget, {
            props: defaultProps,
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).toContain('Get Started');
    });

    it('renders secondary button when text and link are provided', () => {
        const wrapper = mount(HeroWidget, {
            props: defaultProps,
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).toContain('Learn More');
    });

    it('hides primary button when text is empty', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome',
                    subtitle: 'Test',
                    primaryButton: { text: '', link: '/signup' },
                    secondaryButton: null,
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).not.toContain('Get Started');
        expect(wrapper.html()).not.toContain('button');
    });

    it('hides primary button when link is empty', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome',
                    subtitle: 'Test',
                    primaryButton: { text: 'Click Me', link: '' },
                    secondaryButton: null,
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).not.toContain('Click Me');
    });

    it('hides button when button object is null', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome',
                    subtitle: 'Test',
                    primaryButton: null,
                    secondaryButton: null,
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        const buttons = wrapper.findAll('button');
        expect(buttons).toHaveLength(0);
    });

    it('shows only primary button when secondary is null', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome',
                    subtitle: 'Test',
                    primaryButton: { text: 'Get Started', link: '/signup' },
                    secondaryButton: null,
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).toContain('Get Started');
        expect(wrapper.text()).not.toContain('Learn More');
    });

    it('shows only secondary button when primary is null', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome',
                    subtitle: 'Test',
                    primaryButton: null,
                    secondaryButton: { text: 'Learn More', link: '/about' },
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).not.toContain('Get Started');
        expect(wrapper.text()).toContain('Learn More');
    });

    it('uses default content when no content prop provided', () => {
        const wrapper = mount(HeroWidget, {
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.text()).toContain('Your awesome community');
    });

    it('renders both buttons when both have text and link', () => {
        const wrapper = mount(HeroWidget, {
            props: defaultProps,
            global: {
                stubs: {
                    'v-sheet': { template: '<div><slot /></div>' },
                    'v-container': { template: '<div><slot /></div>' },
                    'v-btn': {
                        template: '<button class="test-btn"><slot /></button>',
                        props: ['to', 'size', 'color'],
                    },
                },
            },
        });

        const buttons = wrapper.findAll('.test-btn');
        expect(buttons).toHaveLength(2);
        expect(buttons[0].text()).toBe('Get Started');
        expect(buttons[1].text()).toBe('Learn More');
    });

    it('handles empty subtitle gracefully', () => {
        const wrapper = mount(HeroWidget, {
            props: {
                content: {
                    title: 'Welcome',
                    subtitle: '',
                    primaryButton: null,
                    secondaryButton: null,
                },
            },
            global: {
                stubs: createStubs(),
            },
        });

        expect(wrapper.exists()).toBe(true);
        expect(wrapper.text()).toContain('Welcome');
    });
});
