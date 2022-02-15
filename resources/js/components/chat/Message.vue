<template>
    <div class="d-flex flex-grow-1" :class="{ 'flex-row-reverse': message.selfOwned}">
        <!--            <user-avatar :user="message.user.id" />-->
        {{ message.user.username }}

        <div class="mx-2">
            <v-tooltip bottom>
                <template v-slot:activator="{ on, attrs }">
                    <v-card
                        class="pa-1"
                        :class="{ 'primary darken-1': message.selfOwned}"
                        :dark="message.selfOwned"
                        v-bind="attrs"
                        v-on="on"
                    >
                        <div class="font-weight-bold">{{ message.body }}</div>
                    </v-card>
                </template>
                <span>{{ message.created_at | humanDiff() }}</span>
            </v-tooltip>
        </div>
    </div>
</template>


<script>
export default {
    props: ['message']
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
