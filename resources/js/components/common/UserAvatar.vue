<template>
    <v-tooltip location="bottom">
        <template #activator="{ props }">
            <v-avatar v-bind="props" :color="background" size="45">
                <v-img v-if="user.avatar" :src="user.avatar"></v-img>
                <span
                    v-else
                    :style="styleObject"
                    :class="initials.length === 1 ? 'text-h4' : 'text-h5'"
                >
          {{ initials }}
        </span>
            </v-avatar>
        </template>
        <span>{{ user.username }}</span>
    </v-tooltip>
</template>

<script>
export default {
    props: {
        user: {
            type: Object,
            required: true
        }
    },
    computed: {
        // Generate initials if not provided
        initials() {
            if (this.user?.initials) {
                return this.user.initials;
            }
            // Generate initials from username
            if (this.user?.username) {
                const parts = this.user.username.split(' ');
                if (parts.length > 1) {
                    return (parts[0][0] + parts[1][0]).toUpperCase();
                }
                return this.user.username.substring(0, 2).toUpperCase();
            }
            return '?';
        },

        // Background color with a fallback
        background() {
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
