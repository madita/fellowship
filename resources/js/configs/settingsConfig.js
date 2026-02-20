/**
 * Settings Configuration
 * Central configuration for admin settings categories and individual settings
 */

export const settingsCategories = [
    {
        id: 'general',
        title: 'General',
        description: 'Application name, maintenance mode, and contact information',
        icon: 'mdi-cog-outline',
        color: 'primary',
        settings: [
            { id: 'app-info', title: 'Application Info', description: 'App name, copyright, tagline, URL', icon: 'mdi-information-outline', component: 'AppInfoPage' },
            { id: 'maintenance', title: 'Maintenance Mode', description: 'Enable maintenance mode and message', icon: 'mdi-wrench-outline', component: 'MaintenanceModePage' },
            { id: 'admin-contact', title: 'Admin Contact', description: 'Admin and support email addresses', icon: 'mdi-email-outline', component: 'AdminContactPage' },
            { id: 'public-contact', title: 'Public Contact', description: 'Public contact information', icon: 'mdi-phone-outline', component: 'PublicContactPage' },
            { id: 'social-media', title: 'Social Media', description: 'Social media profile links', icon: 'mdi-share-variant-outline', component: 'SocialMediaPage' },
        ]
    },
    {
        id: 'localization',
        title: 'Localization',
        description: 'Language, timezone, and regional settings',
        icon: 'mdi-earth',
        color: 'blue',
        settings: [
            { id: 'regional', title: 'Regional Settings', description: 'Language, timezone, date/time formats', icon: 'mdi-map-clock-outline', component: 'RegionalSettingsPage' },
            { id: 'language-options', title: 'Language Options', description: 'User language preferences', icon: 'mdi-translate', component: 'LanguageOptionsPage' },
        ]
    },
    {
        id: 'branding',
        title: 'Branding',
        description: 'Logos, icons, and visual identity',
        icon: 'mdi-palette-outline',
        color: 'orange',
        settings: [
            { id: 'logos-icons', title: 'Logos & Icons', description: 'Site logo, favicon, and app icon', icon: 'mdi-image-outline', component: 'LogosIconsPage' },
            { id: 'options', title: 'Branding Options', description: 'Login page branding settings', icon: 'mdi-toggle-switch-outline', component: 'BrandingOptionsPage' },
        ]
    },
    {
        id: 'theme',
        title: 'Theme',
        description: 'Colors, typography, and appearance',
        icon: 'mdi-theme-light-dark',
        color: 'purple',
        settings: [
            { id: 'mode', title: 'Theme Mode', description: 'Default light, dark, or auto theme', icon: 'mdi-brightness-6', component: 'ThemeModePage' },
            { id: 'light-colors', title: 'Light Theme Colors', description: 'Color palette for light mode', icon: 'mdi-white-balance-sunny', component: 'LightColorsPage' },
            { id: 'dark-colors', title: 'Dark Theme Colors', description: 'Color palette for dark mode', icon: 'mdi-moon-waning-crescent', component: 'DarkColorsPage' },
            { id: 'backgrounds', title: 'Background Images', description: 'Background style and images', icon: 'mdi-image-multiple-outline', component: 'BackgroundImagesPage' },
            { id: 'typography', title: 'Typography & CSS', description: 'Fonts and custom styles', icon: 'mdi-format-font', component: 'TypographyPage' },
        ]
    },
    {
        id: 'oauth',
        title: 'Social Login',
        description: 'OAuth providers and authentication',
        icon: 'mdi-login-variant',
        color: 'pink',
        settings: [
            { id: 'google', title: 'Google OAuth', description: 'Sign in with Google', icon: 'mdi-google', component: 'GoogleOAuthPage' },
            { id: 'discord', title: 'Discord OAuth', description: 'Sign in with Discord', icon: 'mdi-discord', component: 'DiscordOAuthPage' },
            { id: 'github', title: 'GitHub OAuth', description: 'Sign in with GitHub', icon: 'mdi-github', component: 'GitHubOAuthPage' },
            { id: 'facebook', title: 'Facebook OAuth', description: 'Sign in with Facebook', icon: 'mdi-facebook', component: 'FacebookOAuthPage' },
            { id: 'general', title: 'OAuth Settings', description: 'Registration and email verification', icon: 'mdi-account-cog-outline', component: 'OAuthGeneralPage' },
        ]
    },
    {
        id: 'seo',
        title: 'SEO',
        description: 'Search engine optimization settings',
        icon: 'mdi-search-web',
        color: 'teal',
        settings: [
            { id: 'metadata', title: 'Basic Metadata', description: 'Title, description, and keywords', icon: 'mdi-tag-text-outline', component: 'MetadataPage' },
            { id: 'open-graph', title: 'Open Graph', description: 'Social media sharing preview', icon: 'mdi-share-outline', component: 'OpenGraphPage' },
            { id: 'twitter-card', title: 'Twitter Card', description: 'Twitter/X sharing settings', icon: 'mdi-twitter', component: 'TwitterCardPage' },
            { id: 'search-engine', title: 'Search Engines', description: 'Indexing, sitemap, robots.txt', icon: 'mdi-robot-outline', component: 'SearchEnginePage' },
        ]
    },
    {
        id: 'homepage',
        title: 'Homepage',
        description: 'Homepage sections, widgets, and menu',
        icon: 'mdi-home-edit-outline',
        color: 'green',
        settings: [
            { id: 'sections', title: 'Sections & Grid', description: 'Manage homepage sections', icon: 'mdi-view-grid-outline', component: 'SectionsPage' },
            { id: 'widgets', title: 'Widgets', description: 'Configure homepage widgets', icon: 'mdi-widgets-outline', component: 'WidgetsPage' },
            { id: 'menu', title: 'Navigation Menu', description: 'Homepage navigation links', icon: 'mdi-menu', component: 'MenuPage' },
        ]
    },
    {
        id: 'footer',
        title: 'Footer',
        description: 'Footer sections, widgets, and content',
        icon: 'mdi-page-layout-footer',
        color: 'cyan',
        settings: [
            { id: 'sections', title: 'Sections & Grid', description: 'Manage footer sections', icon: 'mdi-view-grid-outline', component: 'FooterSectionsPage' },
            { id: 'widgets', title: 'Widgets', description: 'Configure footer widgets', icon: 'mdi-widgets-outline', component: 'FooterWidgetsPage' },
            { id: 'custom-html', title: 'Custom HTML', description: 'Custom footer HTML content', icon: 'mdi-code-tags', component: 'CustomHtmlPage' },
        ]
    },
    {
        id: 'moderation',
        title: 'Moderation',
        description: 'Content approval and moderation settings',
        icon: 'mdi-shield-check-outline',
        color: 'deep-purple',
        settings: [
            { id: 'auto-approval', title: 'Content Auto-Approval', description: 'Configure role-based automatic content approval', icon: 'mdi-shield-check', component: 'AutoApprovalPage' },
        ]
    },
    {
        id: 'advanced',
        title: 'Advanced',
        description: 'Caching, PWA, API, and developer settings',
        icon: 'mdi-cog-sync-outline',
        color: 'grey',
        settings: [
            { id: 'caching', title: 'Performance & Caching', description: 'Cache settings and management', icon: 'mdi-cached', component: 'CachingPage' },
            { id: 'pwa', title: 'Progressive Web App', description: 'PWA configuration and status', icon: 'mdi-cellphone-link', component: 'PwaPage' },
            { id: 'newsletter', title: 'Newsletter', description: 'Newsletter provider integration', icon: 'mdi-email-newsletter', component: 'NewsletterPage' },
            { id: 'api', title: 'API & Developer', description: 'Environment, debug, rate limits', icon: 'mdi-api', component: 'ApiSettingsPage' },
            { id: 'cookies', title: 'Cookie Consent', description: 'GDPR cookie banner settings', icon: 'mdi-cookie-outline', component: 'CookieConsentPage' },
            { id: 'scripts', title: 'Custom Scripts', description: 'Head and body scripts', icon: 'mdi-script-text-outline', component: 'CustomScriptsPage' },
            { id: 'legal', title: 'Legal & Compliance', description: 'Privacy, terms, and GDPR', icon: 'mdi-scale-balance', component: 'LegalCompliancePage' },
        ]
    },
];

/**
 * Get a category by its slug/id
 * @param {string} slug - The category ID
 * @returns {Object|undefined} The category object or undefined
 */
export function getCategoryBySlug(slug) {
    return settingsCategories.find(c => c.id === slug);
}

/**
 * Get a setting by category and setting slugs
 * @param {string} categorySlug - The category ID
 * @param {string} settingSlug - The setting ID
 * @returns {Object|undefined} The setting object or undefined
 */
export function getSettingBySlug(categorySlug, settingSlug) {
    const category = getCategoryBySlug(categorySlug);
    return category?.settings.find(s => s.id === settingSlug);
}

/**
 * Get total count of settings across all categories
 * @returns {number} Total number of settings
 */
export function getTotalSettingsCount() {
    return settingsCategories.reduce((total, cat) => total + cat.settings.length, 0);
}

/**
 * Get the component path for a setting
 * @param {string} categorySlug - The category ID
 * @param {string} settingSlug - The setting ID
 * @returns {string|null} The component path or null
 */
export function getSettingComponentPath(categorySlug, settingSlug) {
    const setting = getSettingBySlug(categorySlug, settingSlug);
    if (!setting) return null;
    return `@/pages/admin/settings/${categorySlug}/${setting.component}.vue`;
}

export default settingsCategories;
