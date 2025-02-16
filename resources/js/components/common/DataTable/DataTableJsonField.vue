<script setup>
import {ref, watch, onMounted} from 'vue';
import DataTableJsonOption from "@/components/common/DataTable/DataTableJsonOption.vue";
// Modal state for JSON key-value pairs
const showModal = ref(false);

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

        if (!response.ok) {
            throw new Error('Failed to fetch items');
        }

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
            <v-row>

                <v-col class="ma-1"><h4>Form Fields</h4></v-col>
                <v-col class="text-right">
                    <v-btn class="btn btn-secondary text-right" @click="addField" icon="mdi-plus"></v-btn>
                </v-col>
            </v-row>

            <v-row v-for="(field, name) in fields" :key="name" class="d-flex align-center">


                <v-col cols="3">
                    <v-text-field
                        density="compact"
                        class="form-control"
                        v-model="field.label"
                        label="Label">
                    </v-text-field>
                </v-col>
                <v-col cols="3">
                    <v-text-field
                        density="compact"
                        class="form-control"
                        v-model="field.name"
                        :label="field.label">
                    </v-text-field>
                </v-col>
                <v-col cols="3">
                    <v-text-field
                        density="compact"
                        class="form-control"
                        v-model="field.placeholder"
                        label="Placeholder">
                    </v-text-field>
                </v-col>
                <v-col cols="3">
                    <v-select
                        density="compact"
                        label="Type"
                        :items="['select', 'text','taxonomy']"
                        v-model="field.type"
                        class="form-control v-col-5"
                    />
                </v-col>
                <v-col cols="12">


                    <!--                        </div>-->

                    <template class="form-control v-col-12" v-if="field.type==='select'">
                        <DataTableJsonOption
                            :name=name
                            :option="fields[name].options"
                            v-model="fields[name].options">
                        </DataTableJsonOption>

                    </template>
                    <template class="form-control v-col-12" v-if="field.type==='taxonomy'">
                        load taxonomies
                        <v-select
                            clearable
                            v-model="fields[name].options"
                            item-title="name"
                            item-value="name"
                            :items="taxonomies"
                            label="Taxonomy"

                        ></v-select>
                    </template>
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
.form-group {
    margin-bottom: 1rem;
}

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
