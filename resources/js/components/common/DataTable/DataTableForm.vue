<script setup>
import { ref, watch, computed, nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { PerfectScrollbar } from 'vue3-perfect-scrollbar';
import { VForm } from 'vuetify/components/VForm';
import axios from "axios";
import Tiptap from "@/components/common/tiptap/Tiptap.vue";
import DataTableJson from "@/components/common/DataTable/DataTableJson.vue";
import DataTableModel from "@/components/common/DataTable/DataTableModel.vue";

const { t } = useI18n();

// 👉 Props
const props = defineProps({
    isDrawerOpen: {
        type: Boolean,
        required: true,
    },
    response: {
        type: Object,
        default: () => ({})
    },
    defaultItem: {
        type: Object,
        default: () => ({})
    },
    item: {
        type: Object,
        default: () => ({})
    },
    endpoint: {
        type: String,
        default: ''
    },
    editMode: {
        type: Boolean,
        default: false
    }
});

// 👉 Emits
const emit = defineEmits([
    'update:isDrawerOpen',
    'addItem',
    'updateItem',
    'removeItem',
]);

// 👉 Reactive Data
const isFocused = ref(true);
const loadItemDetails = ref(true);
const editedItem = ref({ ...props.defaultItem });
const localEditMode = ref(props.editMode);
const refForm = ref();
const title = ref('');

// Initialize item from props
const item = ref(JSON.parse(JSON.stringify(props.item || props.defaultItem)));
const itemDetails = ref({});
const parentItems = ref({});
const loading = ref({});

// Validation states
const startDateError = ref('');
const endDateError = ref('');
const isStartDateValid = ref(true);
const isEndDateValid = ref(true);

// 👉 Methods
const resetItem = () => {
    item.value = JSON.parse(JSON.stringify(props.item || props.defaultItem));
    editedItem.value = JSON.parse(JSON.stringify(props.item || props.defaultItem));
    isStartDateValid.value = true;
    isEndDateValid.value = true;

    nextTick(() => {
        refForm.value?.resetValidation();
    });
};

const removeItem = () => {
    if (item.value.id) {
        emit('removeItem', String(item.value.id));
        emit('update:isDrawerOpen', false);
    }
};

const handleSubmit = () => {
    refForm.value?.validate().then(({ valid }) => {
        if (valid) {
            localEditMode.value = false;

            // Use editedItem for consistency
            const submitData = { ...editedItem.value };
            // console.log('submitData', submitData)

            // If id exists => Update item
            if (submitData.id) {
                emit('updateItem', submitData);
            } else {
                // Add new item
                emit('addItem', submitData);
            }

            // Close drawer
            emit('update:isDrawerOpen', false);
        }
    });
};

const onCancel = () => {
    emit('update:isDrawerOpen', false);
    localEditMode.value = false;

    nextTick(() => {
        refForm.value?.reset();
        resetItem();
        refForm.value?.resetValidation();
        isStartDateValid.value = true;
        isEndDateValid.value = true;
    });
};

const joinItem = (itemId, answer) => {
    if (!itemDetails.value.isGoing) {
        itemDetails.value.isGoing = {};
    }
    itemDetails.value.isGoing.type = answer;

    const userData = {
        answer: answer,
        bringing: 'stuff',
        days: '3'
    };

    axios.post(`/api/items/${itemId}/going`, userData)
        .then(() => {
            console.log('Successfully joined item');
        })
        .catch((error) => {
            console.error('Error joining item:', error);
            if (error.response?.status === 422) {
                // Handle validation errors
            }
        });
};

// Date validation methods
const validateStartDate = () => {
    if (!item.value.start) {
        startDateError.value = 'Start date is required';
        isStartDateValid.value = false;
        return false;
    } else {
        startDateError.value = '';
        isStartDateValid.value = true;
        return true;
    }
};

const validateEndDate = () => {
    if (!item.value.end) {
        endDateError.value = 'End date is required';
        isEndDateValid.value = false;
        return false;
    } else {
        endDateError.value = '';
        isEndDateValid.value = true;
        return true;
    }
};

const dialogModelValueUpdate = (val) => {
    emit('update:isDrawerOpen', val);
};

// 👉 Computed Properties
const startDateTimePickerConfig = computed(() => {
    const config = {
        enableTime: !item.value.allDay,
        dateFormat: `Y-m-d${item.value.allDay ? '' : ' H:i'}`,
    };

    if (item.value.end) {
        config.maxDate = item.value.end;
    }

    return config;
});

const endDateTimePickerConfig = computed(() => {
    const config = {
        enableTime: !item.value.allDay,
        dateFormat: `Y-m-d${item.value.allDay ? '' : ' H:i'}`,
    };

    if (item.value.start) {
        config.minDate = item.value.start;
    }

    return config;
});

const formTitle = computed(() => {
    if (localEditMode.value) {
        return item.value?.id ? 'Update Item' : 'Add Item';
    }
    return 'View Item';
});

const hasUpdatableFields = computed(() => {
    return props.response?.updatable && Array.isArray(props.response.updatable);
});

// 👉 Watchers
watch(() => props.item, (newItem) => {
    if (newItem) {
        item.value = JSON.parse(JSON.stringify(newItem));
        editedItem.value = JSON.parse(JSON.stringify(newItem));
    }
}, { deep: true, immediate: true });

watch(() => props.editMode, (newEditMode) => {
    localEditMode.value = newEditMode;
}, { immediate: true });

watch(() => props.isDrawerOpen, (isOpen) => {
    if (isOpen) {
        resetItem();
        // Auto-enable edit mode for new items (no id)
        if (!item.value.id) {
            localEditMode.value = true;
        }
    }
});

// 👉 Lifecycle
onMounted(() => {
    resetItem();
});
</script>

<template>
    <VNavigationDrawer
        temporary
        location="end"
        :model-value="props.isDrawerOpen"
        width="700"
        class="scrollable-content"
        @update:model-value="dialogModelValueUpdate"
    >
        <!-- Header -->
        <div class="pa-4 d-flex align-center">
            <h5 class="text-h5 me-3">
                {{ formTitle }}
            </h5>

            <VSpacer />

            <slot name="beforeClose" />

            <!-- Action buttons in header -->
            <div class="d-flex gap-2">
                <VBtn
                    v-if="!localEditMode && item.id"
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    @click="localEditMode = true"
                />
                <VBtn
                    v-if="localEditMode && item.id"
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    color="error"
                    @click="removeItem"
                />
                <VBtn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="onCancel"
                />
            </div>
        </div>

        <VDivider />

        <!-- Content -->
        <PerfectScrollbar :options="{ wheelPropagation: false }">
            <VCard flat>
                <VCardText>
                    <!-- Error state -->
                    <VAlert
                        v-if="!hasUpdatableFields"
                        type="warning"
                        class="mb-4"
                        :text="$t('formBuilder.noUpdatableFields')"
                    />

                    <!-- Form Section -->
                    <VForm
                        v-if="hasUpdatableFields"
                        ref="refForm"
                        @submit.prevent="handleSubmit"
                    >
                        <!-- Dynamic Fields -->
                        <VRow
                            v-for="column in response.updatable"
                            :key="`field-${column}`"
                        >
                            <VCol cols="12">
                                <div class="field-container">
                                    <!-- Field Label -->
                                    <div class="text-caption text-medium-emphasis mb-1">
                                        {{ column }}
                                    </div>

                                    <!-- Object-based field types -->
                                    <template v-if="typeof(response.column_fields?.[column]) === 'object'">
                                        <!-- Select dropdown -->
                                        <VSelect
                                            v-if="'select' in response.column_fields[column]"
                                            :items="response.column_fields[column]['select']"
                                            v-model="editedItem[column]"
                                            :label="column"
                                            :readonly="!localEditMode"
                                            variant="outlined"
                                            density="compact"
                                        />

                                        <!-- Fallback for unknown object types -->
                                        <VAlert
                                            v-else
                                            type="error"
                                            density="compact"
                                            :text="$t('formBuilder.unknownFieldConfiguration')"
                                        />
                                    </template>

                                    <!-- String-based field types -->
                                    <template v-else>
                                        <!-- Textarea -->
                                        <VTextarea
                                            v-if="response.column_fields?.[column] === 'textarea'"
                                            :label="column"
                                            v-model="editedItem[column]"
                                            :readonly="!localEditMode"
                                            variant="outlined"
                                            density="compact"
                                            rows="3"
                                        />

                                        <!-- WYSIWYG Editor -->
                                        <Tiptap
                                            v-else-if="response.column_fields?.[column] === 'wysiwyg'"
                                            v-model:modelValue="editedItem[column]"
                                            :readonly="!localEditMode"
                                            :id="`tiptap-${column}`"
                                            :name="column"
                                        />

                                        <!-- Checkbox -->
                                        <VCheckbox
                                            v-else-if="response.column_fields?.[column] === 'checkbox'"
                                            v-model="editedItem[column]"
                                            :label="column"
                                            :readonly="!localEditMode"
                                            density="compact"
                                        />

                                        <!-- Color Picker -->
                                        <VColorPicker
                                            v-else-if="response.column_fields?.[column] === 'color'"
                                            v-model="editedItem[column]"
                                            mode="hex"
                                            :readonly="!localEditMode"
                                        />

                                        <!-- JSON Editor -->
                                        <DataTableJson
                                            v-else-if="response.column_fields?.[column] === 'json'"
                                            :options="response.json_fields"
                                            v-model:modelValue="editedItem[column]"
                                            :readonly="!localEditMode"
                                        />

                                        <!-- Model/Parent Relationship -->
                                        <DataTableModel
                                            v-else-if="response.column_fields?.[column] === 'model' || response.column_fields?.[column] === 'parent'"
                                            :column="column"
                                            :filter_key="response.filter_fields?.[column]"
                                            :filter_value="editedItem[response.filter_fields?.[column]]"
                                            :endPoint="endpoint"
                                            v-model:modelValue="editedItem[column]"
                                            :readonly="!localEditMode"
                                        />

                                        <!-- Default Text Field -->
                                        <VTextField
                                            v-else
                                            v-model="editedItem[column]"
                                            :label="column"
                                            :readonly="!localEditMode"
                                            variant="outlined"
                                            density="compact"
                                        />
                                    </template>
                                </div>
                            </VCol>
                        </VRow>

                        <!-- Form Actions -->
                        <VRow v-if="localEditMode">
                            <VCol cols="12">
                                <div class="d-flex gap-3 mt-4">
                                    <VBtn
                                        type="submit"
                                        color="primary"
                                        variant="elevated"
                                        :loading="loading.submit"
                                    >
                                        {{ item.id ? 'Update' : 'Create' }}
                                    </VBtn>

                                    <VBtn
                                        variant="outlined"
                                        color="secondary"
                                        @click="onCancel"
                                    >
                                        Cancel
                                    </VBtn>

                                    <VBtn
                                        v-if="item.id"
                                        variant="outlined"
                                        color="error"
                                        @click="removeItem"
                                        :loading="loading.delete"
                                    >
                                        Delete
                                    </VBtn>
                                </div>
                            </VCol>
                        </VRow>
                    </VForm>

                    <!-- View Mode Actions -->
                    <VRow v-if="!localEditMode && item.id">
                        <VCol cols="12">
                            <div class="d-flex gap-3 mt-4">
                                <VBtn
                                    color="primary"
                                    variant="elevated"
                                    prepend-icon="mdi-pencil"
                                    @click="localEditMode = true"
                                >
                                    Edit
                                </VBtn>

                                <VBtn
                                    variant="outlined"
                                    color="error"
                                    prepend-icon="mdi-delete"
                                    @click="removeItem"
                                >
                                    Delete
                                </VBtn>
                            </div>
                        </VCol>
                    </VRow>
                </VCardText>
            </VCard>
        </PerfectScrollbar>
    </VNavigationDrawer>
</template>

<style scoped>
.scrollable-content {
    background: rgb(var(--v-theme-surface));
}

.field-container {
    position: relative;
}

.field-container .text-caption {
    font-weight: 500;
    color: rgb(var(--v-theme-primary));
}

/* Custom styling for readonly fields */
.v-input--readonly {
    opacity: 0.7;
}

.v-input--readonly .v-field {
    background-color: rgb(var(--v-theme-surface-variant));
}

/* Better spacing for form elements */
.v-text-field,
.v-textarea,
.v-select,
.v-checkbox {
    margin-bottom: 8px;
}

/* Improved button styling */
.v-btn {
    text-transform: none;
    font-weight: 600;
}

/* Header styling */
.pa-4 {
    border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

/* Loading states */
.v-btn--loading {
    pointer-events: none;
}
</style>
