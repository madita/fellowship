<template>
    <div class="d-flex flex-column flex-grow-1">
        <div class="d-flex align-center py-3">
            <div>
                <div class="display-1">My Notifications</div>
                <v-breadcrumbs :items="breadcrumbs" class="pa-0 py-2"></v-breadcrumbs>
            </div>
            <v-spacer></v-spacer>
        </div>

        <v-card>
            <v-toolbar
                dark
            >
<!--                <v-app-bar-nav-icon></v-app-bar-nav-icon>-->

                <v-toolbar-title>Notifications</v-toolbar-title>

                <v-spacer></v-spacer>

                <v-btn icon>
                    <v-icon>mdi-magnify</v-icon>
                </v-btn>
            </v-toolbar>

            <v-list three-line>
                <v-list-item-group v-model="notificationModel">

                    <v-list-item
                        v-for="(item) in notifications"
                        :key="item.title"
                    >
                        <v-list-item-avatar>
                                <v-img src="/images/avatars/avatar1.svg"></v-img>
                        </v-list-item-avatar>
                        <v-list-item-content>
                            <v-list-item-title >{{ item.data.subject }} - {{ item.data.notifier.username }}</v-list-item-title>
                            <v-list-item-subtitle v-html="item.data.body"></v-list-item-subtitle>
                            <v-btn  :href="item.data.url" target="_blank" depressed small>{{item.data.action}}</v-btn>
                        </v-list-item-content>
                        <v-list-item-action>
                            <v-list-item-action-text>{{item.created_at | humanDiff()}}</v-list-item-action-text>
                            <v-icon
                                class="d-inline-flex"
                                @click="deleteNotification(item.id)"
                                color="red darken-3"
                            >
                                mdi-delete
                            </v-icon>
                        </v-list-item-action>
                    </v-list-item>

                </v-list-item-group>
            </v-list>
        </v-card>
    </div>
</template>

<script>

export default {
    components: {

    },
    // props() {
    //     id: {
    //         required: false
    //     }
    // },
    data() {
        return {
            notificationModel:null,
            isLoading: false,
            breadcrumbs: [{
                text: 'Account',
                disabled: false,
                href: '#'
            }, {
                text: 'Notifications'
            }],

            searchQuery: '',
            selectedNotifications: [],
            notifications: {}
        }
    },
    watch: {
        '$route' () {
            this.notificationModel = this.$route.params.id;
        },
        selectedNotifications(val) {

        }
    },
    methods: {
        getAllNotifications() {
            try {
                axios.get('/api/account/notifications').then((data) => {
                    this.notifications = data.data
                }).catch(err => {
                    if (err.response.status === 404) {
                        throw new Error(`${err.config.url} not found`);
                    }
                    throw err;
                })
            } catch (error) {
                console.warn(error)
            }
        },
        deleteNotification(id) {
            try {
                axios.delete('/api/account/notification/delete/' + id).then(() => {
                    this.getAllNotifications();
                }).catch(err => {
                    if (err.response.status === 404) {
                        throw new Error(`${err.config.url} not found`);
                    }
                    throw err;
                })
            } catch (error) {
                console.warn(error)
            }
        },
        searchNotifications() {
        },
        open() {
        }
    },
    mounted() {
        if(this.$route.params.id) {
            this.notificationModel = this.$route.params.id;
        }
        this.getAllNotifications();
    }
}
</script>

<style lang="scss" scoped>
.slide-fade-enter-active {
    transition: all 0.3s ease;
}

.slide-fade-leave-active {
    transition: all 0.3s cubic-bezier(1, 0.5, 0.8, 1);
}

.slide-fade-enter,
.slide-fade-leave-to {
    transform: translateX(10px);
    opacity: 0;
}
</style>
