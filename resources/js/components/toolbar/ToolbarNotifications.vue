<template>
    <v-menu offset-y left transition="slide-y-transition">
        <template v-slot:activator="{ props }">
            <v-badge
                :content="notifications.length"
                :value="!!notifications.length"
                offset-x="22"
                offset-y="22"
                v-bind="props"
            >
                <v-btn>
                    <v-icon>mdi-bell-outline</v-icon>
                </v-btn>
            </v-badge>
        </template>

        <!-- user menu list -->
        <v-card
            class="mx-auto"
            min-width="300"
            max-width="450"
        >
<!--                            <v-toolbar color="">-->
<!--&lt;!&ndash;                                <v-btn variant="text" icon="mdi-menu"></v-btn>&ndash;&gt;-->

<!--                                <v-toolbar-title>Notifications</v-toolbar-title>-->

<!--                                <v-spacer></v-spacer>-->

<!--                                <v-btn variant="text" icon="mdi-magnify"></v-btn>-->
<!--                            </v-toolbar>-->
            <template v-if="notifications.length == 0">
                <div class="text-center mt-1">
                    No new (unread) Notifiations
                </div>
            </template>
            <v-list>
                <v-list-item
                    v-for="(item, index) in notifications"
                    :key="index"
                    :title="item.data.subject"
                    :subtitle="item.data.body"
                >
                    <template v-slot:prepend>
                        <v-avatar>
                            <v-icon color="primary">mdi-noodles</v-icon>
                        </v-avatar>
                    </template>

                    <template v-slot:append>
                        <v-btn
                            color="grey-lighten-1"
                            icon="mdi-eye"
                            variant="text"
                            @click="markAsRead(item.id)"
                        ></v-btn>

                        <v-btn
                            color="red darken-3"
                            icon="mdi-delete"
                            variant="text"
                            @click="deleteNotification(item.id)"
                        ></v-btn>


                    </template>

<!--                    <v-list-item-action>-->
<!--                                                        {{ $formatDistanceToNow(item.created_at) }}-->
<!--                                                        <div class="d-flex">-->
<!--                                                            <v-btn icon @click="markAsRead(item.id)">-->
<!--                                                                <v-icon color="grey lighten-1">mdi-eye</v-icon>-->
<!--                                                            </v-btn>-->
<!--                                                            <v-btn icon @click="deleteNotification(item.id)">-->
<!--                                                                <v-icon color="red darken-3">mdi-delete</v-icon>-->
<!--                                                            </v-btn>-->
<!--                                                        </div>-->
<!--                                                    </v-list-item-action>-->
                </v-list-item>
            </v-list>

            <div class="text-center py-2">
                <v-btn @click="$router.push({name: 'my-notifications', params: { id: 'abc123' }})" text small>See all</v-btn>
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
// import {mapGetters} from "vuex";

import {ref, onMounted} from 'vue';
import {useApi} from '@/api/useAPI.js'
import {useAuthStore} from "@/store/authStore.js";
import {useUserStore} from "@/store/userStore.js";
import UserAvatar from "@/components/common/UserAvatar.vue";

export default {
    components: {UserAvatar},
    setup() {
        const notifications = ref([]);
        const authStore = useAuthStore();
        const userStore = useUserStore();
        const api = useApi();

        const getNotifications = async () => {
            try {
                const data = await api.get('/account/notification');
                notifications.value = data.data;
            } catch (error) {
                console.warn(error);
            }
        }

        // Other methods...
        const goToNotification = async (id) => {
            try {
                api.get('/account/notification/markasread/' + id).then(() => {
                    getNotifications();
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

        const markAsRead = async (id) => {
            console.log('markasread',id)
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
        }

        const markAllAsRead = async () => {
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
        }

        const deleteNotification = async (id) => {
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

        onMounted(() => {
            Echo.private('users.' + userStore.user.id)
                .notification(() => {
                    getNotifications()
                });
            getNotifications();
        });

        return {
            notifications,
            user: userStore.user,
            authenticated: authStore.isLoggedIn,
            markAllAsRead,
            markAsRead,
            getNotifications,
            deleteNotification,
        }
    }
}


// import {useAuthStore} from "@/store/authStore.js";
// import {useUserStore} from "@/store/userStore.js";
//
// import { useApi } from '@/api/useAPI.js'
// const api = useApi()
//
//
// export default {
//     data() {
//         return {
//             notifications: [],
//         }
//     },
//     mounted() {
//         Echo.private('users.'+ this.user.id)
//             .notification(() => {
//                 this.getNotifications()
//             });
//
// console.log('notifivationsuser',this.user)
//         this.getNotifications()
//     }
//     ,
//     methods: {
//         getNotifications() {
//             try {
//                 api.get('/account/notification').then((data) => {
//                     this.notifications = data.data
//                 }).catch(err => {
//                     if (err.response.status === 404) {
//                         throw new Error(`${err.config.url} not found`);
//                     }
//                     throw err;
//                 })
//             } catch (error) {
//                 console.warn(error)
//             }
//         },
//         goToNotification(id) {
//             try {
//                 api.get('/account/notification/markasread/' + id).then(() => {
//                     this.getNotifications();
//                 }).catch(err => {
//                     if (err.response.status === 404) {
//                         throw new Error(`${err.config.url} not found`);
//                     }
//                     throw err;
//                 })
//             } catch (error) {
//                 console.warn(error)
//             }
//         },
//         markAsRead(id) {
//             try {
//                 axios.get('/api/account/notification/markasread/' + id).then(() => {
//                     this.getNotifications();
//                 }).catch(err => {
//                     if (err.response.status === 404) {
//                         throw new Error(`${err.config.url} not found`);
//                     }
//                     throw err;
//                 })
//             } catch (error) {
//                 console.warn(error)
//             }
//         },
//         markAllAsRead() {
//             try {
//                 axios.get('/api/account/notification/allasread').then(() => {
//                     this.getNotifications();
//                 }).catch(err => {
//                     if (err.response.status === 404) {
//                         throw new Error(`${err.config.url} not found`);
//                     }
//                     throw err;
//                 })
//             } catch (error) {
//                 console.warn(error)
//             }
//         },
//         deleteNotification(id) {
//             try {
//                 axios.delete('/api/account/notification/delete/' + id).then(() => {
//                     this.getNotifications();
//                 }).catch(err => {
//                     if (err.response.status === 404) {
//                         throw new Error(`${err.config.url} not found`);
//                     }
//                     throw err;
//                 })
//             } catch (error) {
//                 console.warn(error)
//             }
//         }
//     },
//     computed: {
//         authenticated() {
//             const authStore = useAuthStore();
//             return authStore.isLoggedIn;
//         },
//         user() {
//             const userStore = useUserStore();
//             console.log(userStore);
//             return userStore.user;
//         },
//     }
// }
</script>
