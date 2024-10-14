<script setup>
import {ref, watch, computed, nextTick, onMounted} from 'vue';
import {PerfectScrollbar} from 'vue3-perfect-scrollbar'
import {VForm} from 'vuetify/components/VForm'
import axios from "axios";
import Tiptap from "@/components/common/tiptap/Tiptap.vue";
import DataTableJsonField from "@/components/common/DataTable/DataTableJsonField.vue";


// 👉 store
const props = defineProps({
    isDrawerOpen: {
        type: Boolean,
        required: true,
    },
    response: {},
    defaultItem: {},
    item: {},
})

const isFocused = ref(true)
const loadItemDetails = ref(true)
const editedItem = ref(props.defaultItem)

const emit = defineEmits([
    'update:isDrawerOpen',
    'addItem',
    'updateItem',
    'removeItem',
])

const localEditMode = ref(props.editModed)

// const options = ref()

const title = ref()

// const store = useCalendarStore()
const refForm = ref()
// const availableCalendars = ref(['test', 'AA'])

// 👉 Item
// const item = ref({})
const item = ref(JSON.parse(JSON.stringify(props.item)))
const itemDetails = ref();

// const options = ref ({
//     answers: { yes: 'Yes', maybe: 'Maybe' },
//     permissions: ['edit', 'view'],
//     profil: ['user', 'admin'],
//     showAttributtes: ['AllDay', 'startDate', 'endDate'],
//     location: ['custom', 'real', 'virtual']
// })
// const item = ref({...props.item})

// const updateJsonInput = async (json) => {
//     // console.log('addevent', addevent)
//     axios.post(`${endpoint}`, addevent).then(() => {
//         event.value = null
//         // this.page = {title: "", body: ""};
//         // this.message = "Page saved ..link"
//     }).catch((error) => {
//         if (error.response.status === 422) {
//             // this.creating.errors = error.response.data
//             this.editing.errors = error.response.data
//         }
//     })
// }


const resetItem = () => {
    item.value = JSON.parse(JSON.stringify(props.item))
    isStartDateValid.value = true;
    isEndDateValid.value = true;
    if(item.value.id > 0) {
        getItem(item.value.id);
    }

    nextTick(() => {
        refForm.value?.resetValidation()
    })
}



const removeItem = () => {
    emit('removeItem', String(item.value.id))

    // Close drawer
    emit('update:isDrawerOpen', false)
}

const handleSubmit = () => {
    // console.log('handlesubmit')
    // validateStartDate();
    // validateEndDate();
    refForm.value?.validate().then(({valid}) => {
        console.log(valid)
        if (valid) {
            localEditMode.value = false;

            // If id exist on id => Update item
            if ('id' in editedItem.value)
                emit('updateItem', editedItem.value)

            // Else => add new item
            else
                emit('addItem', editedItem.value)

            // Close drawer
            emit('update:isDrawerOpen', false)
        }
    })
}

onMounted(() => {

    //console.log('itemonsidebar', item)
});

// 👉 Form
const onCancel = () => {
    // Close drawer
    emit('update:isDrawerOpen', false)
    localEditMode.value = false;
    nextTick(() => {
        refForm.value?.reset()
        resetItem()
        refForm.value?.resetValidation()

        isStartDateValid.value = true;
        isEndDateValid.value = true;

    })
}

// const onYes = () => {

//
//     joinItem(item.value.id, 'going')
//     // Close drawer
//     // emit('update:isDrawerOpen', false)
//
// }

// 👉 Form
// const onNo = () => {
//     // Close drawer
//     // emit('update:isDrawerOpen', false)
//     joinItem(item.value.id, 'notgoing')
// }
//
// const onMaybe = () => {
//     // Close drawer
//     joinItem(item.value.id, 'maybe')
// }
//
// const  getIsGoing = (answer) => {
//     //item.value.id > 0
//     if (loadItemDetails.value) {
//         return true;
//     }
//
//     if (itemDetails.value === null && typeof itemDetails.value != undefined) {
//         return false;
//     }
//     return itemDetails.value.isGoing !== undefined && itemDetails.value.isGoing.type === answer
// }

const joinItem = (itemId, answer) => {
    // console.log('joinItem', itemId, answer)
    itemDetails.value.isGoing.type = answer;
    //todogetfulldate..days
    const userData = {
        answer: answer,
        bringing: 'stuff',
        days: '3'
    }

    axios.post(`/api/items/${itemId}/going`, userData).then(() => {
        // this.page = {title: "", body: ""};
        // this.message = "Page saved ..link"
    }).catch((error) => {
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            // this.editing.errors = error.response.data
        }
    })
}

const getItem = async (itemId) => {
    loadItemDetails.value = true;
    // error.value = null; // Reset previous errors

    try {
        const response = await axios.get(`/api/items/${itemId}`);
        itemDetails.value = response.data; // Assuming the data is directly in the response
        console.log('Item details loaded:', itemDetails.value);
    } catch (err) {
        console.error('Failed to load item details:', err);
        // error.value = 'Failed to load item details'; // Store error message
        itemDetails.value = null; // Reset item details on error
    } finally {
        loadItemDetails.value = false; // Ensure loading state is reset
    }
};


const startDateTimePickerConfig = computed(() => {
    const config = {
        enableTime: !item.value.allDay,
        dateFormat: `Y-m-d${item.value.allDay ? '' : ' H:i'}`,
    }

    if (item.value.end)
        config.maxDate = item.value.end

    return config
})

const startDateError = ref(''); // Error message for start date
const endDateError = ref(''); // Error message for end date
const isStartDateValid = ref(true);
const isEndDateValid = ref(true);

const validateStartDate = () => {
    if (!item.value.start) {
        startDateError.value = 'Start date is required';
        isStartDateValid.value = false; // Set to false if validation fails
    } else {
        startDateError.value = '';
        isStartDateValid.value = true; // Set to true if validation passes
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

const endDateTimePickerConfig = computed(() => {
    const config = {
        enableTime: !item.value.allDay,
        dateFormat: `Y-m-d${item.value.allDay ? '' : ' H:i'}`,
    }

    if (item.value.start)
        config.minDate = item.value.start

    return config
})

const dialogModelValueUpdate = val => {
    emit('update:isDrawerOpen', val)
}

// Modal methods for adding key-value pairs
// const addItem = () => {
//     if (newKey.value) {
//         items.value.push({ key: newKey.value, value: newValue.value });
//         newKey.value = '';
//         newValue.value = '';
//         closeModal();
//     }
// };
//
// const removeKeyValuePair = (index) => {
//     items.value.splice(index, 1);
// };
//
// const generateJson = () => {
//     const jsonData = {};
//     items.value.forEach((item) => {
//         if (item.key) {
//             jsonData[item.key] = item.value;
//         }
//     });
//     generatedJson.value = JSON.stringify(jsonData, null, 2);
// };

watch(() => props.item, (newItem) => {
    item.value = {...newItem};
}, {deep: true, immediate: true});

// watch(() => props.editMode, (editMode) => {
//     localEditMode.value = editMode;
// }, {deep: true, immediate: true});
watch(() => props.editMode, () => {
    localEditMode.value = props.editMode
});

watch(() => props.isDrawerOpen, resetItem)

// const rules = {
//     title: [
//         v => !!v || 'Title is required', // Ensures the title is not empty
//     ],
//     date: [
//         v => !!v || 'Date is required', // Ensures the date is not empty
//     ],
//     location: [
//         v => !!v || 'Location is required', // Ensures the location is not empty
//     ],
// }

// onMounted( () => {
//     title.value = item.value.id ? 'Update Item' : 'Add Item'
// });
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

        <div class="pa-2 d-flex align-center" v-if="localEditMode">
            <h5 class="text-h5 me-3">
                <template v-if="item.id">Update Item</template>
                <template v-else>Add Item</template>
            </h5>

            <VSpacer/>

            <slot name="beforeClose"/>

            del x

        </div>
        <div class="pa-2 d-flex align-center" v-else>
            <h5 class="text-h5 me-3">
                Add/Edit Item
            </h5>


            <VSpacer/>
            <slot name="beforeClose"/>

        </div>

        <VDivider/>

        <PerfectScrollbar :options="{ wheelPropagation: false }">
            <VCard flat>
                <VCardText>
                    <!-- SECTION Form -->
                    <VForm
                        ref="refForm"
                        @submit.prevent="handleSubmit"
                    >
                        <v-row v-for="column in response.updatable" :key="`card-${column}`">
                            <v-col
                                cols="12"
                            >
                                <template v-if="typeof(response.column_fields[column]) === 'object'">
                                    <v-select
                                        v-if="'select' in response.column_fields[column]"
                                        :items="response.column_fields[column]['select']"
                                        v-model="editedItem[column]"
                                        :label="column"
                                    ></v-select>
                                </template>
                                <v-textarea
                                    v-else-if="response.column_fields[column]==='textarea'"
                                    :label="column"
                                    :id="column"
                                    v-model="editedItem[column]"
                                    :value="editedItem[column]"
                                ></v-textarea>

                                <tiptap
                                    v-else-if="response.column_fields[column]==='wysiwyg'"
                                    v-model:modelValue="editedItem[column]"
                                    :value="editedItem[column]"
                                    id="text-content" name="content"/>

<!--                                <simple-editor-->
<!--                                    v-else-if="response.column_fields[column]==='wysiwyg'"-->
<!--                                    v-model="editedItem[column]"-->
<!--                                    :value="editedItem[column]">-->
<!--                                </simple-editor>-->

                                <v-checkbox
                                    v-else-if="response.column_fields[column]==='checkbox'"
                                    v-model="editedItem[column]"
                                    :value="editedItem[column]"
                                    :label="column"
                                ></v-checkbox>

                                <v-color-picker
                                    v-else-if="response.column_fields[column]==='color'"
                                    v-model="editedItem[column]"
                                    mode="hex"
                                ></v-color-picker>

                                <DataTableJsonField
                                    v-else-if="response.column_fields[column]==='json'"
                                    :options="response.json_fields"
                                    v-model:modelValue="editedItem[column]"></DataTableJsonField>


                                <v-text-field
                                    v-else
                                    v-model="editedItem[column]"

                                    :label="column"
                                ></v-text-field>


                                <!--                                                        <span class="help-block" v-if="creating.errors[column]">-->
                                <!--                                <strong>{{ creating.errors[column][0] }}</strong>-->
                            </v-col>
                        </v-row>
                        <VRow>
                            <!-- 👉 Title -->
<!--                            <VCol cols="12">-->
<!--                                <VTextField-->
<!--                                    v-model="item.title"-->
<!--                                    label="Title"-->
<!--                                    :rules="rules.title"-->
<!--                                    placeholder="Title"-->
<!--                                />-->
<!--                            </VCol>-->


<!--                            &lt;!&ndash; 👉 Start date &ndash;&gt;-->
<!--                            <VCol cols="12">-->

<!--                                <CustomDatePicker-->
<!--                                    label="Start Date"-->
<!--                                    v-model="item.start"-->
<!--                                    :allDay="item.allDay"-->
<!--                                    :error="!isStartDateValid"-->
<!--                                    :error-messages="['Date is required']"-->
<!--                                    :date-picker-config="{ enableTimePicker: true }"-->
<!--                                />-->
<!--                            </VCol>-->

<!--                            &lt;!&ndash; 👉 End date &ndash;&gt;-->
<!--                            <VCol cols="12">-->

<!--                                <CustomDatePicker-->
<!--                                    label="End Date"-->
<!--                                    v-model="item.end"-->
<!--                                    :allDay="item.allDay"-->
<!--                                    :error="!isEndDateValid"-->
<!--                                    :error-messages="['Date is required']"-->
<!--                                    :date-picker-config="{ enableTimePicker: true }"-->
<!--                                />-->

<!--                            </VCol>-->

<!--                            &lt;!&ndash; 👉 All day &ndash;&gt;-->
<!--                            <VCol cols="12">-->
<!--                                <VSwitch-->
<!--                                    color="primary"-->
<!--                                    v-model="item.allDay"-->
<!--                                    label="All day"-->
<!--                                />-->
<!--                            </VCol>-->

<!--                            &lt;!&ndash; 👉 Location &ndash;&gt;-->
<!--                            <VCol cols="12">-->
<!--                                <VTextField-->
<!--                                    v-model="item.extendedProps.location"-->
<!--                                    :rules="rules.location"-->
<!--                                    label="Location"-->
<!--                                    placeholder="Meeting room"-->
<!--                                />-->
<!--                            </VCol>-->

                            <!-- 👉 Description -->
                            <VCol cols="12">
<!--                                <VTextarea-->
<!--                                    v-model="item.extendedProps.description"-->
<!--                                    label="Description"-->
<!--                                    placeholder="Meeting description"-->
<!--                                />-->
                            </VCol>

                            <!-- 👉 Form buttons -->
                            <VCol cols="12">
                                <VBtn
                                    type="submit"
                                    class="me-3"
                                >
                                    Submit
                                </VBtn>
                                <VBtn
                                    variant="outlined"
                                    color="secondary"
                                    @click="onCancel"
                                >
                                    Cancel
                                </VBtn>
                            </VCol>
                        </VRow>
                    </VForm>
                    <!-- !SECTION -->
                </VCardText>
            </VCard>



        </PerfectScrollbar>
    </VNavigationDrawer>
    <!-- Modal for adding new answer key-value pair -->


<!--    <v-dialog v-if="showModal" max-width="500">-->



<!--            <v-card title="Options">-->
<!--                <v-card-text>-->
<!--                    <h3>Add Answer</h3>-->
<!--                    <v-text-field v-model="newAnswerKey" label="Key" class="mb-3"></v-text-field>-->
<!--                    <v-text-field v-model="newAnswerValue" label="Value" class="mb-3"></v-text-field>-->
<!--                </v-card-text>-->

<!--                <v-card-actions>-->
<!--                    <v-spacer></v-spacer>-->

<!--                    <v-btn @click="addAnswer" color="primary">Add</v-btn>-->
<!--                    <v-btn-->
<!--                        text="Cancel"-->
<!--                        @click="isActive.value = false"-->
<!--                    ></v-btn>-->
<!--                </v-card-actions>-->

<!--            </v-card>-->
<!--    </v-dialog>-->
</template>

