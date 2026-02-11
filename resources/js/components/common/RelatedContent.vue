<template>
    <VDialog v-model="internalModelValue" max-width="500">
        <VCard>
            <VCardTitle class="text-h5">{{ $t('relatedContent.title') }}</VCardTitle>
            <VCardText>
                <p>{{ $t('relatedContent.selectRelationType') }} <strong>{{ contentName }}</strong>:</p>

                <!-- Dropdown to select relation type -->
                <VSelect
                    v-model="selectedSource"
                    :items="sourceModels"
                    item-title="name"
                    item-value="class"
                    :label="$t('relatedContent.relationType')"
                    :placeholder="$t('relatedContent.selectSourceType')"
                    variant="underlined"
                />

                <VSelect
                    v-model="selectedSourceItem"
                    :items="sourceItems"
                    item-title="title"
                    item-value="id"
                    :label="$t('relatedContent.selectItem')"
                    :placeholder="$t('relatedContent.selectAnItem')"
                    variant="underlined"
                    :disabled="!selectedSource"
                />

                <!-- Dropdown to select the model type -->
                <VSelect
                    v-model="selectedRelated"
                    :items="relatedModels"
                    item-title="name"
                    item-value="class"
                    :label="$t('relatedContent.modelType')"
                    :placeholder="$t('relatedContent.selectModelType')"
                    variant="underlined"
                    :disabled="!selectedSource"
                />

                <!-- Dropdown to select a specific item of the selected model -->
                <VSelect
                    v-model="selectedRelatedItem"
                    :items="relatedItems"
                    item-title="title"
                    item-value="id"
                    :label="$t('relatedContent.selectItem')"
                    :placeholder="$t('relatedContent.selectAnItem')"
                    variant="underlined"
                    :disabled="!selectedRelated"
                />
            </VCardText>
            <VCardActions>
                <VSpacer/>
                <VBtn color="grey" @click="cancel">{{ $t('common.cancel') }}</VBtn>
                <VBtn color="primary" :disabled="!selectedSource || !selectedSourceItem || !selectedRelated || !selectedRelatedItem" @click="confirm">
                    {{ $t('common.confirm') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<script setup>
import {ref, watch, computed, onMounted} from 'vue';
import {useI18n} from 'vue-i18n';
import axios from 'axios';

const {t} = useI18n();

const props = defineProps({
    modelValue: Boolean,
    contentName: String,
    initialSourceType: String,
    initialSourceItem:String,
    initialRelatedType: String,
    initialRelatedItem:String
});

const emit = defineEmits(['update:modelValue', 'confirmRelation']);

const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// const selectedSource = ref(props.initialRelationType || '');
const selectedSource = ref();
const selectedRelated = ref();
const selectedRelatedItem = ref();
const selectedSourceItem = ref();
const sourceModels = ref([]); // Available relation types
const sourceItems = ref([]);
const relatedModels = ref([]); // Models to relate, fetched based on selected relation type
const relatedItems = ref([]); // Items of the selected model, fetched from the API

// Fetch relation types from the API
const fetchSourceModels = async () => {
    try {
        const response = await axios.get('/api/source-models');
        sourceModels.value = response.data.source_models.map(model => ({
            name: model.split('\\').pop(),
            class: model,
        }));

        if(props.initialSourceType) {
            selectedSource.value = props.initialSourceType
            // selectedSourceItem.value = sourceModels.value.find(x => x.class = props.initialSourceType)
            // console.log('initialSourceTypeselection',selectedSource )
         }
    } catch (error) {
        console.error('Failed to fetch relateable models:', error);
    }
};

// Fetch models based on the selected relation type
const fetchRelateableModels = async () => {
    // if (!selectedSource.value) return;

    try {
        const response = await axios.get('/api/models', {
            params: {relationType: selectedSource.value}
        });
        relatedModels.value = response.data.models.map(model => ({
            name: model.split('\\').pop(),
            class: model,
        }));
    } catch (error) {
        console.error('Failed to fetch models:', error);
    }
};

// Fetch items of the selected model
const fetchModelItems = async (model, targetArray) => {
    try {

        const response = await axios.get('/api/model-items', {
            params: { model }
        });
        targetArray.value = response.data.items.map(item => ({
            title: item.name||item.title,
            id: item.id,
        }));

        if(props.initialSourceItem > 0) {
            selectedSourceItem.value = parseInt(props.initialSourceItem)
        }
    } catch (error) {
        console.error(`Failed to fetch items for model ${model}:`, error);
    }
};

const relateModels = async (data) => {
    console.log(data)
    try {

        const response = await axios.post('/api/relate-models', {
            data
        });

    } catch (error) {
        console.error(`Failed to relate models ${data}:`, error);
    }
};

watch(selectedSource, (model) => {
    fetchModelItems(selectedSource.value, sourceItems)
})

watch(selectedRelated, (model) => {
    fetchModelItems(selectedRelated.value, relatedItems)
})

// watch(selectedSourceItem, (item) => {
//     console.log('selectedSourceItem!!!',selectedSourceItem)
// })


watch(internalModelValue, () => {
    fetchSourceModels();
    fetchRelateableModels();
})

// Fetch relateable models on component mount
// onMounted(() => {
//     fetchSourceModels();
//     fetchRelateableModels();
// });

function confirm() {
    let data = {
            sourceType: selectedSource.value,
            relatedType: selectedRelated.value,
            sourceId: selectedSourceItem.value,
            relatedId: selectedRelatedItem.value,
        }

    relateModels(data)



    // emit('confirmRelation', {
    //     sourceType: selectedSource.value,
    //     relatedType: selectedRelated.value,
    //     sourceId: selectedSourceItem.value,
    //     relatedIdId: selectedRelatedItem.value,
    // });


    internalModelValue.value = false;
}

function cancel() {
    internalModelValue.value = false;
}
</script>
