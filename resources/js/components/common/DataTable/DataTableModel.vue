<script setup>
import {ref, watch, computed, onMounted} from 'vue';


const showModal = ref(false);


const emit = defineEmits([
    'update:modelValue',
])

const props = defineProps({
    column: '',
    filter_key: '',
    filter_value: '',
    endPoint: '',
});

const items = ref([]);
const name = ref('');
const apiUrl = ref('');
const model = ref('');
const loading = ref(false);
const localFilterKey = ref('');
const localFilterValue = ref('');
const error = ref(false);




const fetchItems = async () => {
    loading.value = true;
    error.value = null;
    items.value = [];

    // API URL, adjust as per your setup


    try {
        // Using fetch to make API request
        const response = await fetch(apiUrl.value, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        });

        if (!response.ok) {
            console.log('Failed to fetch items')
        }

        items.value = await response.json();
        // console.log('items.value', items.value)

        if(props.column == 'parent_id') {
            items.value = items.value.data.records
        }

    } catch (err) {
        error.value = err.message;
        console.log('error', err)
    } finally {
        loading.value = false;
    }
};

// Computed property to determine which key to use for item-title
const itemTitle = computed(() => {
    if (items.value.length > 0) {
        // Check if 'name' exists in the first item
        if ('name' in items.value[0]) {
            return 'name';
        }
        // Check if 'title' exists in the first item
        if ('title' in items.value[0]) {
            return 'title';
        }
        // Fallback to any other possible key, like 'label'
        if ('label' in items.value[0]) {
            return 'label';
        }

        // Fallback to any other possible key, like 'label'
        if ('description' in items.value[0]) {
            return 'description';
        }

        // Fallback to any other possible key, like 'label'
        if ('taxonomy' in items.value[0]) {
            return 'description';
        }
    }
    // Default if no appropriate field is found
    return '';
});

const filteredItems = computed(() => {
    let data = items.value

    // console.log('hmmmmm',localFilterValue.value)
    if(localFilterValue.value === '') {
        return data
    }

    data = data.filter((row) => {
        return row[localFilterKey.value]  === localFilterValue.value;
    })


    return data
})


// watch(model, emit('update:modelValue', model.value))
// watch(model, emit('update:modelValue', model.value), {deep: true});

watch(() => model.value, (model) => {
    // console.log('model', model)
    emit('update:modelValue', model);

});

watch(() => props.filter_value, (filter) => {
    localFilterValue.value = filter;
});

onMounted(() => {
    // console.log('mountedfilter', props.filter_value, props.filter_key)
    localFilterKey.value = props.filter_key
    if(props.column == 'parent_id') {
        apiUrl.value =  `/api${props.endPoint}`;

    } else {
        apiUrl.value =  `/api/common/items?foreign_key=${props.column}`;
    }





    fetchItems();
});

</script>

<template>

    <v-select
        clearable
        v-model="model"
        :item-title="itemTitle"
        item-value="id"
        :items="filteredItems"
        :label="column"

    ></v-select>


</template>

<style scoped>

</style>
