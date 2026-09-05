<template>
    <v-tooltip location="bottom">
        <template #activator="{ props }">
            <v-avatar v-bind="props" :color="background" size="45">
                <v-img v-if="!legacyName && user?.avatar" :src="user.avatar"></v-img>
                <span
                    v-else
                    :style="styleObject"
                    :class="initials.length === 1 ? 'text-h4' : 'text-h5'"
                >
          {{ initials }}
        </span>
            </v-avatar>
        </template>
        <span>{{ displayName }}</span>
    </v-tooltip>
</template>

<script>
export default {
    props: {
        user: {
            type: Object,
            default: null,
        },
        // Name of the original author from a legacy system (imported
        // content). When set, the avatar shows initials generated from
        // this name instead of the placeholder account's picture.
        legacyName: {
            type: String,
            default: null,
        }
    },
    computed: {
        displayName() {
            return this.legacyName || this.user?.username || '—';
        },

        // Generate initials if not provided
        initials() {
            if (!this.legacyName && this.user?.initials) {
                return this.user.initials;
            }

            const name = this.legacyName || this.user?.username;
            if (name) {
                const parts = name.trim().split(/\s+/);
                if (parts.length > 1) {
                    return (parts[0][0] + parts[1][0]).toUpperCase();
                }
                return name.substring(0, 2).toUpperCase();
            }
            return '?';
        },

        // Background color with a fallback
        background() {
            if (this.legacyName) {
                return this.$helpers.randomBackgroundColor(this.legacyName.length, null);
            }
            return this.user?.colour || this.$helpers.randomBackgroundColor(this.user?.username?.length || 0, null);
        },

        // Font color based on background color
        fontColour() {
            return this.$helpers.lightenColor(this.background, null);
        },

        // Style object for inline styles
        styleObject() {
            return {
                color: this.fontColour
            };
        }
    }
};
</script>
