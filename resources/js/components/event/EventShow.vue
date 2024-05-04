<template>
    <div class="flex-grow-1 event-show">

        <v-skeleton-loader
            v-if="isLoading"
            class="ma-10"
            type="card-avatar, article, actions"
        ></v-skeleton-loader>

        <template v-else>
            <v-sheet
                class="event-hero"
                color="primary"
                width="100%"
                height="50vh"
            >

                <v-container class="fill-height pa-9">
                    <v-row>
                        <v-col><span>
                            {{ $formatDate(event.startDate, 'dd.mm.yyyy') }}
                             at {{ event.startTime }} - {{
                            $formatDate(event.endDate, 'dd.mm.yyyy')
                            }} at {{ event.endTime }}</span>

                            <h1 class="event-title">{{ event.title }}</h1></v-col>
                    </v-row>
                </v-container>

            </v-sheet>

            <v-container>
                <div class="calendar-day">
                    <div class="calendar-day-top"></div>
                    <div class="calendar-day-bottom">{{ $formatDate(event.startDate, 'd') }}</div>
                </div>

                <v-row justify="center">
                    <v-col cols="8">
                        <div v-html="event.description"></div>
                    </v-col>
                    <v-col cols="4" class="align-content-end">
                        <div>Are you coming?</div>
                        <v-btn
                            color="primary"
                            class="me-3"
                            :disabled="getIsGoing('going')"
                            @click="register('going')"
                        >
                            Yes
                        </v-btn>
                        <v-btn
                            variant="tonal"
                            color="primary"
                            class="me-3"
                            :disabled="getIsGoing('notgoing')"
                            @click="register('notgoing')"
                        >
                            No
                        </v-btn>
                        <v-btn
                            variant="outlined"
                            color="secondary"
                            :disabled="getIsGoing('maybe')"
                            @click="register('maybe')"
                        >
                            Maybe
                        </v-btn>


                    <v-list-subheader>Is Going ({{ eventData.going.length }})</v-list-subheader>
                    <user-avatar v-for="user in eventData.going" :key="`going-${user.id}`" :user="user"></user-avatar>
                    <v-list-subheader>Maybe Going ({{ eventData.maybe.length }})</v-list-subheader>
                    <user-avatar v-for="user in eventData.maybe" :key="`maybe-${user.id}`" :user="user"></user-avatar>
                    <v-list-subheader>Not Going ({{ eventData.notgoing.length }})</v-list-subheader>
                    <user-avatar v-for="user in eventData.notgoing" :key="`notgoing-${user.id}`" :user="user"></user-avatar>

                    </v-col>
                </v-row>
            </v-container>
        </template>

    </div>
</template>

<script>

import EventDatePicker from "./EventDatePicker.vue";
import UserAvatar from "../common/UserAvatar.vue";
import {formatDate} from "date-fns";

export default {
    components: {
        EventDatePicker,
        UserAvatar
    },
    data() {
        return {
            isLoading: true,
            eventData: null,
            event: {title: "", description: ""},
            endpoint: '/api/events',
            id: null,
            message: ""
        }
    },
    methods: {
        formatDate,
        getEvent() {
            this.isLoading = true
            return axios.get(`/api/events/${this.id}`).then((response) => {
                this.eventData = response.data;
                this.event = response.data.event;

                this.isLoading = false
            });
        },
        register(answer) {
            axios.get(`${this.endpoint}/${this.id}/going/${answer}`).then((response) => {
                this.eventData.isGoing.type = answer;
                this.eventData.going = response.data.going
                this.eventData.notgoing = response.data.notgoing
                this.eventData.maybe = response.data.maybe

                this.message = "Answer saved"
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        },
        getIsGoing(answer) {
            if (this.eventData === null) {
                return false;
            }
            return this.eventData.isGoing !== undefined && this.eventData.isGoing.type === answer
        },
    },
    created() {
        if (this.$route.params.id) {
            this.id = this.$route.params.id;
            this.getEvent();
        }
    }
}
</script>

<style>
.event-show {

}

.event-hero {
    position: relative;
    font-size: large;
    color: whitesmoke !important;
}

.event-hero h1 {
    display: block;
    font-size: 6rem;
    color: white !important;
}

.calendar-day {
    position: relative;
    top: -3rem;
    width: 80px;
    height: 80px;
    box-shadow: inset 0 -3em 3em rgba(0, 0, 0, 0.1),
    0 0 0 0 rgb(255, 255, 255),
    0.3em 0.3em 1em rgba(0, 0, 0, 0.3);
    margin-bottom: -3rem;
}

.calendar-day-top {
    width: 80px;
    height: 20px;
    background: #941024;
}

.calendar-day-bottom {
    background-color: white;
    color: #1a202c;
    width: 80px;
    height: 60px;
    border-left: 1px solid #a2a2a2;
    border-right: 1px solid #a2a2a2;
    border-bottom: 1px solid #a2a2a2;
    text-align: center;
    justify-content: center;
    font-size: 3rem;
    font-weight: 600;
}
</style>
