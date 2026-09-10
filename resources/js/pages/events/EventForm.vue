<template>
    <div class="flex-grow-1">
        <page-header
            :title="form"
            icon="mdi-calendar-edit"
            :back-to="{ name: 'events' }"
        />

        <v-container>
            <v-row>
                <v-col cols="12" md="3">
                    <EventDatePicker v-model="event.date"></EventDatePicker>
                </v-col>
                <v-col cols="12" md="9">
                    <v-text-field :label="t('common.title')" v-model="event.title" :disabled="saving"></v-text-field>
                    <Tiptap v-model="event.description" />
                </v-col>
            </v-row>
            <div class="d-flex justify-end mt-4">
                <v-btn color="primary" variant="elevated" :loading="saving" @click="save">{{ form }}</v-btn>
            </div>
        </v-container>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
// import VueDatePicker from '@vuepic/vue-datepicker';
// import '@vuepic/vue-datepicker/dist/main.css';
import EventDatePicker from './EventDatePicker.vue'
import Tiptap from "@/components/common/tiptap/Tiptap.vue";
import { useRoute } from 'vue-router';
import axios from 'axios';
import { useDialog } from '@/composables/useDialog.js';

const { t } = useI18n();
const dialog = useDialog();
const date = ref();

const route = useRoute();

// Assuming event is reactive
const event = ref({title: "", description: "", date:null});
const form = ref(t('events.create'));
const saving = ref(false);
const id = ref(route.params.id);

// Convert methods to setup style
const save = async () => {
    if (saving.value) return;
    saving.value = true;
    try {
        if (id.value) {
            await updateEvent();
        } else {
            await storeEvent();
        }
    } catch (error) {
        await dialog.requestError(error, t('events.saveError'));
    } finally {
        saving.value = false;
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

const updateEvent = async () => {
    await axios.patch(`${endpoint}/${id.value}`, event.value);
    saving.value = false;
    await dialog.success(t('events.eventUpdated'));
};

const storeEvent = async () => {
    await axios.post(`${endpoint}`, event.value);
    saving.value = false;
    await dialog.success(t('events.eventCreated'));
};

// Example of using onMounted
onMounted(() => {
    if (id.value) {
        form.value = t('events.update');
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
