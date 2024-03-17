<template>
    <div class="d-flex flex-column flex-grow-1">

        <v-container fluid>
        <div class="d-flex align-center py-3">
            <div>
                <div class="display-1">My Notifications</div>
                <v-breadcrumbs :items="breadcrumbs" class="pa-0 py-2" />
            </div>
            <v-spacer />
        </div>
        <v-app-bar dark>
            <v-app-bar-title>Notifications</v-app-bar-title>
            <v-spacer />
            <v-btn icon>
                <v-icon>mdi-magnify</v-icon>
            </v-btn>
        </v-app-bar>
<!--        <v-card>-->



            <v-card v-model="notificationModel"
                    v-for="(item) in notifications" :key="item.title"
                class="mx-auto my-1 w-100"
                :title="item.data.subject"
            >


                <v-card-text class="text-h5 py-2" v-html="item.data.body">
                </v-card-text>
                <v-btn :href="item.data.url" target="_blank" variant="flat" small>{{item.data.action}}</v-btn>

                <v-card-actions>
                    <v-list-item class="w-100">
                        <template v-slot:prepend>
                            <user-avatar :user="item.data.notifier"></user-avatar>
                        </template>

                        <v-list-item-title>{{ item.data.notifier.username }}</v-list-item-title>

                        <v-list-item-subtitle>{{ $formatDistanceToNow(item.created_at) }}</v-list-item-subtitle>

                        <template v-slot:append>
                            <div class="justify-self-end">
                                <v-icon @click="deleteNotification(item.id)" color="red darken-3">mdi-delete</v-icon>
                            </div>
                        </template>
                    </v-list-item>
                </v-card-actions>
            </v-card>


<!--            <v-list three-line v-model="notificationModel">-->

<!--                    <v-list-item v-for="(item) in notifications" :key="item.title">-->

<!--                        <user-avatar :user="item.data.notifier"></user-avatar>-->
<!--                        <v-list-item-title>-->
<!--                                {{ item.data.subject }} - {{ item.data.notifier.username }}-->
<!--                            </v-list-item-title>-->
<!--                            <v-list-item-subtitle v-html="item.data.body"></v-list-item-subtitle>-->
<!--                            <v-btn :href="item.data.url" target="_blank" variant="flat" small>{{item.data.action}}</v-btn>-->
<!--                        <v-list-item-action>-->
<!--                            {{ $formatDistanceToNow(item.created_at) }}-->
<!--                            <v-icon @click="deleteNotification(item.id)" color="red darken-3">mdi-delete</v-icon>-->
<!--                        </v-list-item-action>-->
<!--                    </v-list-item>-->

<!--            </v-list>-->
<!--        </v-card>-->
        </v-container>
    </div>
</template>

<script>
import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import UserAvatar from "@/components/common/UserAvatar.vue";

export default {
    components: {UserAvatar},
    props: {
        id: {
            required: false,
            type: [String, Number]
        }
    },
    data: () => ({
        breadcrumbs: [{
            text: 'Account',
            disabled: false,
            href: '#'
        }, {
            text: 'Notifications'
        }]
    }),
    setup(props) {
        const notificationModel = ref(null);
        const isLoading = ref(false);
        const notifications = ref({});
        const route = useRoute();


        // Watch for changes in route params
        watch(() => route.params.id, (newId) => {
            notificationModel.value = newId;
        });

        // Load all notifications on mount
        onMounted(() => {
            if (route.params.id) {
                notificationModel.value = route.params.id;
            }
            getAllNotifications();
        });

        const getAllNotifications = async () => {
            // your axios logic here
            try {
                axios.get('/api/account/notifications').then((data) => {
                    notifications.value = data.data
                }).catch(err => {
                    if (err.response.status === 404) {
                        throw new Error(`${err.config.url} not found`);
                    }
                    throw err;
                })
            } catch (error) {
                console.warn(error)
            }
        };

        const deleteNotification = async (id) => {
            try {
                axios.delete('/api/account/notification/delete/' + id).then(() => {
                    getAllNotifications();
                }).catch(err => {
                    if (err.response.status === 404) {
                        throw new Error(`${err.config.url} not found`);
                    }
                    throw err;
                })
            } catch (error) {
                console.warn(error)
            }
        };

        return {
            notificationModel,
            isLoading,
            notifications,
            getAllNotifications,
            deleteNotification
        };
    }
}

// export default {
//     components: {
//
//     },
//     // props() {
//     //     id: {
//     //         required: false
//     //     }
//     // },
//     data() {
//         return {
//             notificationModel:null,
//             isLoading: false,
//             breadcrumbs: [{
//                 text: 'Account',
//                 disabled: false,
//                 href: '#'
//             }, {
//                 text: 'Notifications'
//             }],
//
//             searchQuery: '',
//             selectedNotifications: [],
//             notifications: {}
//         }
//     },
//     watch: {
//         '$route' () {
//             this.notificationModel = this.$route.params.id;
//         },
//         selectedNotifications(val) {
//
//         }
//     },
//     methods: {
//         getAllNotifications() {
//             try {
//                 axios.get('/api/account/notifications').then((data) => {
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
//         deleteNotification(id) {
//             try {
//                 axios.delete('/api/account/notification/delete/' + id).then(() => {
//                     this.getAllNotifications();
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
//         searchNotifications() {
//         },
//         open() {
//         }
//     },
//     mounted() {
//         if(this.$route.params.id) {
//             this.notificationModel = this.$route.params.id;
//         }
//         this.getAllNotifications();
//     }
// }
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
