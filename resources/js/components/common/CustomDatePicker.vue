<template>
    <div class="v-input v-text-field v-text-field--is-booted" :class="{ 'v-text-field--focused': isFocused }">
        <div class="v-input__control">
            <div class="v-input__slot">
                <label :for="id" class="v-label v-label--active" v-if="label">{{ label }}</label>
                <VueDatePicker
                    :id="id"
                    :model-value="localModelValue"
                    @focus="isFocused = true"
                    @blur="isFocused = false"
                    @input="$emit('update:modelValue', $event)"
                    class="v-text-field__slot"
                />

            <div v-if="error" class="v-input__details v-input--error"><div class="v-messages" role="alert" aria-live="polite" id="input-34-messages"><div class="v-messages__message">{{ error }}</div></div><!----></div>
            </div>
<!--            <div class="v-text-field__details">-->
<!--                <div class="v-messages">-->
<!--                    <div class="v-messages__wrapper">-->
<!--                        <div v-if="error" class="v-messages__message">{{ error }}</div>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import VueDatePicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';

const props = defineProps({
    modelValue: Date,
    label: String,
    id: String,
    error: String
});

const emit = defineEmits(['update:modelValue']);
const isFocused = ref(false);
const localModelValue = ref(props.modelValue);

// Watch for external changes to the modelValue prop
watch(() => props.modelValue, (newVal) => {
    localModelValue.value = newVal;
});

function handleUpdate(value) {
    localModelValue.value = value;  // Update local value
    emit('update:modelValue', value);  // Emit change to parent
}
</script>

<style scoped>
.v-input__slot {
    position: relative;
    outline: none;
}

.v-text-field__slot {
    padding-top: 20px; /* Adjust based on your field size and label size */
}

.v-messages__message {
    font-size: 0.875rem;
}

.vue-datepicker {
    border: none; /* Remove default borders */
}

.vue-datepicker input {
    border: none;
    font-size: 1rem;
    height: auto;
    outline: none;
}
</style>
