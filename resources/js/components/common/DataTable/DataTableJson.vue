<script setup>
import {ref, watch, onMounted} from 'vue';
import DataTableJsonOption from "@/components/common/DataTable/DataTableJsonOption.vue";
import DataTableJsonField from "@/components/common/DataTable/DataTableJsonField.vue";
// Modal state for JSON key-value pairs
const showModal = ref(false);



const emit = defineEmits([
    'update:modelValue',
])

// const props = defineProps({
//     options: {},
// });

const props = defineProps({
    modelValue: {
        type: [String, Object, Array], // Adapt this to your data type
        required: true,
    },
    options: {
        type: [String, Object, Array],
        required: false,
        default: () => {},
    },
});

const item = ref({});


// const options = ref(JSON.parse(JSON.stringify(props.options)))
const localOptions = ref({})
const defaultOptions = ref({})


const generatedJson = ref('');

// Generate JSON dynamically based on inputs
const generateJson = () => {

    generatedJson.value = JSON.stringify(localOptions.value, null, 2);

    emit('update:modelValue', generatedJson.value);
};


const closeModal = () => {
    showModal.value = false;
};


// Watchers to trigger the JSON generation whenever something changes
watch(
    () => props.options,
    (newOptions) => {
        localOptions.value = newOptions ? JSON.parse(JSON.stringify(newOptions)) : null;
    },
    { immediate: true } // Trigger immediately to initialize localEvent
);
watch(localOptions, generateJson, {deep: true});


onMounted(() => {
    defaultOptions.value = JSON.parse(JSON.stringify(props.options));
});

</script>

<template>
    <v-row>
        <v-col cols="12">
            <h4 v-if="localOptions.length > 0">Options</h4>

            <v-row v-for="(option, name) in localOptions" :key="name" class="answer-item d-flex align-center mb-1 ml-2">
                <template v-if="name === 'form'">
                    <DataTableJsonField v-model:modelFields="localOptions[name]" />
                </template>
                <v-select
                    v-else-if="Array.isArray(option)"
                    v-model="localOptions[name]"
                    :items="defaultOptions[name]"
                    :label="name"
                    multiple
                />
                <DataTableJsonOption
                    v-else
                    :name="name"
                    :option="defaultOptions[name]"
                    v-model:modelValue="localOptions[name]"
                />
            </v-row>


        </v-col>

    </v-row>

    <v-textarea
        v-model="generatedJson"
    ></v-textarea>
</template>

<style scoped>
.answer-item {
    display: flex;
    justify-content: space-between; /* Align items to the left and right */
    align-items: center;
    padding: 8px 0;
}

.delete-icon, .edit-icon {
    cursor: pointer;
    margin-left: 10px;
}


</style>
