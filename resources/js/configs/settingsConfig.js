/**
 * Settings Configuration
 *
 * The admin settings are grouped into categories, and a category holds
 * the pages somebody would go looking for in the same place. Each entry
 * is either a page living in `pages/admin/settings/<category>/`
 * (`component`) or a screen with a route of its own (`routeName`).
 *
 * Flags an entry may carry:
 *  - `standalone`: the tabs view edits most of a category in one form;
 *    a page that is not part of that form says so, and is linked as a
 *    card beside the form instead of disappearing behind it.
 */

export const settingsCategories = [
    {
        id: 'general',
        title: 'General',
        description: 'Application name, maintenance mode, contact details and which features are on',
        icon: 'mdi-cog-outline',
        color: 'primary',
        settings: [
            { id: 'app-info', title: 'Application Info', description: 'App name, copyright, tagline, URL', icon: 'mdi-information-outline', component: 'AppInfoPage' },
            { id: 'maintenance', title: 'Maintenance Mode', description: 'Enable maintenance mode and message', icon: 'mdi-wrench-outline', component: 'MaintenanceModePage' },
            { id: 'admin-contact', title: 'Admin Contact', description: 'Admin and support email addresses', icon: 'mdi-email-outline', component: 'AdminContactPage' },
            { id: 'public-contact', title: 'Public Contact', description: 'Public contact information', icon: 'mdi-phone-outline', component: 'PublicContactPage' },
            { id: 'social-media', title: 'Social Media', description: 'Social media profile links', icon: 'mdi-share-variant-outline', component: 'SocialMediaPage' },
            { id: 'maps', title: 'Maps', description: 'Which maps are drawn and linked to', icon: 'mdi-map', component: 'MapsPage' },
            { id: 'toggles', title: 'Feature Toggles', description: 'Enable/disable features and hide them from the menu', icon: 'mdi-toggle-switch-outline', component: 'FeatureTogglesPage', standalone: true },
        ]
    },
    {
        id: 'appearance',
        title: 'Appearance',
        description: 'Logos, colours, fonts and background images',
        icon: 'mdi-palette-outline',
        color: 'purple',
        settings: [
            { id: 'logos-icons', title: 'Logos & Icons', description: 'Site logo, favicon, and app icon', icon: 'mdi-image-outline', component: 'LogosIconsPage' },
            { id: 'branding-options', title: 'Branding Options', description: 'Login page branding settings', icon: 'mdi-toggle-switch-outline', component: 'BrandingOptionsPage' },
            { id: 'theme-mode', title: 'Theme Mode', description: 'Default light, dark, or auto theme', icon: 'mdi-brightness-6', component: 'ThemeModePage' },
            { id: 'light-colors', title: 'Light Theme Colors', description: 'Color palette for light mode', icon: 'mdi-white-balance-sunny', component: 'LightColorsPage' },
            { id: 'dark-colors', title: 'Dark Theme Colors', description: 'Color palette for dark mode', icon: 'mdi-moon-waning-crescent', component: 'DarkColorsPage' },
            { id: 'backgrounds', title: 'Background Images', description: 'Background style and images', icon: 'mdi-image-multiple-outline', component: 'BackgroundImagesPage' },
            { id: 'typography', title: 'Typography & CSS', description: 'Fonts and custom styles', icon: 'mdi-format-font', component: 'TypographyPage' },
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
            { id: 'translations', title: 'Translation Manager', description: 'Edit the interface texts of every locale', icon: 'mdi-translate-variant', routeName: 'admin-translations' },
        ]
    },
    {
        id: 'layout',
        title: 'Layout & Navigation',
        description: 'Menus and breadcrumbs, and the grids the homepage and the footer are built from',
        icon: 'mdi-page-layout-body',
        color: 'green',
        settings: [
            { id: 'menus', title: 'Menu Manager', description: 'Create and edit menus, items, and ordering', icon: 'mdi-menu', routeName: 'admin-settings-menus' },
            { id: 'breadcrumbs', title: 'Breadcrumbs', description: 'The trail above a page title, and where it starts', icon: 'mdi-chevron-right', component: 'BreadcrumbsPage', standalone: true },
            { id: 'homepage-sections', title: 'Homepage Sections', description: 'The sections and grid of the homepage', icon: 'mdi-view-grid-outline', component: 'SectionsPage' },
            { id: 'homepage-widgets', title: 'Homepage Widgets', description: 'Configure homepage widgets', icon: 'mdi-widgets-outline', component: 'WidgetsPage' },
            { id: 'homepage-menu', title: 'Homepage Menu', description: 'Homepage navigation links', icon: 'mdi-menu-open', component: 'MenuPage' },
            { id: 'footer-sections', title: 'Footer Sections', description: 'The sections and grid of the footer', icon: 'mdi-view-grid-plus-outline', component: 'FooterSectionsPage' },
            { id: 'footer-widgets', title: 'Footer Widgets', description: 'Configure footer widgets', icon: 'mdi-widgets', component: 'FooterWidgetsPage' },
            { id: 'footer-html', title: 'Footer HTML', description: 'Custom footer HTML content', icon: 'mdi-code-tags', component: 'CustomHtmlPage' },
        ]
    },
    {
        id: 'community',
        title: 'Community & Content',
        description: 'Forum sections, the vocabularies content is filed under, what members can earn, and the tours that show them around',
        icon: 'mdi-account-group-outline',
        color: 'amber',
        settings: [
            { id: 'forum-categories', title: 'Forum Categories', description: 'Create, order, lock and hide the forum categories', icon: 'mdi-forum', routeName: 'admin-forums' },
            { id: 'taxonomies', title: 'Taxonomies', description: 'Vocabularies such as tags, categories and forum sections', icon: 'mdi-file-tree', routeName: 'admin-taxonomie' },
            { id: 'terms', title: 'Terms', description: 'The entries of each taxonomy', icon: 'mdi-tag-multiple', routeName: 'admin-terms' },
            { id: 'achievements', title: 'Achievements', description: 'Set up achievements and award them', icon: 'mdi-trophy-outline', component: 'AchievementsSettingsPage' },
            { id: 'ranks', title: 'Ranks', description: 'The ladder members climb with their points', icon: 'mdi-shield-star-outline', component: 'RanksSettingsPage' },
            { id: 'tours', title: 'Tours & Hints', description: 'Walk members through a page, or put a hint beside the things on it', icon: 'mdi-map-marker-path', component: 'HelpToursPage' },
        ]
    },
    {
        id: 'access',
        title: 'Users & Access',
        description: 'Roles, the permissions they grant, and what they may post unchecked',
        icon: 'mdi-shield-account-outline',
        color: 'red',
        settings: [
            { id: 'roles', title: 'Roles', description: 'Create roles and assign them to users', icon: 'mdi-shield-account', routeName: 'admin-roles' },
            { id: 'permissions', title: 'Permissions', description: 'Which role may do what', icon: 'mdi-shield-key-outline', routeName: 'admin-permissions' },
            { id: 'auto-approval', title: 'Content Auto-Approval', description: 'Which roles may post without waiting for approval', icon: 'mdi-shield-check', component: 'AutoApprovalPage' },
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
        id: 'irc',
        title: 'IRC',
        description: 'IRC servers, daemon, and connection management',
        icon: 'mdi-chat-processing-outline',
        color: 'indigo',
        settings: [
            { id: 'client', title: 'Client Settings', description: 'Comic chat and client options', icon: 'mdi-tune', component: 'IrcClientSettingsPage' },
            { id: 'event-channels', title: 'Event Channels', description: 'Channels suggested when an event is held on IRC', icon: 'mdi-pound', component: 'IrcEventChannelsPage' },
            { id: 'characters', title: 'Character Creator', description: 'Build the comic chat characters members pick from', icon: 'mdi-account-edit-outline', component: 'IrcCharactersPage' },
            { id: 'dashboard', title: 'Dashboard', description: 'Daemon status, stats, and monitoring', icon: 'mdi-monitor-dashboard', component: 'IrcDashboardPage' },
            { id: 'servers', title: 'Servers', description: 'Manage IRC servers', icon: 'mdi-server-network', component: 'IrcServersPage' },
            { id: 'connections', title: 'Connections', description: 'Active user connections', icon: 'mdi-connection', component: 'IrcConnectionsPage' },
        ]
    },
    {
        id: 'sandbox',
        title: 'Sandbox',
        description: 'Collaborative sandbox and Yjs WebSocket settings',
        icon: 'mdi-notebook-edit-outline',
        color: 'deep-purple',
        settings: [
            { id: 'server-status', title: 'WebSocket Server', description: 'Yjs server status and connection info', icon: 'mdi-server-network', component: 'ServerStatusPage' },
            { id: 'feature-settings', title: 'Feature Settings', description: 'Enable/disable sandbox and collaboration', icon: 'mdi-toggle-switch-outline', component: 'FeatureSettingsPage' },
            { id: 'limits', title: 'Limits & Quotas', description: 'Max sandboxes, collaborators, versions', icon: 'mdi-numeric', component: 'LimitsPage' },
            { id: 'statistics', title: 'Statistics', description: 'Sandbox usage and activity stats', icon: 'mdi-chart-bar', component: 'StatisticsPage' },
        ]
    },
    {
        id: 'integrations',
        title: 'Integrations & Tools',
        description: 'Announce what happens on the site elsewhere, and bring content in from somewhere else',
        icon: 'mdi-transit-connection-variant',
        color: 'blue-grey',
        settings: [
            { id: 'discord-webhooks', title: 'Discord Webhooks', description: 'Post new wiki pages, tickets and more into Discord channels', icon: 'mdi-discord', routeName: 'admin-settings-discord' },
            { id: 'migrations', title: 'Migration Tool', description: 'Import content and users from legacy databases', icon: 'mdi-database-arrow-right-outline', routeName: 'admin-migrations' },
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
 * Where everything that used to live somewhere else went, written as
 * `old-category/old-setting` to `new-category/new-setting`.
 *
 * The categories were regrouped once the thin ones outnumbered the full
 * ones, which moved pages between them and renamed a few. A link
 * written down before that - a bookmark, a mail, a note in a ticket -
 * is sent on rather than met with "not found".
 */
export const movedSettings = {
    'features/toggles': 'general/toggles',
    'branding/logos-icons': 'appearance/logos-icons',
    'branding/options': 'appearance/branding-options',
    'theme/mode': 'appearance/theme-mode',
    'theme/light-colors': 'appearance/light-colors',
    'theme/dark-colors': 'appearance/dark-colors',
    'theme/backgrounds': 'appearance/backgrounds',
    'theme/typography': 'appearance/typography',
    'menus/menus': 'layout/menus',
    'menus/breadcrumbs': 'layout/breadcrumbs',
    'homepage/sections': 'layout/homepage-sections',
    'homepage/widgets': 'layout/homepage-widgets',
    'homepage/menu': 'layout/homepage-menu',
    'footer/sections': 'layout/footer-sections',
    'footer/widgets': 'layout/footer-widgets',
    'footer/custom-html': 'layout/footer-html',
    'forum/categories': 'community/forum-categories',
    'taxonomy/taxonomies': 'community/taxonomies',
    'taxonomy/terms': 'community/terms',
    'achievements/list': 'community/achievements',
    'achievements/ranks': 'community/ranks',
    'help/tours': 'community/tours',
    'moderation/auto-approval': 'access/auto-approval',
    'tools/migrations': 'integrations/migrations',
};

/**
 * Which category took over each of the ones that were dissolved, read
 * off the moves above so the two cannot drift apart.
 *
 * Without a prototype, because the key comes straight out of the URL
 * and `/admin/settings/constructor` should find nothing here.
 */
export const movedCategories = Object.entries(movedSettings).reduce((map, [from, to]) => {
    map[from.split('/')[0]] = to.split('/')[0];
    return map;
}, Object.create(null));

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
 * Where a setting that has moved lives now
 * @param {string} categorySlug - The category it was asked for under
 * @param {string} settingSlug - The setting it was asked for under
 * @returns {{category: string, setting: string}|null} Its home now, or null if it never moved
 */
export function resolveMovedSetting(categorySlug, settingSlug) {
    const moved = movedSettings[`${categorySlug}/${settingSlug}`];
    if (!moved) return null;

    const [category, setting] = moved.split('/');
    return { category, setting };
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
