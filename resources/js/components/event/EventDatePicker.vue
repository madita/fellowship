<template>
    <v-container>
        <v-row>
            <!-- Date Picker -->

                <VueDatePicker
                    :locale="userLocale"
                    v-model="dateValue"
                    :enable-time-picker="false"
                    range
                    :timezone="userTimezone"
                    inline
                    auto-apply
                    :preview-format="format"
                    :format="dateFormat"
                    @update:modelValue="updateDate"
                />


            <!-- Start Time Picker -->

                <VueDatePicker
                    :locale="userLocale"
                    v-model="startTime"
                    :time-picker="true"
                    :is-24="is24HourFormat"
                    :timezone="userTimezone"
                    :enable-date-picker="false"
                    @update:modelValue="updateStartTime"
                />


            <!-- End Time Picker -->

                <VueDatePicker
                    :locale="userLocale"
                    v-model="endTime"
                    :time-picker="true"
                    :is-24="is24HourFormat"
                    :timezone="userTimezone"
                    :enable-date-picker="false"
                    @update:modelValue="updateEndTime"

                />

        </v-row>
    </v-container>
</template>

<script setup>
import {ref, watch, computed, defineProps, defineEmits} from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useUserStore } from '@/store/userStore.js';
import { useSettingsStore } from '@/store/settingStore.js';

// Stores
const userStore = useUserStore();
const settingsStore = useSettingsStore();

// Define props and emits
const props = defineProps({
    modelValue: Object,
});

const emit = defineEmits(['update:modelValue']);

// Computed user preferences
const userTimezone = computed(() => {
    return userStore.user?.timezone || settingsStore.appSettings?.default_timezone || 'UTC';
});

const userLocale = computed(() => {
    // Map language codes to locale codes for vue-datepicker
    const lang = userStore.user?.language || settingsStore.appSettings?.default_language || 'en';
    const localeMap = {
        'en': 'en-US',
        'de': 'de-DE',
        'es': 'es-ES',
        'fr': 'fr-FR',
    };
    return localeMap[lang] || 'en-US';
});

const userDateFormat = computed(() => {
    return userStore.user?.date_format || settingsStore.appSettings?.date_format || 'Y-m-d';
});

const userTimeFormat = computed(() => {
    return userStore.user?.time_format || settingsStore.appSettings?.time_format || 'H:i:s';
});

// Check if 24-hour format
const is24HourFormat = computed(() => {
    const timeFormat = userTimeFormat.value;
    // PHP 'H' is 24-hour, 'h' is 12-hour
    return timeFormat.includes('H');
});

// Convert PHP date format to vue-datepicker format
const dateFormat = computed(() => {
    const phpFormat = userDateFormat.value;
    const formatMap = {
        'Y-m-d': 'yyyy-MM-dd',
        'd/m/Y': 'dd/MM/yyyy',
        'm/d/Y': 'MM/dd/yyyy',
        'd.m.Y': 'dd.MM.yyyy',
    };
    return formatMap[phpFormat] || 'yyyy-MM-dd';
});

// Reactive state
const dateValue = ref(props.modelValue?.date || null);
const startTime = ref(props.modelValue?.startTime || null);
const endTime = ref(props.modelValue?.endTime || null);

const format = (date) => {
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();

    // Use user's date format preference
    const phpFormat = userDateFormat.value;
    if (phpFormat === 'd/m/Y' || phpFormat === 'd.m.Y') {
        const sep = phpFormat === 'd/m/Y' ? '/' : '.';
        return `${day}${sep}${month}${sep}${year}`;
    } else if (phpFormat === 'm/d/Y') {
        return `${month}/${day}/${year}`;
    }
    return `${year}-${month}-${day}`;
}

// Methods to handle updates
const updateDate = (newDate) => {
    emitUpdate({date: newDate, startTime: startTime.value, endTime: endTime.value});
};

const updateStartTime = (newStartTime) => {
    emitUpdate({date: dateValue.value, startTime: newStartTime, endTime: endTime.value});
};

const updateEndTime = (newEndTime) => {
    emitUpdate({date: dateValue.value, startTime: startTime.value, endTime: newEndTime});
};

const emitUpdate = (value) => {
    emit('update:modelValue', value);
};

// Watch for external updates
watch(() => props.modelValue, (newModelValue) => {
    dateValue.value = newModelValue?.date || null;
    startTime.value = newModelValue?.startTime || null;
    endTime.value = newModelValue?.endTime || null;
});
</script>
