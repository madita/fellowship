<template>
    <!-- online users drawer -->
    <v-navigation-drawer
        v-model="usersDrawer"
        width="180"
        :right="!$vuetify.rtl"
        app
    >
        <v-list dense>
            <v-subheader class="mx-1 overline">
                {{ $t('chat.online', { count: users.length }) }}
            </v-subheader>
            <v-list-item v-for="item in users" :key="item.id" class="mb-1">
<!--                <user-avatar :user="item" class="mx-1" />-->
                <v-list-item-content>
                    <v-list-item-title :class="{ 'primary--text': item.id === user.id }">{{ item.username }}</v-list-item-title>
                </v-list-item-content>
            </v-list-item>
        </v-list>
    </v-navigation-drawer>
</template>

<script>
    // import pluralize from 'pluralize'
    import Bus from '../../bus'
    import {mapGetters} from "vuex";

    export default {
        data() {
            return {
                usersDrawer: true,
                users:[]
            }
        },
        methods: {
            // pluralize: pluralize
        },
        computed: {
            ...mapGetters({
                user: 'auth/user',
            })
        },
        mounted() {
            Bus.$on('chatUsers.here', (users) => {
                this.users = users
            })
                .$on('chatUsers.joined', (user) => {
                   this.users.unshift(user)
                })
                .$on('chatUsers.left', (user) => {
                    this.users = this.users.filter((u) => {
                        return u.id !== user.id
                    })
                })

        }
    }
</script>

<style lang="scss">
    .users {
        background-color: #fff;
        border: 1px solid #d3e0e9;
        border-radius: 3px;

        &__header {
            padding:15px;
            font-weight:800;
            margin: 0;
        }

        &__user {
            padding:0 15px;

            &:last-child {
                padding-bottom: 15px;
            }
        }
    }

</style>
