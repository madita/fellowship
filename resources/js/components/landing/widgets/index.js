// Import existing refactored components
import StatsWidget from '../Stats.vue';
import PartnersWidget from '../Partners.vue';
import FeatureGridWidget from '../Feature2.vue'; // Feature2 is the grid
import FeatureShowcaseWidget from '../Feature1.vue'; // Feature1 is the showcase
import CtaWidget from '../CallToAction.vue';

// Import new widgets
import HeroWidget from './HeroWidget.vue';
import BlogPostsWidget from './BlogPostsWidget.vue';
import TeamMembersWidget from './TeamMembersWidget.vue';
import ContactFormWidget from './ContactFormWidget.vue';
import VideoWidget from './VideoWidget.vue';
import NewsletterWidget from './NewsletterWidget.vue';
import TimelineWidget from './TimelineWidget.vue';
import ClientsWidget from './ClientsWidget.vue';
import CustomHtmlWidget from './CustomHtmlWidget.vue';

// These will be created later
// import TestimonialsWidget from './TestimonialsWidget.vue';
// import PricingWidget from './PricingWidget.vue';
// import FaqWidget from './FaqWidget.vue';
// import GalleryWidget from './GalleryWidget.vue';

/**
 * Widget component registry
 * Maps widget type strings to Vue components
 */
export const WIDGET_COMPONENTS = {
    hero: HeroWidget,
    stats: StatsWidget,
    partners: PartnersWidget,
    feature_grid: FeatureGridWidget,
    feature_showcase: FeatureShowcaseWidget,
    cta: CtaWidget,
    blog_posts: BlogPostsWidget,
    team_members: TeamMembersWidget,
    contact_form: ContactFormWidget,
    video: VideoWidget,
    newsletter: NewsletterWidget,
    timeline: TimelineWidget,
    clients: ClientsWidget,
    custom_html: CustomHtmlWidget,
    // testimonials: TestimonialsWidget,
    // pricing: PricingWidget,
    // faq: FaqWidget,
    // gallery: GalleryWidget,
};

/**
 * Get widget component by type
 * @param {string} type - Widget type
 * @returns {Component|null} Vue component or null if not found
 */
export function getWidgetComponent(type) {
    return WIDGET_COMPONENTS[type] || null;
}

/**
 * Check if a widget type is registered
 * @param {string} type - Widget type
 * @returns {boolean}
 */
export function hasWidgetComponent(type) {
    return type in WIDGET_COMPONENTS;
}

/**
 * Get all registered widget types
 * @returns {string[]}
 */
export function getRegisteredWidgetTypes() {
    return Object.keys(WIDGET_COMPONENTS);
}
