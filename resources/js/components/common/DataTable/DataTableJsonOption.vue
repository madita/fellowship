<script setup>
import {ref, watch, onMounted} from 'vue';
// Modal state for JSON key-value pairs
const showModal = ref(false);


const emit = defineEmits([
    'update:modelValue',
])

const props = defineProps({
    name: {},
    option: {},
});

const item = ref({});

// States for edit functionality
const editIndex = ref(null);
const editKey = ref('');
const editValue = ref('');

const name = ref(props.name)
// const option = ref(JSON.parse(JSON.stringify(props.option)))
const localOption = ref([])
const defaultOption = ref(JSON.parse(JSON.stringify(props.option)))
// const options = ref(JSON.parse(JSON.stringify(props.options)))
// const defaultOptions = ref(JSON.parse(JSON.stringify(props.options)))

const newOptionKey = ref('');
const newOptionValue = ref('');
// const answers = ref({});

const addOption = () => {
    // console.log('name', option.value[name])
    Object.keys(localOption).map((key) => [key, localOption[key]]);
    if (newOptionKey.value && newOptionValue.value) {
        const newOption = {
            key: newOptionKey.value,
            value: newOptionValue.value,
        }

        console.log('typeof option', typeof localOption.value)
        localOption.value.push(newOption)
        // option.value[]['key'] = newOptionKey.value;
        // option.value[]['value'] = newOptionValue.value;
        newOptionKey.value = '';
        newOptionValue.value = '';
        closeModal();
        // emit('update:modelValue', option.value);
    }
};

// Remove an existing answer
const removeOption = (index) => {
    delete localOption.value[index];
};

// Function to trigger edit mode

const editOption = (index, value) => {
console.log('value', value, 'index', index)
    editIndex.value = index;
    editKey.value = value.key;
    editValue.value = value.value;
};

// Function to save edited key-value pair
const saveEdit = (index) => {

    // const keys = Object.keys(option.value);
    // const oldKey = keys[index];

    // Delete old key and set new key-value pair
    // if (oldKey !== editKey.value) {
    //     delete option.value[oldKey];
    // }
    localOption.value[index]['key'] = editKey.value;
    localOption.value[index]['value'] = editValue.value;
    // option.value[editKey.value] = editValue.value;
    // console.log('saveeditoption', option.value)

    // Exit edit mode
    editIndex.value = null;
    editKey.value = '';
    editValue.value = '';

};

const closeModal = () => {
    showModal.value = false;
};


// Watchers to trigger the JSON generation whenever something changes
watch(localOption, (newValue) => {
    console.log('newValue', newValue)
    emit('update:modelValue', newValue);
}, {deep: true});

onMounted(() => {

});

</script>

<template>
    <v-row>
        <v-col cols="12">

            <v-btn v-if="!showModal" @click="showModal = true" class="mb-1">Add {{ name }}</v-btn>
            <template v-if="showModal">
                <v-row>
                    <v-text-field density="compact" v-model="newOptionKey" label="Key" class="me-1 v-col-5"/>
                    <v-text-field density="compact" v-model="newOptionValue" label="Value" class="me-1 v-col-5"/>
                    <v-icon @click="addOption()">mdi-check</v-icon>
                    <v-icon @click="showModal = false">mdi-close</v-icon>
                </v-row>

            </template>


            <ul>

                <li v-for="(value, index) in option" :key="key" class="answer-item d-flex align-center mb-1 ml-2">
                    <template v-if="editIndex === index" class="d-flex align-center">
                        <v-text-field density="compact" v-model="editKey" label="Key" class="me-1 v-col-5"/>
                        <v-text-field density="compact" v-model="editValue" label="Value" class="me-1 v-col-5"/>
                        <v-icon @click="saveEdit(index)">mdi-check</v-icon>

                    </template>
                    <template v-else>
                        <span>{{ index }} -  {{ value.key }}: {{ value.value }}</span>
                        <span>
                                                            <v-icon class="edit-icon"
                                                                    @click="editOption(index, value)">mdi-pencil</v-icon>
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
    padding: 1px 0;
}

.delete-icon, .edit-icon {
    cursor: pointer;
    margin-left: 10px;
}


</style>
