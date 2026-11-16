<script setup>
import {ref, watch, onMounted} from 'vue';
import {useI18n} from 'vue-i18n';
import DataTableJsonOption from "@/components/common/DataTable/DataTableJsonOption.vue";

const {t} = useI18n();
// Modal state for JSON key-value pairs
const showModal = ref([]);

const emit = defineEmits([
    'update:modelFields',
])

const item = ref({});

const taxonomies = ref([]);

const defaultOptions = ref()
const fields = ref([])
const formData = ref({});


const closeModal = () => {
    showModal.value = false;
};

// Function to convert object to array format expected by DataTableJsonOption
const convertToArray = (option) => {
    if (Array.isArray(option)) {
        return option;
    }

    if (typeof option === 'object' && option !== null) {
        // Convert object {key1: value1, key2: value2} to [{key: 'key1', value: 'value1'}, {key: 'key2', value: 'value2'}]
        return Object.entries(option).map(([key, value]) => ({
            key,
            value
        }));
    }

    // Default to empty array if option is not an object or array
    return [];
};


const saveForm = () => {
    // emit('submit', options.value);


    emit('update:modelFields', fields.value);
};

const getTaxonomies = async () => {


    // API URL, adjust as per your setup

    // const apiUrl = '/api/datatable/taxonomies'
    const apiUrl = `/api/common/items?foreign_key=taxonomy`;


    try {
        // Using fetch to make API request
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        // console.log('response', response)

        // if (!response.ok) {
        //     throw new Error('Failed to fetch items');
        // }

        taxonomies.value = await response.json();


    } catch (err) {
        // error.value = err.message;
        console.log('error', err)
    } finally {
        // loading.value = false;
    }
};

const addField = () => {
    const newField = {
        name: `field${fields.value.length + 1}`,
        label: `Field ${fields.value.length + 1}`,
        type: 'text',
        options: {},
        placeholder: `Enter value for Field ${fields.value.length + 1}`,
    };
    fields.value.push(newField);
    // options.value[newField.name] = "";
};
// Watchers to trigger the JSON generation whenever something changes
watch(fields, saveForm, {deep: true});


onMounted(() => {
    getTaxonomies();
    // props.fields.forEach((field) => {
    //     options.value[field.name] = field.type === 'select' && field.multiple ? [] : "";
    // });
});

</script>

<template>
    <v-row>
        <v-col cols="12">
            <div class="d-flex align-center ga-2 mb-2">
                <h3 class="text-subtitle-1 font-weight-medium flex-grow-1">{{ $t('formBuilder.formFields') }}</h3>
                <v-btn variant="tonal" size="small" prepend-icon="mdi-plus" @click="addField">
                    {{ $t('formBuilder.addField') }}
                </v-btn>
            </div>

            <v-row v-for="(field, name) in fields" :key="name" class="d-flex align-center">


                <v-col cols="12" sm="6" md="3">
                    <v-text-field
                        density="compact"
                        v-model="field.label"
                        :label="$t('formBuilder.label')">
                    </v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field
                        density="compact"
                        v-model="field.name"
                        :label="field.label">
                    </v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-text-field
                        density="compact"
                        v-model="field.placeholder"
                        :label="$t('formBuilder.placeholder')">
                    </v-text-field>
                </v-col>
                <v-col cols="12" sm="6" md="3">
                    <v-select
                        density="compact"
                        :label="$t('formBuilder.type')"
                        :items="['select', 'text','taxonomy']"
                        v-model="field.type"
                    />
                </v-col>
                <v-col cols="12">
                    <div v-if="field.type==='select'">
                        <DataTableJsonOption
                            :name=name
                            :option="convertToArray(fields[name].options)"
                            v-model="fields[name].options">
                        </DataTableJsonOption>
                    </div>
                    <div v-if="field.type==='taxonomy'">
                        <div class="text-caption text-medium-emphasis mb-1">{{ $t('formBuilder.loadTaxonomies') }}</div>
                        <v-select
                            clearable
                            v-model="fields[name].options"
                            item-title="name"
                            item-value="name"
                            :items="taxonomies"
                            :label="$t('formBuilder.taxonomy')"
                            density="compact"
                        ></v-select>

<!--                        <v-combobox-->
<!--                            v-model="fields[name].options"-->
<!--                            :items="parents"-->
<!--                            item-title="name"-->
<!--                            item-value="name"-->
<!--                            label="Taxonomy"-->
<!--                            chips-->
<!--                            clearable-->
<!--                        ></v-combobox>-->
                    </div>
                </v-col>


            </v-row>



<!--            <button type="button" class="btn btn-secondary" @click="addField">Add Field</button>-->
<!--            <button type="button" class="btn btn-primary text-right" @click="saveForm">Save form</button>-->


        </v-col>

    </v-row>

    <!--    <v-textarea-->
    <!--        v-model="generatedJson"-->
    <!--    ></v-textarea>-->
</template>

<style scoped>
</style>
