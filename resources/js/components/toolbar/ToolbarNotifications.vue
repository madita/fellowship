<template>
    <v-menu offset-y left transition="slide-y-transition">
        <template v-slot:activator="{ on }">
            <v-badge
                bordered
                :content="notifications.length"
                :value="notifications.length"
                offset-x="22"
                offset-y="22"
            >
                <v-btn icon v-on="on">
                    <v-icon>mdi-bell-outline</v-icon>
                </v-btn>
            </v-badge>
        </template>


        <!-- dropdown card -->
        <v-card>
            <v-list three-line dense max-width="400">
                <v-list-item-group>
                <v-subheader class="pa-2 font-weight-bold">Notifications</v-subheader>
                <div v-for="(item, index) in notifications" :key="index">
                    <v-divider v-if="index > 0 && index < notifications.length" inset></v-divider>

                    <v-list-item>
                        <v-list-item-avatar size="32" color="primary">
                            <v-icon dark small>mdi-noodles</v-icon>
                        </v-list-item-avatar>
                        <v-list-item-content @click="$router.push({name: 'my-notification', params: { id: index },})">
                            <v-list-item-title v-text="item.data.subject"></v-list-item-title>
                            <v-list-item-subtitle class="caption" v-text="item.data.body"></v-list-item-subtitle>
                            <v-btn  :href="item.data.url" target="_blank" depressed small>{{item.data.action}}</v-btn>
                        </v-list-item-content>
                        <v-list-item-action class="align-self-center">
                            <v-list-item-action-text v-text="">{{item.created_at | humanDiff()}}</v-list-item-action-text>
                            <div class="d-flex">
                                <v-icon
                                    class="d-inline-flex"
                                    @click="markAsRead(item.id)"
                                    color="grey lighten-1"
                                >
                                    mdi-eye
                                </v-icon>
                                <v-icon
                                    class="d-inline-flex"
                                    @click="deleteNotification(item.id)"
                                    color="red darken-3"
                                >
                                    mdi-delete
                                </v-icon>
                            </div>

                        </v-list-item-action>
                    </v-list-item>
                </div>
                </v-list-item-group>
            </v-list>

            <div class="text-center py-2">
                    <v-btn @click="$router.push({name: 'my-notifications', params: { id: 'abc123' },})" small>See all</v-btn>
            </div>
        </v-card>
    </v-menu>
</template>

<script>
/*
|---------------------------------------------------------------------
| Toolbar Notifications Component
|---------------------------------------------------------------------
|
| Quickmenu to check out notifications
|
*/
import {mapGetters} from "vuex";

export default {
    data() {
        return {
            notifications: [],
        }
    },
    mounted() {
        Echo.private('users.'+ this.user.id)
            .notification(() => {
                this.getNotifications()
            });


        this.getNotifications()
    }
    ,
    methods: {
        getNotifications() {
            try {
                axios.get('/api/account/notification').then((data) => {
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
        goToNotification(id) {
            try {
                axios.get('/api/account/notification/markasread/' + id).then(() => {
                    this.getNotifications();
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
        markAsRead(id) {
            try {
                axios.get('/api/account/notification/markasread/' + id).then(() => {
                    this.getNotifications();
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
        markAllAsRead() {
            try {
                axios.get('/api/account/notification/allasread').then(() => {
                    this.getNotifications();
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
                    this.getNotifications();
                }).catch(err => {
                    if (err.response.status === 404) {
                        throw new Error(`${err.config.url} not found`);
                    }
                    throw err;
                })
            } catch (error) {
                console.warn(error)
            }
        }
    },
    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            user: 'auth/user',
        })
    }
}
</script>
