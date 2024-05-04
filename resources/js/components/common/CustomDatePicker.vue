<template>
    <div class="custom-date-picker v-input v-input--is-label-active v-input--is-dirty v-input--is-focused v-input--has-state" :class="{'v-input--error':localError}">
        <div class="v-input__control">
            <div class="v-input__slot">
                <label :for="id" class="v-label" :class="{ 'v-label--active': isFocused || localModelValue }">{{ label }}</label>
                <VueDatePicker
                    locale="de"
                    :id="id"
                    v-model="localModelValue"
                    :class="{'v-input--error':localError}"
                    :enable-time-picker="!localAllDay"
                    :timezone="tz"
                    @focus="isFocused = true"
                    @blur="isFocused = false"
                    utc
                    auto-apply
                    @input="$emit('update:modelValue', $event)"
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

<!--<VueDatePicker&ndash;&gt;-->
<!--                                    locale="de"-->
<!--                                    v-model="event.end"-->
<!--                                    :class="{ 'error-class': !isStartDateValid }"-->
<!--                                    :enable-time-picker="!event.allDay"-->
<!--                                    minutes-increment="15"-->
<!--                                    @blur="validateEndDate"-->
<!--                                    utc-->
<!--                                    auto-apply-->
<!--                                    :preview-format="format"-->
<!--                                />-->

<script setup>
import {ref, computed, watch} from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    modelValue: Date|String,
    label: String,
    id: String,
    allDay: Boolean,
    error: Boolean,
    errorMessages: Array
});

const emit = defineEmits(['update:modelValue']);
const isFocused = ref(false);
const localModelValue = ref(props.modelValue);
const localError = ref(props.error);
const localAllDay = ref(props.allDay);

const timezone = ref({ timezone: undefined })
const selectedTz = ref(11);

const timezones = [
    { tz: 'Pacific/Midway', offset: -11 },
    { tz: 'America/Adak', offset: -10 },
    { tz: 'Pacific/Gambier', offset: -9 },
    { tz: 'America/Los_Angeles', offset: -8 },
    { tz: 'America/Denver', offset: -7 },
    { tz: 'America/Chicago', offset: -6 },
    { tz: 'America/New_York', offset: -5 },
    { tz: 'America/Santiago', offset: -4 },
    { tz: 'America/Sao_Paulo', offset: -3 },
    { tz: 'America/Noronha', offset: -2 },
    { tz: 'Atlantic/Cape_Verde', offset: -1 },
    { tz: 'UTC', offset: 0 },
    { tz: 'Europe/Berlin', offset: 3 },
    { tz: 'Europe/Brussels', offset: 1 },
    { tz: 'Africa/Cairo', offset: 2 },
    { tz: 'Europe/Minsk', offset: 3 },
    { tz: 'Europe/Moscow', offset: 4 },
    { tz: 'Asia/Tashkent', offset: 5 },
    { tz: 'Asia/Dhaka', offset: 6 },
    { tz: 'Asia/Novosibirsk', offset: 7 },
    { tz: 'Australia/Perth', offset: 8 },
    { tz: 'Asia/Tokyo', offset: 9 },
    { tz: 'Australia/Hobart', offset: 10 },
    { tz: 'Asia/Vladivostok', offset: 11 },
];

const activeTz = computed(() => timezones[selectedTz.value]);

const tz = computed(() => {
    return { ...timezone.value, timezone: activeTz.value.tz };
});

const maxDate = computed(() => {
    const month = getMonth(new Date()) + 1 > 9 ? getMonth(new Date()) + 1 : `0${getMonth(new Date()) + 1}`;
    return `${getYear(new Date())}-${month}-15T01:00:00Z`;
});

// Watch localModelValue to check if a valid date is set
watch(localModelValue, (newVal) => {
    // Check if newVal is a valid date (you can adjust the condition based on your requirements)
    if (newVal && !isNaN(new Date(newVal).getTime())) {
        localError.value = false; // Reset error if the date is valid
    } else if (!newVal) {
        localError.value = props.error; // Set to default error state if no date is set
    }
    emit('update:modelValue', newVal); // Emit update event
});

// Watch for external changes to the modelValue prop
watch(() => props.modelValue, (newVal) => {
    console.log('watch', newVal)
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
