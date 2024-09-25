<script setup>
import {ref, watch} from 'vue';
// Modal state for JSON key-value pairs
const showModal = ref(false);
// const newKey = ref('');
// const newValue = ref('');
// const items = ref([{ key: '', value: '' }]);
// const generatedJson = ref('');


const emit = defineEmits([
    'update:jsonValue',
])

// States for edit functionality
const editIndex = ref(null);
const editKey = ref('');
const editValue = ref('');

const newAnswerKey = ref('');
const newAnswerValue = ref('');
const answers = ref({});
const profil = ref(['player']);
const permissions = ref(['edit']);
const generatedJson = ref('');
// Adding a new answer (key-value pair)
const addAnswer = () => {
    if (newAnswerKey.value && newAnswerValue.value) {
        answers.value[newAnswerKey.value] = newAnswerValue.value;
        newAnswerKey.value = '';
        newAnswerValue.value = '';
        closeModal();
    }
};

// Remove an existing answer
const removeAnswer = (key) => {
    delete answers.value[key];
};

// Function to trigger edit mode
// Function to trigger edit mode
const editAnswer = (key, value, index) => {
    editIndex.value = index;
    editKey.value = key;
    editValue.value = value;
};

// Function to save edited key-value pair
const saveEdit = (index) => {
    const keys = Object.keys(answers.value);
    const oldKey = keys[index];

    // Delete old key and set new key-value pair
    if (oldKey !== editKey.value) {
        delete answers.value[oldKey];
    }
    answers.value[editKey.value] = editValue.value;

    // Exit edit mode
    editIndex.value = null;
    editKey.value = '';
    editValue.value = '';
};

// Generate JSON dynamically based on inputs
const generateJson = () => {
    const jsonData = {
        answers: answers.value,
        profil: profil.value,
        permissions: permissions.value,
    };
    generatedJson.value = JSON.stringify(jsonData, null, 2);

    emit('update:jsonValue', generatedJson.value);
};


const closeModal = () => {
    showModal.value = false;
};



// Watchers to trigger the JSON generation whenever something changes
watch(answers, generateJson, { deep: true });
watch(profil, generateJson, { deep: true });
watch(permissions, generateJson, { deep: true });

</script>

<template>
    <v-row>
        <v-col cols="12">
            <h4>Options</h4>
            <h5>Answers</h5>
            <!-- Display existing answers -->

            <V-row v-for="(value, key, index) in answers" :key="key" class="answer-item d-flex align-center mb-1 ml-2">
                <!-- Display key and value or editable fields if in edit mode -->
                <template v-if="editIndex === index" class="d-flex align-center">

                    <v-text-field v-model="editKey" label="Key" class="me-2 v-col-5" />
                    <v-text-field v-model="editValue" label="Value" class="me-2 v-col-5" />
                    <v-icon @click="saveEdit(index)">mdi-check</v-icon>

                </template>
                <template v-else>
                    <span>{{ key }}: {{ value }}</span>
                    <span>
                                                            <v-icon class="edit-icon" @click="editAnswer(key, value, index)">mdi-pencil</v-icon>
                                                            <v-icon class="delete-icon" @click="removeAnswer(key)">mdi-delete</v-icon>
                                                        </span>

                </template>
            </V-row>

            <v-btn v-if="!showModal" @click="showModal = true" class="mb-3">Add Answer</v-btn>
            <template v-if="showModal">
                <v-row>
                    <v-col cols="6">
                        <v-text-field v-model="newAnswerKey" label="Key" class="mb-3"></v-text-field>
                    </v-col>
                    <v-col cols="6">
                        <v-text-field v-model="newAnswerValue" label="Value" class="mb-3"></v-text-field>
                    </v-col>
                </v-row>

                <v-btn @click="addAnswer" color="primary">Add</v-btn>
                <v-btn
                    text="Cancel"
                    @click="showModal = false"
                ></v-btn>
            </template>
        </v-col>

        <v-col cols="6">
            <v-select
                v-model="profil"
                :items="['player', 'admin', 'user']"
                label="Profil"
                multiple
            ></v-select>
        </v-col>

        <v-col cols="6">
            <v-select
                v-model="permissions"
                :items="['edit', 'view', 'delete']"
                label="Permissions"
                multiple
            ></v-select>
        </v-col>
    </v-row>

    <!--                                    <VRow>-->
    <!--                                        <VCol cols="12">-->
    <!--                                            <VBtn @click="generateJson" class="me-3">Generate JSON</VBtn>-->
    <!--                                        </VCol>-->
    <!--                                    </VRow>-->

    <!--                                    <VRow>-->
    <!--                                        <VCol cols="12">-->
    <!--                                            <pre>{{ generatedJson }}</pre>-->
    <!--                                        </VCol>-->
    <!--                                    </VRow>-->
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
