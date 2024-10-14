<script setup>
import {ref, watch, onMounted} from 'vue';
// Modal state for JSON key-value pairs
const showModal = ref(false);
// const newKey = ref('');
// const newValue = ref('');
// const items = ref([{ key: '', value: '' }]);
// const generatedJson = ref('');


// const emit = defineEmits([
//     'update:modelValue',
// ])

const emit = defineEmits([
    'update:modelOption',
])

const props = defineProps({
    name:{},
    option: {
    },
});

const item = ref({});

// States for edit functionality
const editIndex = ref(null);
const editKey = ref('');
const editValue = ref('');

const name = ref(props.name)
const option = ref(JSON.parse(JSON.stringify(props.option)))
const defaultOption = ref(JSON.parse(JSON.stringify(props.option)))
// const options = ref(JSON.parse(JSON.stringify(props.options)))
// const defaultOptions = ref(JSON.parse(JSON.stringify(props.options)))

const newOptionKey = ref('');
const newOptionValue = ref('');
// const answers = ref({});
// const profil = ref();
// const permissions = ref();
//
// const showAttributtes = ref();
//
// const location = ref();
//
// const generatedJson = ref('');
// Adding a new answer (key-value pair)
const addOption = () => {
    // console.log('name', options.value[name])

    if (newOptionKey.value && newOptionValue.value) {
        option.value[newOptionKey.value] = newOptionValue.value;
        newOptionKey.value = '';
        newOptionValue.value = '';
        closeModal();
    }
};

// Remove an existing answer
const removeOption = (key) => {
    delete option.value[key];
};

// Function to trigger edit mode
// Function to trigger edit mode
const editOption = (key, value, index) => {
    console.log('dfdsfdsfdsf',key,value,index)
    editIndex.value = index;
    editKey.value = key;
    editValue.value = value;
};

// Function to save edited key-value pair
const saveEdit = (index) => {
    const keys = Object.keys(option.value);
    const oldKey = keys[index];

    // Delete old key and set new key-value pair
    if (oldKey !== editKey.value) {
        delete option.value[oldKey];
    }
    option.value[editKey.value] = editValue.value;

    // Exit edit mode
    editIndex.value = null;
    editKey.value = '';
    editValue.value = '';
    emit('update:modelOption', option.value);
};

// Generate JSON dynamically based on inputs
// const generateJson = () => {
//     // const jsonData = {
//     //     answers: answers.value,
//     //     profil: profil.value,
//     //     permissions: permissions.value,
//     // };
//     generatedJson.value = JSON.stringify(options.value, null, 2);
//
//     emit('update:modelValue', generatedJson.value);
// };


const closeModal = () => {
    showModal.value = false;
};



// Watchers to trigger the JSON generation whenever something changes
// watch(options, generateJson, { deep: true });


onMounted(() => {

});

</script>

<template>
    <v-row>
        <v-col cols="12">

                        <v-btn v-if="!showModal" @click="showModal = true" class="mb-3">Add {{name}}</v-btn>
                        <template v-if="showModal">
                            <v-row>
                            <v-text-field v-model="newOptionKey" label="Key" class="me-2 v-col-5" />
                            <v-text-field v-model="newOptionValue" label="Value" class="me-2 v-col-5" />
                            <v-icon @click="addOption()">mdi-check</v-icon>
                            <v-icon @click="showModal = false">mdi-close</v-icon>
                            </v-row>

                        </template>


                        <ul>


                        <li v-for="(value, key, index) in option" :key="key" class="answer-item d-flex align-center mb-1 ml-2">
                            <template v-if="editIndex === index" class="d-flex align-center">

                                <v-text-field v-model="editKey" label="Key" class="me-2 v-col-5" />
                                <v-text-field v-model="editValue" label="Value" class="me-2 v-col-5" />
                                <v-icon @click="saveEdit(index)">mdi-check</v-icon>

                            </template>
                            <template v-else>
                                <span>{{ key }}: {{ value }}</span>
                                <span>
                                                            <v-icon class="edit-icon" @click="editOption(key, value, index)">mdi-pencil</v-icon>
                                                            <v-icon class="delete-icon" @click="removeOption( key)">mdi-delete</v-icon>
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
    justify-content: space-between; /* Align items to the left and right */
    align-items: center;
    padding: 8px 0;
}

.delete-icon, .edit-icon {
    cursor: pointer;
    margin-left: 10px;
}


</style>
