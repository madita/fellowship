<template>
    <div class="flex-grow-1">
        <v-container fluid class="pa-2 pa-sm-4">
            <v-card elevation="2">
                <!-- Header with gradient -->
                <v-card-title class="text-h6 text-sm-h5 font-weight-bold pa-3 pa-sm-6 bg-gradient">
                    <div class="d-flex align-center justify-space-between w-100">
                        <div class="d-flex align-center">
                            <v-btn
                                icon
                                variant="text"
                                :color="backButtonColor"
                                class="mr-2"
                                @click="goBack"
                            >
                                <v-icon>mdi-arrow-left</v-icon>
                            </v-btn>
                            <v-icon v-if="icon" class="mr-2 mr-sm-3" :size="$vuetify.display.mobile ? 24 : 28">{{ icon }}</v-icon>
                            <span>{{ title }}</span>
                        </div>
                        <v-btn
                            v-if="showSaveButton"
                            :loading="isSaving"
                            color="white"
                            variant="elevated"
                            @click="$emit('save')"
                            prepend-icon="mdi-content-save"
                            class="d-none d-sm-flex"
                        >
                            {{ $t('common.save') }}
                        </v-btn>
                    </div>
                </v-card-title>

                <!-- Breadcrumbs -->
                <v-breadcrumbs
                    v-if="breadcrumbs.length > 0"
                    :items="breadcrumbs"
                    class="px-4 py-2 text-caption"
                >
                    <template v-slot:divider>
                        <v-icon size="small">mdi-chevron-right</v-icon>
                    </template>
                </v-breadcrumbs>

                <v-divider></v-divider>

                <!-- Description -->
                <v-card-subtitle v-if="description" class="py-3 px-4 text-body-2">
                    {{ description }}
                </v-card-subtitle>

                <!-- Main content slot -->
                <v-card-text class="pa-2 pa-sm-4 pa-md-6">
                    <slot></slot>
                </v-card-text>

                <!-- Mobile save button -->
                <v-card-actions v-if="showSaveButton" class="d-sm-none pa-4">
                    <v-btn
                        :loading="isSaving"
                        block
                        size="large"
                        color="primary"
                        @click="$emit('save')"
                        prepend-icon="mdi-content-save"
                    >
                        {{ $t('settings.saveSettings') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    description: {
        type: String,
        default: ''
    },
    icon: {
        type: String,
        default: ''
    },
    backRoute: {
        type: [String, Object],
        default: null
    },
    backButtonColor: {
        type: String,
        default: 'white'
    },
    showSaveButton: {
        type: Boolean,
        default: true
    },
    isSaving: {
        type: Boolean,
        default: false
    },
    categoryTitle: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['save']);

const router = useRouter();
const route = useRoute();

const breadcrumbs = computed(() => {
    const items = [
        {
            title: t('settings.overview.settings'),
            disabled: false,
            to: { name: 'admin-settings' }
        }
    ];

    // Category is shown but not clickable (it's just a section header, not a page)
    if (props.categoryTitle) {
        items.push({
            title: props.categoryTitle,
            disabled: true
        });
    }

    if (props.title && route.params.setting) {
        items.push({
            title: props.title,
            disabled: true
        });
    }

    return items;
});

function goBack() {
    // Always go back to Settings Overview (categories are just headers, not pages)
    router.push({ name: 'admin-settings' });
}
</script>

<style scoped>
.bg-gradient {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    color: white;
}

.w-100 {
    width: 100%;
}
</style>
