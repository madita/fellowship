<script setup>
import {ref, watch, onMounted} from 'vue';
import DataTableJsonOption from "@/components/common/DataTable/DataTableJsonOption.vue";
// Modal state for JSON key-value pairs
const showModal = ref(false);
// const newKey = ref('');
// const newValue = ref('');
// const items = ref([{ key: '', value: '' }]);
// const generatedJson = ref('');


const emit = defineEmits([
    'update:modelValue',
])

const props = defineProps({
    options: {
    },
});

const item = ref({});

// States for edit functionality
// const editIndex = ref(null);
// const editKey = ref('');
// const editValue = ref('');

const options = ref(JSON.parse(JSON.stringify(props.options)))
const defaultOptions = ref(JSON.parse(JSON.stringify(props.options)))

// const newOptionKey = ref('');
// const newOptionValue = ref('');
// const answers = ref({});
// const profil = ref();
// const permissions = ref();

// const showAttributtes = ref();
//
// const location = ref();

const generatedJson = ref('');
// Adding a new answer (key-value pair)
// const addOption = (name) => {
//     // console.log('name', options.value[name])
//
//     if (newOptionKey.value && newOptionValue.value) {
//         options.value[name][newOptionKey.value] = newOptionValue.value;
//         newOptionKey.value = '';
//         newOptionValue.value = '';
//         closeModal();
//     }
// };
//
// // Remove an existing answer
// const removeOption = (option, key) => {
//     delete options.value[option][key];
// };
//
// // Function to trigger edit mode
// // Function to trigger edit mode
// const editOption = (option, key, value, index) => {
//     editIndex.value[option] = index;
//     editKey.value[option] = key;
//     editValue.value[option] = value;
// };
//
// // Function to save edited key-value pair
// const saveEdit = (option, index) => {
//     const keys = Object.keys(options.value[option]);
//     const oldKey = keys[index];
//
//     // Delete old key and set new key-value pair
//     if (oldKey !== editKey.value) {
//         delete options.value[option][oldKey];
//     }
//     options.value[option][editKey.value] = editValue.value;
//
//     // Exit edit mode
//     editIndex.value = null;
//     editKey.value = '';
//     editValue.value = '';
// };

// Generate JSON dynamically based on inputs
const generateJson = () => {
    // const jsonData = {
    //     answers: answers.value,
    //     profil: profil.value,
    //     permissions: permissions.value,
    // };
    generatedJson.value = JSON.stringify(options.value, null, 2);

    emit('update:modelValue', generatedJson.value);
};


const closeModal = () => {
    showModal.value = false;
};



// Watchers to trigger the JSON generation whenever something changes
watch(options, generateJson, { deep: true });


onMounted(() => {

});

</script>

<template>
    <v-row>
        <v-col cols="12">
            <h4>Options</h4>
            <v-row v-for="(option, name, index) in options" :key="name" class="answer-item d-flex align-center mb-1 ml-2">
<!--                value {{value}} key {{ key}} index {{index}}-->
<!--                {{typeof value}} {{ Array.isArray(value)}}-->

                <template v-if="typeof option === 'object'">
                    <v-select
                        v-if="Array.isArray(option)"
                        v-model="options[name]"
                        :items="defaultOptions[name]"
                        :label="name"
                        multiple
                    ></v-select>
                    <template v-else>

                        <DataTableJsonOption
                            :name=name
                            :option="options[name]"
                            v-model:modelOption="options[name]">
                        </DataTableJsonOption>

<!--                        <v-col cols="12">-->

<!--                        <v-btn v-if="!showModal" @click="showModal = true" class="mb-3">Add Option</v-btn>-->
<!--                        <template v-if="showModal">-->
<!--                            <v-row>-->
<!--                            <v-text-field v-model="newOptionKey" label="Key" class="me-2 v-col-5" />-->
<!--                            <v-text-field v-model="newOptionValue" label="Value" class="me-2 v-col-5" />-->
<!--                            <v-icon @click="addOption(name)">mdi-check</v-icon>-->
<!--                            <v-icon @click="showModal = false">mdi-close</v-icon>-->
<!--                            </v-row>-->

<!--                        </template>-->
<!--                        </v-col>-->

<!--                        <ul>-->


<!--                        <li v-for="(value, key, i) in option" :key="key" class="answer-item d-flex align-center mb-1 ml-2">-->
<!--                            <template v-if="editIndex === i" class="d-flex align-center">-->

<!--                                <v-text-field v-model="editKey" label="Key" class="me-2 v-col-5" />-->
<!--                                <v-text-field v-model="editValue" label="Value" class="me-2 v-col-5" />-->
<!--                                <v-icon @click="saveEdit(name, index)">mdi-check</v-icon>-->

<!--                            </template>-->
<!--                            <template v-else>-->
<!--                                <span>{{ key }}: {{ value }}</span>-->
<!--                                <span>-->
<!--                                                            <v-icon class="edit-icon" @click="editOption(name, key, value, index)">mdi-pencil</v-icon>-->
<!--                                                            <v-icon class="delete-icon" @click="removeOption(name, key)">mdi-delete</v-icon>-->
<!--                                                        </span>-->

<!--                            </template>-->
<!--                        </li>-->
<!--                        </ul>-->



                    </template>


                </template>
                <template v-else></template>

            </V-row>

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
