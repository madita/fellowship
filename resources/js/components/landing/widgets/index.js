import { defineAsyncComponent, markRaw } from 'vue';

// Import widget components
const HeroWidget = defineAsyncComponent(() => import('./HeroWidget.vue'));
const PartnersWidget = defineAsyncComponent(() => import('./PartnersWidget.vue'));
const StatsWidget = defineAsyncComponent(() => import('./StatsWidget.vue'));
const FeatureGridWidget = defineAsyncComponent(() => import('./FeatureGridWidget.vue'));
const FeatureShowcaseWidget = defineAsyncComponent(() => import('./FeatureShowcaseWidget.vue'));
const CtaWidget = defineAsyncComponent(() => import('./CtaWidget.vue'));
const TestimonialsWidget = defineAsyncComponent(() => import('./TestimonialsWidget.vue'));
const PricingWidget = defineAsyncComponent(() => import('./PricingWidget.vue'));
const FaqWidget = defineAsyncComponent(() => import('./FaqWidget.vue'));
const GalleryWidget = defineAsyncComponent(() => import('./GalleryWidget.vue'));
const BlogPostsWidget = defineAsyncComponent(() => import('./BlogPostsWidget.vue'));
const TeamMembersWidget = defineAsyncComponent(() => import('./TeamMembersWidget.vue'));
const ContactFormWidget = defineAsyncComponent(() => import('./ContactFormWidget.vue'));
const VideoWidget = defineAsyncComponent(() => import('./VideoWidget.vue'));
const NewsletterWidget = defineAsyncComponent(() => import('./NewsletterWidget.vue'));
const TimelineWidget = defineAsyncComponent(() => import('./TimelineWidget.vue'));
const ClientsWidget = defineAsyncComponent(() => import('./ClientsWidget.vue'));
const CustomHtmlWidget = defineAsyncComponent(() => import('./CustomHtmlWidget.vue'));

// Widget component mapping
const widgetComponents = {
    hero: HeroWidget,
    partners: PartnersWidget,
    stats: StatsWidget,
    feature_grid: FeatureGridWidget,
    feature_showcase: FeatureShowcaseWidget,
    cta: CtaWidget,
    testimonials: TestimonialsWidget,
    pricing: PricingWidget,
    faq: FaqWidget,
    gallery: GalleryWidget,
    blog_posts: BlogPostsWidget,
    team_members: TeamMembersWidget,
    contact_form: ContactFormWidget,
    video: VideoWidget,
    newsletter: NewsletterWidget,
    timeline: TimelineWidget,
    clients: ClientsWidget,
    custom_html: CustomHtmlWidget,
};

/**
 * Get Vue component for a widget type
 * @param {string} type - Widget type identifier
 * @returns {Component|null} Vue component or null if not found
 */
export function getWidgetComponent(type) {
    const component = widgetComponents[type];
    if (component) {
        return markRaw(component);
    }
    console.warn(`Unknown widget type: ${type}`);
    return null;
}

export default widgetComponents;
