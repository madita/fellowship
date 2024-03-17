<template>
<!--    <div class="d-flex" :class="{ 'flex-row-reverse': message.selfOwned }">-->
<!--        &lt;!&ndash; To reintegrate the user-avatar component, simply uncomment the following line and ensure the component is imported and registered &ndash;&gt;-->
<!--        &lt;!&ndash; <user-avatar :user="message.user.id" /> &ndash;&gt;-->

<!--        {{ message?.user?.username }}-->
<!--        <div class="mx-2">-->
<!--&lt;!&ndash;            <v-tooltip>&ndash;&gt;-->
<!--&lt;!&ndash;                <template #activator="{ props }">&ndash;&gt;-->
<!--                    <v-card-->
<!--                        class="pa-1"-->
<!--                        :class="{ 'primary darken-1': message?.selfOwned }"-->
<!--                        :dark="message?.selfOwned"-->
<!--                    >-->
<!--                        <div class="font-weight-bold">{{ message?.body }}</div>-->
<!--                    </v-card>-->
<!--&lt;!&ndash;                </template>&ndash;&gt;-->
<!--                <span>{{ message?.created_at }}</span>-->
<!--&lt;!&ndash;            </v-tooltip>&ndash;&gt;-->
<!--        </div>-->
<!--    </div>-->

    <div v-if="message?.selfOwned" class="justify-end d-flex text-end mb-1">
        <div>
            <small class="text-medium-emphasis text-subtitle-2" v-if="message.created_at">
                {{ $formatDistanceToNow(message?.created_at) }}
                </small
            >

            <v-sheet class="bg-grey100 rounded-md px-3 py-2 mb-1">
                <p class="text-body-1">{{ message?.body }}</p>
            </v-sheet>
<!--            <v-sheet v-if="chat.type == 'img'" class="mb-1">-->
<!--                <img :src="chat.msg" class="rounded-md" alt="pro" width="250" />-->
<!--            </v-sheet>-->
        </div>
    </div>
    <div v-else class="d-flex align-items-start gap-3 mb-1">
        <!---User Avatar-->
<!--        <v-avatar>-->
<!--&lt;!&ndash;            <img :src="chatDetail.thumb" alt="pro" width="40" />&ndash;&gt;-->
<!--        </v-avatar>-->
        <user-avatar :user="message?.user"></user-avatar>
        <div>
            <small class="text-medium-emphasis text-subtitle-2" v-if="message.created_at">
                {{ message?.user?.username }},
                {{ $formatDistanceToNow(message?.created_at) }}
            </small>

            <v-sheet class="bg-grey100 rounded-md px-3 py-2 mb-1">
                <p class="text-body-1">{{ message?.body }}</p>
            </v-sheet>
<!--            <v-sheet v-if="chat.type == 'img'" class="mb-1">-->
<!--                <img :src="chat.msg" class="rounded-md" alt="pro" width="250" />-->
<!--            </v-sheet>-->
        </div>
    </div>
</template>

<script>
import UserAvatar from "@/components/common/UserAvatar.vue";
import {formatDistanceToNow} from "date-fns";

export default {
    methods: {formatDistanceToNow},
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
.chat {
    &__message {
        padding: 15px;
        border-bottom: 1px solid #eee;

        &--own {
            background-color: #f0f0f0;
        }

        &-user {
            font-weight: 800;
        }

        &-timestamp {
            color: #aaa;
        }

        &-body {
            margin-bottom: 0;
            white-space: pre-wrap;
        }
    }
}
</style>
