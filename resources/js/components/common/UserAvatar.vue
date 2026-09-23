<template>
    <div class="user-avatar d-inline-flex align-center ga-2">
        <v-tooltip location="bottom">
            <template #activator="{ props }">
                <!-- A real member's face leads to their page; an imported
                     name from the old site has nobody to lead to. -->
                <component
                    :is="profileLink ? 'router-link' : 'span'"
                    v-bind="profileLink ? { to: profileLink } : {}"
                    class="avatar-link"
                    :class="{ 'is-link': profileLink }"
                >
                    <v-avatar v-bind="props" :color="background" :size="size">
                        <v-img v-if="!legacyName && user?.avatar" :src="user.avatar" />
                        <span
                            v-else
                            :style="styleObject"
                            :class="initials.length === 1 ? 'text-h4' : 'text-h5'"
                        >{{ initials }}</span>
                    </v-avatar>
                </component>
            </template>
            <span>{{ displayName }}</span>
        </v-tooltip>

        <!-- Off by default: most places show a face, not a standing -->
        <v-chip
            v-if="showRank && rank"
            size="x-small"
            :color="rank.color"
            variant="tonal"
            class="rank-chip"
        >
            <v-icon v-if="!rank.image_url" :icon="rank.icon" size="x-small" start />
            <v-avatar v-else start>
                <v-img :src="rank.image_url" :alt="rank.name" />
            </v-avatar>
            {{ rank.name }}
        </v-chip>
    </div>
</template>

<script>
export default {
    name: 'UserAvatar',
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
        },
        size: {
            type: [Number, String],
            default: 45,
        },
        // Show the member's rank beside the avatar. The rank has to be on
        // the user the caller passes in — the forum sends it, most places
        // do not.
        showRank: {
            type: Boolean,
            default: false,
        },
        // Somewhere a link would be wrong, like inside another link
        linkToProfile: {
            type: Boolean,
            default: true,
        },
    },
    computed: {
        displayName() {
            return this.legacyName || this.user?.username || '—';
        },

        rank() {
            return this.user?.rank || null;
        },

        /**
         * Where the avatar leads. An imported name has no account behind it,
         * so it leads nowhere.
         */
        profileLink() {
            if (!this.linkToProfile || this.legacyName || !this.user?.username) {
                return null;
            }

            return { name: 'member-profile', params: { username: this.user.username } };
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

<style scoped>
.avatar-link {
    display: inline-flex;
    text-decoration: none;
}

.is-link {
    cursor: pointer;
    transition: opacity 0.15s;
}

.is-link:hover {
    opacity: 0.85;
}

.rank-chip {
    flex-shrink: 0;
}
</style>
