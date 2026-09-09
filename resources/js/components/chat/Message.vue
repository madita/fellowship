<template>
    <div v-if="message?.selfOwned" class="justify-end d-flex text-end mb-1">
        <div>
            <small class="text-medium-emphasis text-subtitle-2" v-if="message.created_at">
                {{ formatDistanceToNow(message?.created_at) }}
            </small>

            <v-sheet class="chat-bubble chat-bubble--own rounded-lg px-3 py-2 mb-1">
                <p class="text-body-1 mb-0">{{ message?.body }}</p>
            </v-sheet>
        </div>
    </div>
    <div v-else class="d-flex align-start ga-3 mb-1">
        <!---User Avatar-->
        <user-avatar :user="message?.user"></user-avatar>
        <div>
            <small class="text-medium-emphasis text-subtitle-2" v-if="message.created_at">
                {{ message?.user?.username }},
                {{ formatDistanceToNow(message?.created_at) }}
            </small>

            <v-sheet class="chat-bubble rounded-lg px-3 py-2 mb-1">
                <p class="text-body-1 mb-0">{{ message?.body }}</p>
            </v-sheet>
        </div>
    </div>
</template>

<script>
import UserAvatar from "@/components/common/UserAvatar.vue";
import { formatDateDistanceToNow } from "@/plugins/formatDate.js";

export default {
    methods: {
        formatDistanceToNow(date) {
            return formatDateDistanceToNow(date);
        }
    },
    components: {UserAvatar},
    props: {
        message: {
            type: Object,
            required: true
        }
    }
}
</script>

<style lang="scss">
.chat-bubble {
    background: rgba(var(--v-theme-on-surface), 0.06) !important;
    white-space: pre-wrap;

    &--own {
        background: rgba(var(--v-theme-primary), 0.12) !important;
    }
}
</style>
