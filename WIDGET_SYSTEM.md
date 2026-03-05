# Widget System - New Widgets

## Overview

Four new versatile widgets that can be used in homepage, footer, or dashboard contexts.

## 1. Quick Links Widget

**File:** `QuickLinksWidget.vue`

Displays a configurable list of links with icons, badges, and multiple display styles.

### Use Cases
- **Homepage:** Main site navigation
- **Footer:** Footer quick links
- **Dashboard:** User quick actions

### Display Styles
- **Card:** Links displayed as hoverable cards with icons and descriptions
- **List:** Compact list view with icons
- **Button:** Action buttons with optional badges

### Configuration

```javascript
{
  content: {
    title: "Quick Links",
    subtitle: "Navigate our site",
    links: [
      {
        title: "Documentation",
        description: "Read the docs",
        url: "/docs",
        icon: "mdi-book-open-variant",
        color: "blue",
        internal: true,
        badge: "New",
        badgeColor: "red"
      }
      // ... more links
    ]
  },
  config: {
    style: "card",          // 'card', 'list', 'button'
    cols: 12,
    sm: 6,
    md: 4,
    lg: 3,
    alignment: "center",    // 'start', 'center', 'end'
    elevation: 2,
    hover: true,
    iconSize: 48,
    iconColor: "primary",
    buttonColor: "primary",
    outlined: false,
    block: false
  }
}
```

### Example Configurations

**Homepage Hero Section:**
```javascript
{
  style: "card",
  md: 4,
  alignment: "center",
  elevation: 3,
  iconSize: 64
}
```

**Footer Navigation:**
```javascript
{
  style: "list",
  cols: 12,
  md: 3,
  elevation: 0
}
```

**Dashboard Quick Actions:**
```javascript
{
  style: "button",
  block: true,
  large: true,
  cols: 12,
  sm: 6,
  md: 4
}
```

## 2. Activity Feed Widget

**File:** `ActivityFeedWidget.vue`

Displays recent activity with timestamps, avatars, and badges.

### Use Cases
- **Homepage:** Show recent community activity
- **Dashboard:** User's personal activity feed
- **Sidebar:** Recent updates widget

### Features
- Avatar/icon support
- Timestamps (relative or absolute)
- Badges for highlighting
- "View All" link
- Empty state handling
- Two-line or three-line list items

### Configuration

```javascript
{
  content: {
    title: "Recent Activity",
    subtitle: "What's happening",
    viewAllUrl: "/activity",
    viewAllText: "View All",
    viewAllInternal: true,
    emptyText: "No recent activity",
    items: [
      {
        title: "New forum post",
        subtitle: "by John Doe",
        description: "Check out this amazing feature...",
        timestamp: "2 min ago",
        icon: "mdi-forum",
        iconColor: "blue",
        avatar: "",
        url: "/forum/1",
        internal: true,
        badge: "New",
        badgeColor: "primary"
      }
      // ... more items
    ]
  },
  config: {
    elevation: 1,
    twoLine: true,
    threeLine: false,
    showAvatar: true,
    maxItems: 10,
    containerClass: "py-4"
  }
}
```

### Example Configurations

**Dashboard Feed:**
```javascript
{
  elevation: 2,
  threeLine: true,
  maxItems: 5
}
```

**Homepage Recent Posts:**
```javascript
{
  twoLine: true,
  showAvatar: false,
  maxItems: 3
}
```

## 3. Social Links Widget

**File:** `SocialLinksWidget.vue`

Displays social media links with various styling options.

### Use Cases
- **Footer:** Social media links
- **Homepage:** Follow section
- **Contact Page:** Connect with us

### Display Styles
- **Icon:** Icon-only buttons
- **Fab:** Floating action button style
- **Text:** Text buttons with icons
- **Outlined:** Outlined buttons with icons

### Configuration

```javascript
{
  content: {
    title: "Follow Us",
    subtitle: "Stay connected",
    links: [
      {
        name: "Twitter",
        url: "https://twitter.com/yourhandle",
        icon: "mdi-twitter",
        color: "#1DA1F2"
      },
      {
        name: "Facebook",
        url: "https://facebook.com/yourpage",
        icon: "mdi-facebook",
        color: "#1877F2"
      }
      // ... more social links
    ]
  },
  config: {
    style: "icon",
    size: "default",
    iconSize: 24,
    alignment: "center",
    showLabels: false,
    defaultColor: "grey darken-1",
    containerClass: "py-4",
    titleClass: "text-center",
    spacing: 2
  }
}
```

### Popular Social Networks

Included icon mappings:
- Twitter: `mdi-twitter`
- Facebook: `mdi-facebook`
- LinkedIn: `mdi-linkedin`
- GitHub: `mdi-github`
- Discord: `mdi-discord`
- YouTube: `mdi-youtube`
- Instagram: `mdi-instagram`
- TikTok: `mdi-music-note` (or use custom)
- Reddit: `mdi-reddit`
- Twitch: `mdi-twitch`

### Example Configurations

**Footer Social Bar:**
```javascript
{
  style: "icon",
  size: "small",
  alignment: "center",
  spacing: 3
}
```

**Homepage Hero:**
```javascript
{
  style: "fab",
  size: "large",
  iconSize: 32,
  alignment: "center",
  spacing: 4
}
```

**With Labels:**
```javascript
{
  style: "outlined",
  showLabels: true,
  alignment: "start"
}
```

## 4. User Stats Widget

**File:** `UserStatsWidget.vue`

Displays statistics with trends, progress bars, and change indicators.

### Use Cases
- **Dashboard:** User statistics overview
- **Admin Panel:** Site analytics
- **Profile Page:** User achievements

### Features
- Formatted values (number, currency, percentage)
- Trend indicators (up/down/neutral)
- Progress bars
- Clickable cards
- Icon support
- Color themes (light/dark)

### Configuration

```javascript
{
  content: {
    title: "Dashboard Overview",
    subtitle: "Your activity summary",
    stats: [
      {
        label: "Total Posts",
        value: 1234,
        format: "number",          // 'number', 'currency', 'percentage'
        change: 12.5,              // Percentage change
        changePeriod: "this month",
        description: "Forum posts created",
        icon: "mdi-forum",
        iconColor: "blue",
        color: "",                 // Card background color
        dark: false,               // Dark theme for card
        url: "/posts",
        internal: true,
        progress: 75,              // Optional progress bar (0-100)
        progressColor: "blue"
      }
      // ... more stats
    ]
  },
  config: {
    cols: 6,
    sm: 6,
    md: 3,
    elevation: 2,
    outlined: false,
    hover: true,
    iconSize: 48,
    containerClass: "py-4"
  }
}
```

### Value Formats

- **number:** `1,234` (with thousand separators)
- **currency:** `$1,234.00` (USD by default)
- **percentage:** `75%`

### Example Configurations

**Dashboard Overview:**
```javascript
{
  cols: 6,
  md: 3,
  elevation: 2,
  hover: true,
  iconSize: 48
}
```

**Admin Analytics:**
```javascript
{
  cols: 12,
  md: 6,
  lg: 3,
  elevation: 3,
  iconSize: 56
}
```

**Compact Stats:**
```javascript
{
  cols: 6,
  sm: 4,
  md: 2,
  elevation: 1,
  iconSize: 32
}
```

## Integration

### Backend Setup

Widgets are stored in the `widgets` table with:
- `location` - 'home', 'footer', or 'dashboard'
- `type` - Widget component name (e.g., 'QuickLinksWidget')
- `content` - Translatable title and content (JSON)
- `config` - Widget-specific configuration (JSON)

### Frontend Usage

```vue
<template>
  <component
    :is="widget.type"
    :content="widget.content"
    :config="widget.config"
  />
</template>

<script setup>
import QuickLinksWidget from '@/components/landing/widgets/QuickLinksWidget.vue'
import ActivityFeedWidget from '@/components/landing/widgets/ActivityFeedWidget.vue'
import SocialLinksWidget from '@/components/landing/widgets/SocialLinksWidget.vue'
import UserStatsWidget from '@/components/landing/widgets/UserStatsWidget.vue'

// Register widgets
const widgetComponents = {
  QuickLinksWidget,
  ActivityFeedWidget,
  SocialLinksWidget,
  UserStatsWidget
}
</script>
```

### Example Widget Database Entry

```php
Widget::create([
    'location' => 'home',
    'type' => 'QuickLinksWidget',
    'section_id' => null,
    'enabled' => true,
    'order' => 1,
    'column' => 1,
    'config' => [
        'style' => 'card',
        'md' => 4,
        'alignment' => 'center',
        'elevation' => 2
    ],
    'en' => [
        'title' => 'Quick Links',
        'content' => json_encode([
            'links' => [
                [
                    'title' => 'Documentation',
                    'url' => '/docs',
                    'icon' => 'mdi-book-open-variant',
                    'internal' => true
                ]
            ]
        ])
    ]
]);
```

## Styling

All widgets use:
- Vuetify components for consistency
- Responsive grid system
- Configurable colors and spacing
- Hover effects and transitions
- Dark mode support (where applicable)

## Best Practices

1. **Keep content arrays manageable** - Max 10 items for optimal UX
2. **Use internal routing** - Set `internal: true` for Vue Router navigation
3. **Provide meaningful icons** - Use Material Design Icons (mdi-*)
4. **Set appropriate elevations** - Higher for primary content, lower for secondary
5. **Configure responsive columns** - Different layouts for mobile/tablet/desktop
6. **Use badges sparingly** - Highlight important or new items only
7. **Test empty states** - Ensure widgets handle empty data gracefully

## Color System

Available color modifiers:
- `primary`, `secondary`, `accent`
- `success`, `warning`, `error`, `info`
- `blue`, `green`, `red`, `orange`, `purple`, `yellow`
- `grey`, `grey darken-1`, `grey lighten-1`, etc.

## Accessibility

All widgets include:
- Proper ARIA labels
- Keyboard navigation support
- Screen reader friendly content
- High contrast support
- Semantic HTML structure

## Performance

- Widgets cache data when possible
- Lazy loading for images
- Efficient re-rendering with Vue 3
- Minimal DOM manipulation
- Optimized transitions and animations
