# Custom Footer HTML Feature

## Overview
The Custom Footer HTML feature allows administrators to replace the default footer with custom HTML content through the admin settings panel.

## Location
Admin Settings > Advanced Tab > Custom Footer section

## How to Use

### 1. Enable Custom Footer
- Navigate to Admin Settings > Advanced Tab
- Find the "Custom Footer" section
- Toggle "Enable Custom Footer HTML" switch to ON

### 2. Choose a Template
Once enabled, you have two template options:

#### Simple Template
A centered, single-column footer with:
- Application name
- Copyright text
- Contact email
- Social media icons

**Best for:** Minimalist designs, simple applications

#### Complex Template
A full-width, three-column footer with:
- Navigation links section
- Contact information section
- Newsletter signup and social media section
- Footer bottom with copyright

**Best for:** Feature-rich applications, content-heavy sites

### 3. Customize the HTML
After loading a template, you can:
- Edit the HTML directly in the textarea
- Add custom sections
- Modify styling
- Add or remove components

## Available Variables

Use these template variables that will be automatically replaced with actual values:

| Variable | Description | Example |
|----------|-------------|---------|
| `{{appName}}` | Application name | "Fellowship" |
| `{{appCopyright}}` | Copyright text | "© Fellowship 2024" |
| `{{contactEmail}}` | Contact email | "support@fellowship.com" |
| `{{contactPhone}}` | Contact phone | "+1 234 567 8900" |
| `{{contactAddress}}` | Contact address | "123 Main St, City" |
| `{{socialTwitter}}` | Twitter URL | "https://twitter.com/..." |
| `{{socialFacebook}}` | Facebook URL | "https://facebook.com/..." |
| `{{socialInstagram}}` | Instagram URL | "https://instagram.com/..." |

## Example Usage

### Simple Footer with Variables
```html
<v-footer inset>
    <v-container>
        <div class="text-center">
            <strong>{{appName}}</strong>
            <div>{{appCopyright}}</div>
            <a :href="`mailto:{{contactEmail}}`">{{contactEmail}}</a>
        </div>
    </v-container>
</v-footer>
```

### Adding Custom Links
```html
<v-footer>
    <v-container>
        <v-row>
            <v-col cols="12" md="6">
                <h3>Quick Links</h3>
                <router-link to="/about">About Us</router-link> |
                <router-link to="/privacy">Privacy Policy</router-link> |
                <router-link to="/terms">Terms of Service</router-link>
            </v-col>
            <v-col cols="12" md="6" class="text-right">
                {{appCopyright}}
            </v-col>
        </v-row>
    </v-container>
</v-footer>
```

## Technical Notes

### Supported HTML
- All Vuetify 3 components (v-footer, v-container, v-row, v-col, etc.)
- Vue directives (v-if, v-for, :href, etc.)
- Standard HTML elements
- CSS classes (Vuetify utility classes and custom classes)

### Conditional Rendering
Show/hide elements based on variable availability:
```html
<div v-if="{{contactEmail}}">
    Email: {{contactEmail}}
</div>
```

### Styling
You can use:
- Vuetify utility classes: `text-center`, `mb-4`, `pa-5`, etc.
- Custom CSS classes (defined in Branding > Custom CSS)
- Inline styles (though not recommended)

## Tips

1. **Test Your Footer**: After saving, check both the landing page and authenticated pages
2. **Responsive Design**: Use Vuetify's grid system (`v-row`, `v-col`) for mobile responsiveness
3. **Keep It Simple**: Complex footers can slow down page load times
4. **Backup**: Copy your custom HTML before making major changes
5. **Accessibility**: Include proper semantic HTML and ARIA labels

## Troubleshooting

### Footer Not Showing
- Ensure "Enable Custom Footer HTML" is toggled ON
- Save settings after making changes
- Clear browser cache

### Variables Not Working
- Use exact variable syntax: `{{variableName}}`
- Ensure the setting value exists in General/Contact settings
- Check for typos in variable names

### Layout Issues
- Verify Vuetify component syntax
- Test on different screen sizes
- Use browser DevTools to inspect CSS

## Reverting to Default
To go back to the default footer:
1. Toggle "Enable Custom Footer HTML" to OFF
2. Click "Save Settings"

The custom HTML will be preserved if you want to re-enable it later.
