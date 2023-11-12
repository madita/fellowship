<template>
    <div class="d-flex flex-grow-1 flex-column">
<!--        <v-row class="flex-grow-0" dense>-->
<!--            <v-col cols="12">-->
<!--                Lorem ipsum...-->
<!--            </v-col>-->
<!--        </v-row>-->
        <v-container fluid>
        <v-row>
            <v-col cols="12" md="6" lg="4">
                <v-card>
                    <v-card-text>
                        <div class="headline mb-2">Upcoming Events</div>
                        <div class="caption mb-2">You have {{ eventsCount }} upcoming events.</div>
                        <v-divider></v-divider>
                        <div class="caption mt-2">Next event:</div>
                        <div class="subtitle-1">{{ nextEvent.title }}</div>
                        <div class="caption">{{ nextEvent.date }}</div>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn color="primary" to="/events">View All Events</v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
            <v-col cols="12" md="6" lg="4">
                <v-card>
                    <v-card-text>
                        <div class="headline mb-2">Recent Activity</div>
                        <div class="caption mb-2">Here are the most recent activities in your community.</div>
                        <v-divider></v-divider>
                        <v-list dense>
                            <v-list-item v-for="(activity, index) in recentActivity" :key="index">

                                    <v-icon>{{ activity.icon }}</v-icon>

                                    <div>{{ activity.title }}</div>
                                    <div class="caption">{{ activity.date }}</div>

                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn color="primary"  to="/activity">View All Activity</v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
            <v-col cols="12" lg="4">
                <v-card>
                    <v-card-text>
                        <div class="headline mb-2">New Members</div>
                        <div class="caption mb-2">You have {{ newMembersCount }} new members.</div>
                        <v-divider></v-divider>
                        <v-list dense>
                            <v-list-item v-for="(member, index) in newMembers" :key="index">

                                    <v-img :src="member.avatar"></v-img>


                                    <div>{{ member.name }}</div>
                                    <div class="caption">{{ member.joinDate }}</div>

                            </v-list-item>
                        </v-list>
                    </v-card-text>
                    <v-card-actions>
                        <v-btn color="primary" to="/members">View All Members</v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>
        </v-container>
    </div>
</template>

<script>
import {useAuthStore} from "@/store/authStore.js";

import {useUserStore} from '@/store/userStore.js'


export default {
    components: {},
    data() {
        return {
            loadingInterval: null,
            isLoading: true,
            eventsCount: 5,
            nextEvent: {
                title: 'Monthly Meeting',
                date: 'May 15, 2023',
            },
            recentActivity: [
                {
                    icon: 'mdi-message-text',
                    title: 'New forum post: "Introducing FellowshipHQ!"',
                    date: '2 hours ago',
                },
                {
                    icon: 'mdi-calendar-clock',
                    title: 'New event: "Community Cleanup"',
                    date: '1 day ago',
                },
                {
                    icon: 'mdi-account-plus',
                    title: 'New member: "John Doe"',
                    date: '3 days ago',
                },
                {
                    icon: 'mdi-account-plus',
                    title: 'New member: "Jane Smith"',
                    date: '5 days ago',
                },
                {
                    icon: 'mdi-message-text',
                    title: 'New forum post: "Getting to know each other"',
                    date: '1 week ago',
                },
            ],
            newMembersCount: 3,
            newMembers: [
                {
                    avatar: 'https://randomuser.me/api/portraits/men/25.jpg',
                    name: 'John Doe',
                    joinDate: 'May 1, 2023',
                },
                {
                    avatar: 'https://randomuser.me/api/portraits/women/25.jpg',
                    name: 'Jane Smith',
                    joinDate: 'May 5, 2023',
                },
                {
                    avatar: 'https://randomuser.me/api/portraits/men/26.jpg',
                    name: 'Mark Johnson',
                    joinDate: 'May 7, 2023',
                },
            ],
        }
    },
    mounted() {
        let count = 0

        // DEMO delay for loading graphics
        this.loadingInterval = setInterval(() => {
            this[`isLoading${count++}`] = false
            if (count === 4) this.clear()
        }, 400)
    },
    beforeDestroy() {
        this.clear()
    },
    computed: {
        user() {
            const userStore = useUserStore();
            // userStore.storeInfo()
            console.log('userStoredashboard',userStore.user)
            return userStore.user;
        },
    },
    methods: {
        clear() {
            clearInterval(this.loadingInterval)
        }
    }
}
</script>
