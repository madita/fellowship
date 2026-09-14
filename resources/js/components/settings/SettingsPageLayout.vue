<template>
    <div class="flex-grow-1">
        <page-header
            :title="title"
            :subtitle="description"
            :icon="icon"
            :back-to="backTo"
            fluid
        >
            <template v-if="showSaveButton" #actions>
                <v-btn
                    :loading="isSaving"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save"
                    class="d-none d-sm-flex"
                    @click="$emit('save')"
                >
                    {{ $t('common.save') }}
                </v-btn>
            </template>

            <v-breadcrumbs
                v-if="breadcrumbs.length > 0"
                :items="breadcrumbs"
                density="compact"
                class="pa-0 text-caption"
            >
                <template v-slot:divider>
                    <v-icon size="small">mdi-chevron-right</v-icon>
                </template>
            </v-breadcrumbs>
        </page-header>

        <v-container fluid class="pa-2 pa-sm-4">
            <slot></slot>

            <!-- Mobile save button -->
            <div v-if="showSaveButton" class="d-sm-none mt-4">
                <v-btn
                    :loading="isSaving"
                    block
                    size="large"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-content-save"
                    @click="$emit('save')"
                >
                    {{ $t('settings.saveSettings') }}
                </v-btn>
            </div>
        </v-container>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import PageHeader from '@/components/common/PageHeader.vue';

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
    // Kept for backwards compatibility with existing pages; the shared
    // PageHeader renders the back button in the theme colour.
    backButtonColor: {
        type: String,
        default: ''
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

defineEmits(['save']);

const route = useRoute();

const backTo = computed(() => props.backRoute || { name: 'admin-settings' });

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
</script>
