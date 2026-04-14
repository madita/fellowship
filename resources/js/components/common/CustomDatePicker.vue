<template>
    <div class="custom-date-picker v-input v-input--is-label-active v-input--is-dirty v-input--is-focused v-input--has-state" :class="{'v-input--error':localError}">
        <div class="v-input__control">
            <div class="v-input__slot">
                <label :for="id" class="v-label" :class="{ 'v-label--active': isFocused || localModelValue }">{{ label }}</label>
                <VueDatePicker
                    :locale="userLocale"
                    :id="id"
                    v-model="localModelValue"
                    :class="{'v-input--error':localError}"
                    :enable-time-picker="!localAllDay"
                    :timezone="userTimezone"
                    :min-date="minDate"
                    :max-date="maxDate"
                    :minutes-increment="15"
                    :hours-increment="1"
                    :is-24="is24HourFormat"
                    :must-show-time-select="!localAllDay"
                    time-picker-inline
                    :format="dateFormat"
                    :preview-format="dateFormat"
                    @focus="isFocused = true"
                    @blur="isFocused = false"
                    auto-apply
                    @update:model-value="handleModelUpdate"
                    class="v-text-field__slot"
                />
            </div>
            <div class="v-input__details">
                <div class="v-messages theme--light">
                    <div class="v-messages__wrapper">
                        <div v-if="localError" class="v-messages__message" role="alert">{{ errorMessages[0] }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import {ref, computed, watch} from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { useUserStore } from '@/store/userStore.js';
import { useSettingsStore } from '@/store/settingStore.js';

// Stores for user preferences
const userStore = useUserStore();
const settingsStore = useSettingsStore();

const props = defineProps({
    modelValue: [Date, String],
    label: String,
    id: String,
    allDay: Boolean,
    error: Boolean,
    errorMessages: Array,
    minDate: [Date, String],
    maxDate: [Date, String],
    isEndDate: {
        type: Boolean,
        default: false
    },
    format: {
        type: String,
        default: null // Will use user preference if not specified
    }
});

const emit = defineEmits(['update:modelValue', 'date-selected']);
const isFocused = ref(false);
const localModelValue = ref(props.modelValue);
const localError = ref(props.error);
const localAllDay = ref(props.allDay);

// User timezone preference
const userTimezone = computed(() => {
    return userStore.user?.timezone || settingsStore.appSettings?.default_timezone || 'UTC';
});

// User locale preference
const userLocale = computed(() => {
    const lang = userStore.user?.language || settingsStore.appSettings?.default_language || 'en';
    const localeMap = {
        'en': 'en-US',
        'de': 'de-DE',
        'es': 'es-ES',
        'fr': 'fr-FR',
    };
    return localeMap[lang] || 'en-US';
});

// User date format preference
const userDateFormatPHP = computed(() => {
    return userStore.user?.date_format || settingsStore.appSettings?.date_format || 'Y-m-d';
});

// User time format preference
const userTimeFormat = computed(() => {
    return userStore.user?.time_format || settingsStore.appSettings?.time_format || 'H:i:s';
});

// Check if 24-hour format (PHP 'H' = 24h, 'h' = 12h)
const is24HourFormat = computed(() => {
    return userTimeFormat.value.includes('H');
});

// Convert PHP date format to vue-datepicker format
const userDateFormatPicker = computed(() => {
    const phpFormat = userDateFormatPHP.value;
    const formatMap = {
        'Y-m-d': 'yyyy-MM-dd',
        'd/m/Y': 'dd/MM/yyyy',
        'm/d/Y': 'MM/dd/yyyy',
        'd.m.Y': 'dd.MM.yyyy',
    };
    return formatMap[phpFormat] || 'yyyy-MM-dd';
});

// Date format for the picker - use prop if provided, otherwise user preference
const dateFormat = computed(() => {
    const baseFormat = props.format || userDateFormatPicker.value;
    const timeFormat = is24HourFormat.value ? 'HH:mm' : 'hh:mm a';
    return localAllDay.value ? baseFormat : `${baseFormat} ${timeFormat}`;
});

// Handle model update
const handleModelUpdate = (newVal) => {
    // If this is a new date selection and time picking is enabled
    if (newVal && !localAllDay.value) {
        // Round to the next hour if this is a new selection
        const date = new Date(newVal);

        // Only adjust time if it's likely a new selection (not just a time adjustment)
        // We detect this by checking if the previous value was null or
        // if the date part has changed (ignoring time)
        const isNewSelection = !localModelValue.value ||
            (date.getDate() !== new Date(localModelValue.value).getDate() ||
                date.getMonth() !== new Date(localModelValue.value).getMonth() ||
                date.getFullYear() !== new Date(localModelValue.value).getFullYear());

        if (isNewSelection) {
            // Round to the next full hour
            const currentMinutes = date.getMinutes();
            const currentSeconds = date.getSeconds();

            // If we're not already at a full hour, advance to the next one
            if (currentMinutes > 0 || currentSeconds > 0) {
                date.setHours(date.getHours() + 1);
                date.setMinutes(0, 0, 0); // Reset minutes, seconds, milliseconds
            }

            newVal = date;
        }
    }

    localModelValue.value = newVal;
    emit('update:modelValue', newVal);
    emit('date-selected', newVal); // Emit a custom event when date is selected
}

// Watch localModelValue to check if a valid date is set
watch(localModelValue, (newVal) => {
    // Check if newVal is a valid date
    if (newVal && !isNaN(new Date(newVal).getTime())) {
        localError.value = false; // Reset error if the date is valid
    } else if (!newVal) {
        localError.value = props.error; // Set to default error state if no date is set
    }
});

// Watch for external changes to the modelValue prop
watch(() => props.modelValue, (newVal) => {
    localModelValue.value = newVal;
});

watch(() => props.error, (newVal) => {
    localError.value = newVal;
});

watch(() => props.allDay, (newAllDay) => {
    localAllDay.value = newAllDay;
}, {deep: true, immediate: true});

</script>

<style scoped>
.v-input__control {
    display: block;
    grid-area: initial;
}


.custom-date-picker .v-messages__message {
    font-size: 0.75rem;
    margin-top: 4px;

}

.v-label--active {
    transform: translateY(-125%) scale(0.75);
    color: rgba(0, 0, 0, 0.54); /* Vuetify label color when active */
}


.v-input__slot {
    position: relative;
    padding-top: 5px; /* Space for floating label */
    background-color: rgba(233, 236, 236, 1)!important;
}

.v-label {
    position: absolute;
    left: 33px;
    top: 18px;
    cursor: text;
    transition: font 0.15s cubic-bezier(0.4, 0.0, 0.2, 1), transform 0.15s cubic-bezier(0.4, 0.0, 0.2, 1);
}

.v-label--active {
    top: 24px;
    transform: translateY(-100%) scale(0.75);
    transform-origin: left;
    color: #000;
}

.v-input__details {
    padding-top: 4px;
    padding-inline: 16px;
}

.v-input--error {
    color: red!important;
}

</style>

<style>

.custom-date-picker {
    margin-top: 2px; /* Adjust based on your form layout */

    .dp__input {
        background-color: transparent!important; /* Ensuring background color is set */
        border: none!important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.42) !important;
        border-radius: 0!important;
        font-family: "Roboto", sans-serif; /* Match Vuetify's typography */
        font-size: 16px; /* Ensuring font size matches Vuetify inputs */
        width: 100%; /* Full width */
        box-sizing: border-box; /* Box-sizing border box */
        letter-spacing: .009375em;
        min-height: 50px;
        min-width: 0;
        opacity: 1;
        padding-inline: 32px 16px;
        padding-bottom: 4px;
        padding-top: 20px;
    }

    .v-input--error .dp__input {
        border-bottom: 1px solid red !important;
    }
}

</style>
