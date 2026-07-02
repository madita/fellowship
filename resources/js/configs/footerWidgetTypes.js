/**
 * Footer Widget Categories
 */
export const FOOTER_WIDGET_CATEGORIES = [
    { value: 'navigation', label: 'Navigation' },
    { value: 'content', label: 'Content' },
    { value: 'social', label: 'Social & Contact' },
];

/**
 * Footer Widget Type Definitions
 */
const footerWidgetDefinitions = {
    quicklinks: {
        type: 'quicklinks',
        name: 'Quick Links',
        description: 'Display a list of quick navigation links',
        icon: 'mdi-link-variant',
        color: 'blue',
        category: 'navigation',
        defaultContent: {},
        defaultConfig: {
            title: 'Quick Links',
            style: 'simple',
            links: [
                { label: 'Home', url: '/', external: false, authOnly: false },
                { label: 'About', url: '/about', external: false, authOnly: false },
            ],
        },
    },
    menu: {
        type: 'menu',
        name: 'Menu',
        description: 'Render a menu managed in Settings → Navigation',
        icon: 'mdi-menu',
        color: 'indigo',
        category: 'navigation',
        defaultContent: {},
        defaultConfig: {
            title: '',
            menu_slug: '',
            layout: 'list',
            style: 'simple',
        },
    },
    contact: {
        type: 'contact',
        name: 'Contact Information',
        description: 'Display contact details (address, phone, email)',
        icon: 'mdi-card-account-mail',
        color: 'green',
        category: 'social',
        defaultContent: {},
        defaultConfig: {
            title: 'Contact Us',
            showAddress: true,
            showPhone: true,
            showEmail: true,
        },
    },
    newsletter: {
        type: 'newsletter',
        name: 'Newsletter Signup',
        description: 'Newsletter subscription form',
        icon: 'mdi-email-newsletter',
        color: 'orange',
        category: 'content',
        defaultContent: {},
        defaultConfig: {
            title: 'Subscribe to Newsletter',
            description: 'Stay updated with our latest news and updates',
            buttonText: 'Subscribe',
        },
    },
    social: {
        type: 'social',
        name: 'Social Media Links',
        description: 'Social media links with icons',
        icon: 'mdi-share-variant',
        color: 'purple',
        category: 'social',
        defaultContent: {},
        defaultConfig: {
            title: 'Follow Us',
            showTwitter: true,
            showFacebook: true,
            showInstagram: true,
            showLinkedin: true,
            showYoutube: true,
            showDiscord: true,
        },
    },
    text: {
        type: 'text',
        name: 'Text Block',
        description: 'Custom text or HTML content',
        icon: 'mdi-text-box',
        color: 'grey',
        category: 'content',
        defaultContent: {},
        defaultConfig: {
            title: 'About',
            content: 'Add your custom text content here...',
        },
    },
};

/**
 * Legacy export for backward compatibility
 */
export const footerWidgetTypes = Object.values(footerWidgetDefinitions).map(def => ({
    type: def.type,
    label: def.name,
    icon: def.icon,
    description: def.description,
    defaultConfig: def.defaultConfig,
}));

/**
 * Get footer widget definition by type (matches widgetTypes.js API)
 */
export function getWidgetDefinition(type) {
    return footerWidgetDefinitions[type] || null;
}

/**
 * Get all available footer widgets (matches widgetTypes.js API)
 */
export function getAvailableWidgets() {
    return Object.values(footerWidgetDefinitions);
}

/**
 * Get footer widget info (legacy function)
 */
export function getWidgetTypeInfo(type) {
    const definition = getWidgetDefinition(type);
    if (!definition) return null;

    return {
        type: definition.type,
        label: definition.name,
        icon: definition.icon,
        description: definition.description,
        defaultConfig: definition.defaultConfig,
    };
}
