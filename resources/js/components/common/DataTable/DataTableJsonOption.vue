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
            <v-btn v-if="!showModal" @click="showModal = true" class="mb-1">
                Add {{ name }}
            </v-btn>

            <!-- Modal for adding new option -->
            <template v-if="showModal">
                <v-row>
                    <v-text-field v-model="newOptionKey" :label="$t('formBuilder.key')" density="compact" class="me-1 v-col-5" />
                    <v-text-field v-model="newOptionValue" :label="$t('formBuilder.value')" density="compact" class="me-1 v-col-5" />
                    <v-icon @click="addOption()">mdi-check</v-icon>
                    <v-icon @click="showModal = false">mdi-close</v-icon>
                </v-row>
            </template>

            <!-- Display Options List -->
            <ul>
                {{localOption}}
                <li v-for="(value, index) in localOption" :key="index" class="answer-item d-flex align-center mb-1 ml-2">
                    <template v-if="editIndex === index">
                        <v-text-field v-model="editKey" :label="$t('formBuilder.key')" density="compact" class="me-1 v-col-5" />
                        <v-text-field v-model="editValue" :label="$t('formBuilder.value')" density="compact" class="me-1 v-col-5" />
                        <v-icon @click="saveEdit(index)">mdi-check</v-icon>
                    </template>
                    <template v-else>
                        <span>{{ index }} - {{ value.key }}: {{ value.value }}</span>
                        <span>
              <v-icon class="edit-icon" @click="editOption(index, value)">mdi-pencil</v-icon>
              <v-icon class="delete-icon" @click="removeOption(index)">mdi-delete</v-icon>
            </span>
                    </template>
                </li>
            </ul>
        </v-col>
    </v-row>
</template>

<style scoped>
.answer-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1px 0;
}

.delete-icon, .edit-icon {
    cursor: pointer;
    margin-left: 10px;
}
</style>
