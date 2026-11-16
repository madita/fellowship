import { describe, it, expect } from 'vitest';
import {
    WIDGET_TYPES,
    WIDGET_DEFINITIONS,
    getWidgetDefinition,
    getAvailableWidgets,
    getWidgetsByCategory,
    WIDGET_CATEGORIES,
} from '@/configs/widgetTypes';

describe('Widget Types Configuration', () => {
    describe('WIDGET_TYPES constant', () => {
        it('contains all expected widget type keys', () => {
            const expectedTypes = [
                'HERO',
                'PARTNERS',
                'STATS',
                'FEATURE_GRID',
                'FEATURE_SHOWCASE',
                'CTA',
                'TESTIMONIALS',
                'PRICING',
                'FAQ',
                'GALLERY',
                'BLOG_POSTS',
                'TEAM_MEMBERS',
                'CONTACT_FORM',
                'VIDEO',
                'NEWSLETTER',
                'TIMELINE',
                'CLIENTS',
                'CUSTOM_HTML',
            ];

            expectedTypes.forEach(type => {
                expect(WIDGET_TYPES).toHaveProperty(type);
            });
        });

        it('uses lowercase snake_case values', () => {
            Object.values(WIDGET_TYPES).forEach(value => {
                expect(value).toMatch(/^[a-z_]+$/);
            });
        });
    });

    describe('WIDGET_DEFINITIONS', () => {
        it('has definition for each widget type', () => {
            Object.values(WIDGET_TYPES).forEach(type => {
                expect(WIDGET_DEFINITIONS).toHaveProperty(type);
            });
        });

        it('each widget definition has required properties', () => {
            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                expect(def).toHaveProperty('name');
                expect(def).toHaveProperty('description');
                expect(def).toHaveProperty('icon');
                expect(def).toHaveProperty('category');
                expect(def).toHaveProperty('color');
                expect(def).toHaveProperty('defaultContent');
                expect(def).toHaveProperty('schema');
            });
        });

        it('all widget names are strings', () => {
            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                expect(typeof def.name).toBe('string');
                expect(def.name.length).toBeGreaterThan(0);
            });
        });

        it('all widget icons start with mdi-', () => {
            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                expect(def.icon).toMatch(/^mdi-/);
            });
        });

        it('all widgets have valid categories', () => {
            const validCategories = WIDGET_CATEGORIES.map(c => c.value);

            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                expect(validCategories).toContain(def.category);
            });
        });
    });

    describe('Hero Widget Definition', () => {
        it('has correct schema structure', () => {
            const hero = WIDGET_DEFINITIONS[WIDGET_TYPES.HERO];

            expect(hero.schema).toHaveProperty('title');
            expect(hero.schema).toHaveProperty('subtitle');
            expect(hero.schema).toHaveProperty('primaryButton');
            expect(hero.schema).toHaveProperty('secondaryButton');
        });

        it('has object type for button fields', () => {
            const hero = WIDGET_DEFINITIONS[WIDGET_TYPES.HERO];

            expect(hero.schema.primaryButton.type).toBe('object');
            expect(hero.schema.secondaryButton.type).toBe('object');
        });

        it('button fields have text and link subfields', () => {
            const hero = WIDGET_DEFINITIONS[WIDGET_TYPES.HERO];

            expect(hero.schema.primaryButton.fields).toHaveProperty('text');
            expect(hero.schema.primaryButton.fields).toHaveProperty('link');
            expect(hero.schema.secondaryButton.fields).toHaveProperty('text');
            expect(hero.schema.secondaryButton.fields).toHaveProperty('link');
        });

        it('has default content with buttons', () => {
            const hero = WIDGET_DEFINITIONS[WIDGET_TYPES.HERO];

            expect(hero.defaultContent).toHaveProperty('title');
            expect(hero.defaultContent).toHaveProperty('subtitle');
            expect(hero.defaultContent).toHaveProperty('primaryButton');
            expect(hero.defaultContent).toHaveProperty('secondaryButton');
        });
    });

    describe('Stats Widget Definition', () => {
        it('has array type schema', () => {
            const stats = WIDGET_DEFINITIONS[WIDGET_TYPES.STATS];

            expect(stats.schema).toHaveProperty('stats');
            expect(stats.schema.stats.type).toBe('array');
        });

        it('has itemSchema for array items', () => {
            const stats = WIDGET_DEFINITIONS[WIDGET_TYPES.STATS];

            expect(stats.schema.stats).toHaveProperty('itemSchema');
            expect(stats.schema.stats.itemSchema).toHaveProperty('title');
            expect(stats.schema.stats.itemSchema).toHaveProperty('value');
            expect(stats.schema.stats.itemSchema).toHaveProperty('description');
        });
    });

    describe('getWidgetDefinition function', () => {
        it('returns definition for valid widget type', () => {
            const hero = getWidgetDefinition('hero');

            expect(hero).toBeDefined();
            expect(hero.name).toBe('Hero Section');
        });

        it('returns null for invalid widget type', () => {
            const invalid = getWidgetDefinition('nonexistent');

            expect(invalid).toBeNull();
        });

        it('returns correct definition for each widget type', () => {
            Object.values(WIDGET_TYPES).forEach(type => {
                const def = getWidgetDefinition(type);
                expect(def).toBe(WIDGET_DEFINITIONS[type]);
            });
        });
    });

    describe('getAvailableWidgets function', () => {
        it('returns array of all widgets', () => {
            const widgets = getAvailableWidgets();

            expect(Array.isArray(widgets)).toBe(true);
            expect(widgets.length).toBe(Object.keys(WIDGET_DEFINITIONS).length);
        });

        it('each widget has type property', () => {
            const widgets = getAvailableWidgets();

            widgets.forEach(widget => {
                expect(widget).toHaveProperty('type');
                expect(widget).toHaveProperty('name');
                expect(widget).toHaveProperty('description');
            });
        });

        it('widget types match WIDGET_TYPES values', () => {
            const widgets = getAvailableWidgets();
            const widgetTypes = widgets.map(w => w.type);

            Object.values(WIDGET_TYPES).forEach(type => {
                expect(widgetTypes).toContain(type);
            });
        });
    });

    describe('getWidgetsByCategory function', () => {
        it('returns widgets for core category', () => {
            const coreWidgets = getWidgetsByCategory('core');

            expect(Array.isArray(coreWidgets)).toBe(true);
            expect(coreWidgets.length).toBeGreaterThan(0);

            coreWidgets.forEach(widget => {
                expect(widget.category).toBe('core');
            });
        });

        it('returns widgets for marketing category', () => {
            const marketingWidgets = getWidgetsByCategory('marketing');

            expect(Array.isArray(marketingWidgets)).toBe(true);

            marketingWidgets.forEach(widget => {
                expect(widget.category).toBe('marketing');
            });
        });

        it('returns widgets for content category', () => {
            const contentWidgets = getWidgetsByCategory('content');

            expect(Array.isArray(contentWidgets)).toBe(true);

            contentWidgets.forEach(widget => {
                expect(widget.category).toBe('content');
            });
        });

        it('returns empty array for nonexistent category', () => {
            const widgets = getWidgetsByCategory('nonexistent');

            expect(Array.isArray(widgets)).toBe(true);
            expect(widgets.length).toBe(0);
        });

        it('all widgets are categorized', () => {
            const allWidgets = getAvailableWidgets();
            const categorizedWidgets = [
                ...getWidgetsByCategory('core'),
                ...getWidgetsByCategory('marketing'),
                ...getWidgetsByCategory('content'),
                ...getWidgetsByCategory('advanced'),
            ];

            expect(categorizedWidgets.length).toBe(allWidgets.length);
        });
    });

    describe('WIDGET_CATEGORIES', () => {
        it('has expected category definitions', () => {
            const categories = WIDGET_CATEGORIES.map(c => c.value);

            expect(categories).toContain('core');
            expect(categories).toContain('marketing');
            expect(categories).toContain('content');
            expect(categories).toContain('advanced');
        });

        it('each category has required properties', () => {
            WIDGET_CATEGORIES.forEach(category => {
                expect(category).toHaveProperty('value');
                expect(category).toHaveProperty('label');
                expect(category).toHaveProperty('color');
            });
        });

        it('category labels are readable strings', () => {
            WIDGET_CATEGORIES.forEach(category => {
                expect(typeof category.label).toBe('string');
                expect(category.label.length).toBeGreaterThan(0);
            });
        });
    });

    describe('Widget schemas validation', () => {
        it('all schemas have valid field types', () => {
            const validTypes = ['text', 'textarea', 'number', 'boolean', 'icon', 'select', 'object', 'array', 'image'];

            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                Object.values(def.schema).forEach(field => {
                    expect(validTypes).toContain(field.type);
                });
            });
        });

        it('array fields have itemSchema', () => {
            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                Object.values(def.schema).forEach(field => {
                    if (field.type === 'array') {
                        expect(field).toHaveProperty('itemSchema');
                    }
                });
            });
        });

        it('object fields have fields property', () => {
            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                Object.values(def.schema).forEach(field => {
                    if (field.type === 'object') {
                        expect(field).toHaveProperty('fields');
                    }
                });
            });
        });

        it('select fields have options', () => {
            Object.values(WIDGET_DEFINITIONS).forEach(def => {
                Object.values(def.schema).forEach(field => {
                    if (field.type === 'select') {
                        expect(field).toHaveProperty('options');
                        expect(Array.isArray(field.options)).toBe(true);
                    }
                });
            });
        });
    });

    describe('Default content validation', () => {
        it('default content matches schema structure', () => {
            Object.entries(WIDGET_DEFINITIONS).forEach(([type, def]) => {
                Object.keys(def.schema).forEach(key => {
                    expect(def.defaultContent).toHaveProperty(key);
                });
            });
        });

        it('hero default buttons are objects', () => {
            const hero = WIDGET_DEFINITIONS[WIDGET_TYPES.HERO];

            expect(typeof hero.defaultContent.primaryButton).toBe('object');
            expect(typeof hero.defaultContent.secondaryButton).toBe('object');
            expect(hero.defaultContent.primaryButton).toHaveProperty('text');
            expect(hero.defaultContent.primaryButton).toHaveProperty('link');
        });

        it('stats default has array of stats', () => {
            const stats = WIDGET_DEFINITIONS[WIDGET_TYPES.STATS];

            expect(Array.isArray(stats.defaultContent.stats)).toBe(true);
            expect(stats.defaultContent.stats.length).toBeGreaterThan(0);
        });
    });
});
