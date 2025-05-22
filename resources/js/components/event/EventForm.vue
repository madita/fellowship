<template>
    <div class="flex-grow-1">
        <v-container>
            <v-row>
                <v-col cols="3">
                    <EventDatePicker v-model="event.date"></EventDatePicker>
<!--                    <VueDatePicker v-model="event.date" :range="{ partialRange: false }" />-->
                </v-col>
                <v-col cols="9">
                    <v-text-field label="Title" v-model="event.title"></v-text-field>
                    <!-- Notice the updated v-model usage -->
                    <Tiptap v-model="event.description" />
                </v-col>
            </v-row>
            <v-btn @click="save">{{ form }}</v-btn>
        </v-container>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
// import VueDatePicker from '@vuepic/vue-datepicker';
// import '@vuepic/vue-datepicker/dist/main.css';
import EventDatePicker from './EventDatePicker.vue'
import Tiptap from "@/components/common/tiptap/Tiptap.vue";
import { useRoute } from 'vue-router';
import axios from 'axios';
const date = ref();

const route = useRoute();

// Assuming event is reactive
const event = ref({title: "", description: "", date:null});
const form = ref("Create");
const message = ref("");
const id = ref(route.params.id);

// Convert methods to setup style
const save = () => {
    if (id.value) {
        updateEvent();
    } else {
        storeEvent();
    }
};

const endpoint = '/api/events';

// const getEvent = () => {
//     this.loading = true
//     return axios.get(`/api/events/${this.id}/edit`).then((response) => {
//         this.event = response.data.data.event
//
//         this.loading = false
//     });
// }

const updateEvent = () => {
    // Implement update logic here
    axios.patch(`${endpoint}/${id.value}`, this.page).then(() => {
        message.value = "Event updated"
    }).catch((error) => {
        if (error.response.status === 422) {
            this.editing.errors = error.response.data
        }
    })
};

const storeEvent = () => {
    // Implement store logic here
    console.log('event',event)
    axios.post(`${endpoint}`, event.value).then(() => {
        // this.page = {title: "", body: ""};
        // this.message = "Page saved ..link"
    }).catch((error) => {
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            this.editing.errors = error.response.data
        }
    })
};

// Example of using onMounted
onMounted(() => {
    if (id.value) {
        form.value = "Update";
        getEvent();
    }
});

const getEvent = async () => {
    try {
        const response = await axios.get(`/api/events/${id.value}/edit`);
        event.value = response.data.data.event;
    } catch (error) {
        console.error(error);
    }
};
</script>
