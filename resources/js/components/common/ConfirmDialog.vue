<template>
    <!-- Bind modelValue to the dialog's v-model -->
    <VDialog v-model="internalModelValue" max-width="400">
        <VCard>
            <VCardTitle class="text-h5">{{ title || $t('dialogs.confirm.title') }}</VCardTitle>
            <VCardText>
                <p v-if="content">{{ content }}</p>
                <VTextField
                    v-if="confirmationKeyword"
                    v-model="textField"
                    :label="$t('dialogs.confirm.typeToConfirm')"
                    variant="underlined"
                />
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn color="grey" @click="cancel">{{ cancellationText || $t('common.cancel') }}</VBtn>
                <VBtn color="red" :disabled="confirmationButtonDisabled" @click="confirm">
                    {{ confirmationText || $t('dialogs.confirm.yes') }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

// Define the modelValue prop
const props = defineProps({
    modelValue: Boolean, // Highlight: Bind modelValue to control dialog visibility
    title: String,
    content: String,
    confirmationKeyword: String,
    confirmationText: {type: String, default: 'Yes'},
    cancellationText: {type: String, default: 'Cancel'},
    resolve: {type: Function, required: true},
});

const emit = defineEmits(['update:modelValue']); // Highlight: Emit update for modelValue
const textField = ref('');

// Create an internal computed property for modelValue
const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value), // Highlight: Emit changes
});

function confirm() {
    props.resolve(true);
    internalModelValue.value = false;
}

function cancel() {
    props.resolve(false);
    internalModelValue.value = false;
}

// Disable the confirmation button if keyword is required but not matched
const confirmationButtonDisabled = computed(() => {
    return props.confirmationKeyword && props.confirmationKeyword !== textField.value;
});
</script>
