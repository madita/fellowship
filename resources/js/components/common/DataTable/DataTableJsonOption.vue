<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Reactive modal state
const showModal = ref(false);

// Emit event for model updates
const emit = defineEmits(['update:modelValue']);

// Define props
const props = defineProps({
    name: { type: String, required: true },
    option: { type: Array, default: () => [] }, // Ensure it's an array
});

// Reactive data
const localOption = ref([...props.option]); // Deep copy of the prop
const newOptionKey = ref('');
const newOptionValue = ref('');
const editIndex = ref(null);
const editKey = ref('');
const editValue = ref('');

// Function to add a new key-value pair
const addOption = () => {
    if (newOptionKey.value.trim() && newOptionValue.value.trim()) {
        console.log('adding option')
        // options.value[name][newOptionKey.value] = newOptionValue.value;
        localOption.value.push({ key: newOptionKey.value, value: newOptionValue.value });
        newOptionKey.value = '';
        newOptionValue.value = '';
        console.log(localOption)
    }
    showModal.value = false;
};

// Function to remove an option
const removeOption = (index) => {
    localOption.value.splice(index, 1);
};

// Function to start editing an option
const editOption = (index, value) => {
    editIndex.value = index;
    editKey.value = value.key;
    editValue.value = value.value;
};

// Function to save an edited option
const saveEdit = (index) => {
    if (editKey.value.trim() && editValue.value.trim()) {
        localOption.value[index] = { key: editKey.value, value: editValue.value };
        editIndex.value = null;
        editKey.value = '';
        editValue.value = '';
    }
};

// Watch for changes in localOption and emit updates
watch(localOption, (newValue) => {
    console.log('watch', newValue)
    // localOption.value = newValue;
    emit('update:modelValue', newValue);
}, { deep: true });

</script>

<template>
    <v-row>
        <v-col cols="12">
            <!-- Add Button -->
            <v-btn
                v-if="!showModal"
                variant="tonal"
                size="small"
                prepend-icon="mdi-plus"
                class="mb-2"
                @click="showModal = true"
            >
                {{ $t('formBuilder.addOption', { name }) }}
            </v-btn>

            <!-- Inline form for adding a new option -->
            <div v-if="showModal" class="d-flex align-center ga-2 mb-2">
                <v-text-field v-model="newOptionKey" :label="$t('formBuilder.key')" density="compact" hide-details class="flex-grow-1" />
                <v-text-field v-model="newOptionValue" :label="$t('formBuilder.value')" density="compact" hide-details class="flex-grow-1" />
                <v-btn icon="mdi-check" variant="text" size="small" color="primary" :aria-label="$t('common.add')" @click="addOption()" />
                <v-btn icon="mdi-close" variant="text" size="small" :aria-label="$t('common.cancel')" @click="showModal = false" />
            </div>

            <!-- Display Options List -->
            <div v-for="(value, index) in localOption" :key="index" class="d-flex align-center ga-2 mb-1">
                <template v-if="editIndex === index">
                    <v-text-field v-model="editKey" :label="$t('formBuilder.key')" density="compact" hide-details class="flex-grow-1" />
                    <v-text-field v-model="editValue" :label="$t('formBuilder.value')" density="compact" hide-details class="flex-grow-1" />
                    <v-btn icon="mdi-check" variant="text" size="small" color="primary" :aria-label="$t('common.save')" @click="saveEdit(index)" />
                </template>
                <template v-else>
                    <span class="flex-grow-1 text-body-2">{{ value.key }}: {{ value.value }}</span>
                    <v-btn icon="mdi-pencil" variant="text" size="small" :aria-label="$t('common.edit')" @click="editOption(index, value)" />
                    <v-btn icon="mdi-delete" variant="text" size="small" color="error" :aria-label="$t('common.delete')" @click="removeOption(index)" />
                </template>
            </div>
        </v-col>
    </v-row>
</template>

