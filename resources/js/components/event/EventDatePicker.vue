<template>
    <v-container>
        <v-row>
            <!-- Date Picker -->

                <VueDatePicker
                    locale="de"
                    v-model="dateValue"
                    :enable-time-picker="false"
                    range
                    utc
                    inline
                    auto-apply
                    :preview-format="format"
                    @update:modelValue="updateDate"
                />


            <!-- Start Time Picker -->

                <VueDatePicker
                    locale="de"
                    v-model="startTime"
                    :time-picker="true"
                    :enable-date-picker="false"
                    @update:modelValue="updateStartTime"
                />


            <!-- End Time Picker -->

                <VueDatePicker
                    locale="de"
                    v-model="endTime"
                    :time-picker="true"
                    :enable-date-picker="false"
                    @update:modelValue="updateEndTime"

                />

        </v-row>
    </v-container>
</template>

<script setup>
import {ref, watch, defineProps, defineEmits} from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

// Define props and emits
const props = defineProps({
    modelValue: Object,
});

const emit = defineEmits(['update:modelValue']);

// Reactive state
const dateValue = ref(props.modelValue?.date || null);
const startTime = ref(props.modelValue?.startTime || null);
const endTime = ref(props.modelValue?.endTime || null);

const format = (date) => {
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();

    return `${day}.${month}.${year}`;
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
