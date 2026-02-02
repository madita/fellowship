export const WIDGET_TYPES = {
    HERO: 'hero',
    PARTNERS: 'partners',
    STATS: 'stats',
    FEATURE_GRID: 'feature_grid',
    FEATURE_SHOWCASE: 'feature_showcase',
    CTA: 'cta',
    TESTIMONIALS: 'testimonials',
    PRICING: 'pricing',
    FAQ: 'faq',
    GALLERY: 'gallery',
    BLOG_POSTS: 'blog_posts',
    TEAM_MEMBERS: 'team_members',
    CONTACT_FORM: 'contact_form',
    VIDEO: 'video',
    NEWSLETTER: 'newsletter',
    TIMELINE: 'timeline',
    CLIENTS: 'clients',
    CUSTOM_HTML: 'custom_html',
};

export const WIDGET_DEFINITIONS = {
    [WIDGET_TYPES.HERO]: {
        name: 'Hero Section',
        description: 'Large header with title, subtitle, and CTA buttons',
        icon: 'mdi-format-header-1',
        category: 'core',
        color: 'primary',
        defaultContent: {
            title: 'Your awesome community',
            subtitle: 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Commodi ex facilis ad atque natus tenetur debitis qui quisquam iure amet.',
            primaryButton: { text: 'Join Now!', link: '/auth/signup' },
            secondaryButton: { text: 'Learn more', link: '#' },
        },
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
    },
    [WIDGET_TYPES.PARTNERS]: {
        name: 'Partners',
        description: 'Partners or sponsors content (SVG decoration is now section-level)',
        icon: 'mdi-handshake',
        category: 'core',
        color: 'secondary',
        defaultContent: {
            showContent: false,
            message: '',
        },
        schema: {
            showContent: { type: 'boolean', label: 'Show Content' },
            message: { type: 'text', label: 'Message (optional)' },
        },
    },
    [WIDGET_TYPES.STATS]: {
        name: 'Statistics',
        description: 'Display key metrics and numbers',
        icon: 'mdi-chart-bar',
        category: 'core',
        color: 'success',
        defaultContent: {
            stats: [
                { title: 'Projects', value: '4,253', description: 'Lorem ipsum dolor sit amet' },
                { title: 'API Requests', value: '1,283,787', description: 'Lorem ipsum dolor sit amet' },
                { title: 'Subscribers', value: '1,348', description: 'Lorem ipsum dolor sit amet' },
                { title: 'Businesses', value: '331,234', description: 'Lorem ipsum dolor sit amet' },
            ],
        },
        schema: {
            stats: {
                type: 'array',
                label: 'Statistics',
                itemSchema: {
                    title: { type: 'text', label: 'Title', required: true },
                    value: { type: 'text', label: 'Value', required: true },
                    description: { type: 'textarea', label: 'Description' },
                },
            },
        },
    },
    [WIDGET_TYPES.FEATURE_GRID]: {
        name: 'Feature Grid',
        description: 'Grid of features with icons',
        icon: 'mdi-view-grid',
        category: 'core',
        color: 'info',
        defaultContent: {
            features: [
                { icon: 'mdi-account-check-outline', title: 'Account Verification', description: 'Lorem ipsum dolor sit amet' },
                { icon: 'mdi-lifebuoy', title: 'Dedicated Support', description: 'Lorem ipsum dolor sit amet' },
                { icon: 'mdi-email-outline', title: 'Email Integration', description: 'Lorem ipsum dolor sit amet' },
                { icon: 'mdi-clock-outline', title: 'Save Time', description: 'Lorem ipsum dolor sit amet' },
            ],
        },
        schema: {
            features: {
                type: 'array',
                label: 'Features',
                itemSchema: {
                    icon: { type: 'icon', label: 'Icon' },
                    title: { type: 'text', label: 'Title', required: true },
                    description: { type: 'textarea', label: 'Description' },
                },
            },
        },
    },
    [WIDGET_TYPES.FEATURE_SHOWCASE]: {
        name: 'Feature Showcase',
        description: 'Featured content with image and text',
        icon: 'mdi-star',
        category: 'core',
        color: 'warning',
        defaultContent: {
            subtitle: 'Work with us',
            title: 'Get your startup ready for business',
            description: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
            image: null,
            imagePosition: 'right',
            features: [],
        },
        schema: {
            subtitle: { type: 'text', label: 'Subtitle (small text above title)' },
            title: { type: 'text', label: 'Title', required: true },
            description: { type: 'textarea', label: 'Description' },
            image: { type: 'image', label: 'Image' },
            imagePosition: {
                type: 'select',
                label: 'Image Position',
                options: [
                    { value: 'right', title: 'Image on Right (Text Left)' },
                    { value: 'left', title: 'Image on Left (Text Right)' },
                ],
            },
        },
    },
    [WIDGET_TYPES.CTA]: {
        name: 'Call to Action',
        description: 'Call to action section with button',
        icon: 'mdi-bullhorn',
        category: 'core',
        color: 'error',
        defaultContent: {
            title: 'Ready to talk? Our team is here to help',
            description: 'Get in touch with us today.',
            buttonText: 'Contact Us',
            buttonLink: '/contact',
        },
        schema: {
            title: { type: 'text', label: 'Title', required: true },
            description: { type: 'textarea', label: 'Description' },
            buttonText: { type: 'text', label: 'Button Text' },
            buttonLink: { type: 'text', label: 'Button Link' },
        },
    },
    [WIDGET_TYPES.TESTIMONIALS]: {
        name: 'Testimonials',
        description: 'Customer testimonials and reviews',
        icon: 'mdi-comment-quote',
        category: 'marketing',
        color: 'purple',
        defaultContent: {
            testimonials: [
                {
                    quote: 'This is an amazing platform! It has transformed how we work.',
                    author: 'John Doe',
                    role: 'CEO, Company Inc',
                    avatar: null,
                    rating: 5,
                },
                {
                    quote: 'Outstanding service and support. Highly recommended!',
                    author: 'Jane Smith',
                    role: 'CTO, Tech Corp',
                    avatar: null,
                    rating: 5,
                },
            ],
        },
        schema: {
            testimonials: {
                type: 'array',
                label: 'Testimonials',
                itemSchema: {
                    quote: { type: 'textarea', label: 'Quote', required: true },
                    author: { type: 'text', label: 'Author Name', required: true },
                    role: { type: 'text', label: 'Role/Company' },
                    avatar: { type: 'image', label: 'Avatar Image' },
                    rating: { type: 'number', label: 'Rating (1-5)', min: 1, max: 5 },
                },
            },
        },
    },
    [WIDGET_TYPES.PRICING]: {
        name: 'Pricing Tables',
        description: 'Pricing plans and packages',
        icon: 'mdi-currency-usd',
        category: 'marketing',
        color: 'orange',
        defaultContent: {
            plans: [
                {
                    name: 'Basic',
                    price: '9.99',
                    period: 'month',
                    features: ['1 User', '10 Projects', '5GB Storage', 'Email Support'],
                    highlighted: false,
                    buttonText: 'Get Started',
                    buttonLink: '/signup',
                },
                {
                    name: 'Pro',
                    price: '29.99',
                    period: 'month',
                    features: ['10 Users', 'Unlimited Projects', '100GB Storage', 'Priority Support', 'Advanced Features'],
                    highlighted: true,
                    buttonText: 'Get Started',
                    buttonLink: '/signup',
                },
                {
                    name: 'Enterprise',
                    price: '99.99',
                    period: 'month',
                    features: ['Unlimited Users', 'Unlimited Projects', '1TB Storage', '24/7 Support', 'Custom Solutions'],
                    highlighted: false,
                    buttonText: 'Contact Sales',
                    buttonLink: '/contact',
                },
            ],
        },
        schema: {
            plans: {
                type: 'array',
                label: 'Pricing Plans',
                itemSchema: {
                    name: { type: 'text', label: 'Plan Name', required: true },
                    price: { type: 'text', label: 'Price' },
                    period: { type: 'select', label: 'Billing Period', options: ['month', 'year'] },
                    features: { type: 'array', label: 'Features', itemType: 'text' },
                    highlighted: { type: 'boolean', label: 'Highlight This Plan' },
                    buttonText: { type: 'text', label: 'Button Text' },
                    buttonLink: { type: 'text', label: 'Button Link' },
                },
            },
        },
    },
    [WIDGET_TYPES.FAQ]: {
        name: 'FAQ',
        description: 'Frequently asked questions accordion',
        icon: 'mdi-help-circle',
        category: 'content',
        color: 'teal',
        defaultContent: {
            title: 'Frequently Asked Questions',
            faqs: [
                {
                    question: 'How do I get started?',
                    answer: 'Getting started is easy! Simply sign up for an account and follow our onboarding guide.',
                },
                {
                    question: 'What payment methods do you accept?',
                    answer: 'We accept all major credit cards, PayPal, and bank transfers.',
                },
                {
                    question: 'Can I cancel my subscription?',
                    answer: 'Yes, you can cancel your subscription at any time from your account settings.',
                },
            ],
        },
        schema: {
            title: { type: 'text', label: 'Section Title' },
            faqs: {
                type: 'array',
                label: 'Questions',
                itemSchema: {
                    question: { type: 'text', label: 'Question', required: true },
                    answer: { type: 'textarea', label: 'Answer', required: true },
                },
            },
        },
    },
    [WIDGET_TYPES.GALLERY]: {
        name: 'Image Gallery',
        description: 'Image gallery with lightbox',
        icon: 'mdi-view-gallery',
        category: 'content',
        color: 'indigo',
        defaultContent: {
            title: 'Our Gallery',
            images: [
                { url: null, caption: 'Image 1' },
                { url: null, caption: 'Image 2' },
                { url: null, caption: 'Image 3' },
            ],
        },
        schema: {
            title: { type: 'text', label: 'Gallery Title' },
            images: {
                type: 'array',
                label: 'Images',
                itemSchema: {
                    url: { type: 'image', label: 'Image', required: true },
                    caption: { type: 'text', label: 'Caption' },
                },
            },
        },
    },
    [WIDGET_TYPES.BLOG_POSTS]: {
        name: 'Blog Posts',
        description: 'Display latest blog posts in a grid',
        icon: 'mdi-post',
        category: 'content',
        color: 'blue',
        defaultContent: {
            title: 'Latest from Our Blog',
            subtitle: 'Stay updated with our latest news and articles',
            posts: [
                {
                    title: 'Getting Started with Our Platform',
                    excerpt: 'Learn how to make the most of our platform with this comprehensive guide for beginners.',
                    image: null,
                    category: 'Tutorial',
                    author: 'Jane Smith',
                    date: '2024-01-15',
                    link: '/blog/getting-started',
                },
                {
                    title: '10 Tips for Better Productivity',
                    excerpt: 'Discover proven strategies to boost your productivity and achieve more in less time.',
                    image: null,
                    category: 'Tips',
                    author: 'John Doe',
                    date: '2024-01-10',
                    link: '/blog/productivity-tips',
                },
                {
                    title: 'New Feature Release: Advanced Analytics',
                    excerpt: 'We are excited to announce our new advanced analytics dashboard with powerful insights.',
                    image: null,
                    category: 'Product Updates',
                    author: 'Sarah Johnson',
                    date: '2024-01-05',
                    link: '/blog/new-analytics',
                },
            ],
            postsToShow: 3,
            postsPerRow: 3,
            showViewAllButton: true,
            viewAllLink: '/blog',
        },
        schema: {
            title: { type: 'text', label: 'Section Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            posts: {
                type: 'array',
                label: 'Blog Posts',
                itemSchema: {
                    title: { type: 'text', label: 'Post Title', required: true },
                    excerpt: { type: 'textarea', label: 'Excerpt', required: true },
                    image: { type: 'text', label: 'Image URL' },
                    category: { type: 'text', label: 'Category' },
                    author: { type: 'text', label: 'Author' },
                    date: { type: 'text', label: 'Date' },
                    link: { type: 'text', label: 'Link', required: true },
                },
            },
            postsToShow: { type: 'number', label: 'Number of Posts to Show', min: 1, max: 12 },
            postsPerRow: { type: 'select', label: 'Posts Per Row', options: [{ value: 2, title: '2 Columns' }, { value: 3, title: '3 Columns' }] },
            showViewAllButton: { type: 'boolean', label: 'Show "View All" Button' },
            viewAllLink: { type: 'text', label: 'View All Link' },
        },
    },
    [WIDGET_TYPES.TEAM_MEMBERS]: {
        name: 'Team Members',
        description: 'Showcase your team with photos and bios',
        icon: 'mdi-account-group',
        category: 'content',
        color: 'cyan',
        defaultContent: {
            title: 'Meet Our Team',
            subtitle: 'The people behind our success',
            members: [
                {
                    name: 'John Doe',
                    role: 'CEO & Founder',
                    bio: 'Passionate about building great products',
                    image: null,
                    email: 'john@example.com',
                    linkedin: '',
                    twitter: '',
                },
                {
                    name: 'Jane Smith',
                    role: 'CTO',
                    bio: 'Tech enthusiast with 10+ years experience',
                    image: null,
                    email: 'jane@example.com',
                    linkedin: '',
                    twitter: '',
                },
            ],
        },
        schema: {
            title: { type: 'text', label: 'Section Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            members: {
                type: 'array',
                label: 'Team Members',
                itemSchema: {
                    name: { type: 'text', label: 'Name', required: true },
                    role: { type: 'text', label: 'Role/Title', required: true },
                    bio: { type: 'textarea', label: 'Bio' },
                    image: { type: 'image', label: 'Photo' },
                    email: { type: 'text', label: 'Email' },
                    linkedin: { type: 'text', label: 'LinkedIn URL' },
                    twitter: { type: 'text', label: 'Twitter Handle' },
                },
            },
        },
    },
    [WIDGET_TYPES.CONTACT_FORM]: {
        name: 'Contact Form',
        description: 'Contact or inquiry form',
        icon: 'mdi-email-edit',
        category: 'content',
        color: 'green',
        defaultContent: {
            title: 'Get in Touch',
            subtitle: 'We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.',
            nameLabel: 'Your Name',
            emailLabel: 'Email Address',
            messageLabel: 'Message',
            buttonText: 'Send Message',
            successMessage: 'Thank you! We\'ll get back to you soon.',
        },
        schema: {
            title: { type: 'text', label: 'Form Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            nameLabel: { type: 'text', label: 'Name Field Label' },
            emailLabel: { type: 'text', label: 'Email Field Label' },
            messageLabel: { type: 'text', label: 'Message Field Label' },
            buttonText: { type: 'text', label: 'Submit Button Text' },
            successMessage: { type: 'textarea', label: 'Success Message' },
        },
    },
    [WIDGET_TYPES.VIDEO]: {
        name: 'Video',
        description: 'Embed YouTube or Vimeo videos',
        icon: 'mdi-video',
        category: 'content',
        color: 'red',
        defaultContent: {
            title: 'Watch Our Video',
            subtitle: '',
            videoUrl: 'https://www.youtube.com/embed/dQw4w9WgXcQ',
            aspectRatio: '16/9',
        },
        schema: {
            title: { type: 'text', label: 'Section Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            videoUrl: { type: 'text', label: 'Video URL (YouTube or Vimeo embed URL)', required: true },
            aspectRatio: { type: 'select', label: 'Aspect Ratio', options: ['16/9', '4/3', '21/9'] },
        },
    },
    [WIDGET_TYPES.NEWSLETTER]: {
        name: 'Newsletter Signup',
        description: 'Newsletter subscription form',
        icon: 'mdi-email-newsletter',
        category: 'marketing',
        color: 'amber',
        defaultContent: {
            title: 'Subscribe to Our Newsletter',
            subtitle: 'Get the latest updates and exclusive offers delivered to your inbox.',
            emailPlaceholder: 'Enter your email',
            buttonText: 'Subscribe',
            successMessage: 'Thank you for subscribing!',
            privacyText: 'We respect your privacy. Unsubscribe at any time.',
        },
        schema: {
            title: { type: 'text', label: 'Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            emailPlaceholder: { type: 'text', label: 'Email Placeholder' },
            buttonText: { type: 'text', label: 'Button Text' },
            successMessage: { type: 'text', label: 'Success Message' },
            privacyText: { type: 'text', label: 'Privacy Note' },
        },
    },
    [WIDGET_TYPES.TIMELINE]: {
        name: 'Timeline / Process',
        description: 'Step-by-step timeline or process',
        icon: 'mdi-timeline',
        category: 'content',
        color: 'deep-purple',
        defaultContent: {
            title: 'How It Works',
            subtitle: 'Get started in 3 easy steps',
            steps: [
                {
                    title: 'Sign Up',
                    description: 'Create your free account in seconds',
                    icon: 'mdi-account-plus',
                },
                {
                    title: 'Set Up',
                    description: 'Customize your profile and preferences',
                    icon: 'mdi-cog',
                },
                {
                    title: 'Get Started',
                    description: 'Start using all our amazing features',
                    icon: 'mdi-rocket-launch',
                },
            ],
        },
        schema: {
            title: { type: 'text', label: 'Section Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            steps: {
                type: 'array',
                label: 'Steps',
                itemSchema: {
                    title: { type: 'text', label: 'Step Title', required: true },
                    description: { type: 'textarea', label: 'Description' },
                    icon: { type: 'icon', label: 'Icon' },
                },
            },
        },
    },
    [WIDGET_TYPES.CLIENTS]: {
        name: 'Clients / Logo Grid',
        description: 'Display client or partner logos',
        icon: 'mdi-domain',
        category: 'marketing',
        color: 'brown',
        defaultContent: {
            title: 'Trusted By Leading Companies',
            subtitle: '',
            logos: [
                { name: 'Company 1', image: null, url: '' },
                { name: 'Company 2', image: null, url: '' },
                { name: 'Company 3', image: null, url: '' },
                { name: 'Company 4', image: null, url: '' },
            ],
        },
        schema: {
            title: { type: 'text', label: 'Section Title' },
            subtitle: { type: 'textarea', label: 'Subtitle' },
            logos: {
                type: 'array',
                label: 'Client Logos',
                itemSchema: {
                    name: { type: 'text', label: 'Company Name', required: true },
                    image: { type: 'image', label: 'Logo Image' },
                    url: { type: 'text', label: 'Website URL' },
                },
            },
        },
    },
    [WIDGET_TYPES.CUSTOM_HTML]: {
        name: 'Custom HTML',
        description: 'Add custom HTML content',
        icon: 'mdi-code-tags',
        category: 'advanced',
        color: 'grey',
        defaultContent: {
            htmlContent: '<div class="text-center pa-8"><h2>Custom HTML Content</h2><p>Add your custom HTML here</p></div>',
            containerClass: '',
            containerStyle: '',
            fullWidth: false,
        },
        schema: {
            htmlContent: { type: 'textarea', label: 'HTML Content', required: true, hint: 'Enter your custom HTML code', rows: 10 },
            containerClass: { type: 'text', label: 'Container CSS Class', hint: 'e.g., bg-grey-lighten-4' },
            containerStyle: { type: 'text', label: 'Inline Styles', hint: 'e.g., padding: 20px;' },
            fullWidth: { type: 'boolean', label: 'Full Width Container' },
        },
    },
};

export function getWidgetDefinition(type) {
    return WIDGET_DEFINITIONS[type] || null;
}

export function getAvailableWidgets() {
    return Object.entries(WIDGET_DEFINITIONS).map(([type, def]) => ({
        type,
        ...def,
    }));
}

export function getWidgetsByCategory(category) {
    return getAvailableWidgets().filter(w => w.category === category);
}

export const WIDGET_CATEGORIES = [
    { value: 'core', label: 'Core', color: 'primary' },
    { value: 'marketing', label: 'Marketing', color: 'purple' },
    { value: 'content', label: 'Content', color: 'teal' },
    { value: 'advanced', label: 'Advanced', color: 'grey' },
];
