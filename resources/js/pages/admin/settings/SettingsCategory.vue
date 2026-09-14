<template>
    <div class="flex-grow-1">
        <template v-if="category">
            <page-header
                :title="category.title"
                :subtitle="category.description"
                :icon="category.icon"
                :back-to="{ name: 'admin-settings' }"
                fluid
            >
                <v-breadcrumbs :items="breadcrumbs" density="compact" class="pa-0 text-caption">
                    <template v-slot:divider>
                        <v-icon size="small">mdi-chevron-right</v-icon>
                    </template>
                </v-breadcrumbs>
            </page-header>

            <v-container fluid class="pa-2 pa-sm-4">
                <v-row>
                    <v-col
                        v-for="setting in category.settings"
                        :key="setting.id"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <settings-item-card
                            :title="setting.title"
                            :description="setting.description"
                            :icon="setting.icon"
                            :color="category.color"
                            @click="navigateToSetting(setting)"
                        />
                    </v-col>
                </v-row>
            </v-container>
        </template>

        <!-- 404 State -->
        <template v-else>
            <page-header
                :title="$t('settings.category.notFound')"
                icon="mdi-alert-circle-outline"
                :back-to="{ name: 'admin-settings' }"
                fluid
            />
            <v-container fluid class="pa-2 pa-sm-4">
                <empty-state
                    icon="mdi-alert-circle-outline"
                    :title="$t('settings.category.notFound')"
                    :text="$t('settings.category.notFoundMessage')"
                >
                    <template #actions>
                        <v-btn
                            color="primary"
                            variant="flat"
                            prepend-icon="mdi-arrow-left"
                            @click="goBack"
                        >
                            {{ $t('settings.category.backToSettings') }}
                        </v-btn>
                    </template>
                </empty-state>
            </v-container>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { getCategoryBySlug } from '@/configs/settingsConfig';
import PageHeader from '@/components/common/PageHeader.vue';
import EmptyState from '@/components/common/EmptyState.vue';
import SettingsItemCard from '@/components/settings/SettingsItemCard.vue';

const { t } = useI18n();
const router = useRouter();
const route = useRoute();

const category = computed(() => getCategoryBySlug(route.params.category));

const breadcrumbs = computed(() => [
    {
        title: t('settings.overview.settings'),
        disabled: false,
        to: { name: 'admin-settings' }
    },
    {
        title: category.value?.title || t('settings.category.category'),
        disabled: true
    }
]);

function goBack() {
    router.push({ name: 'admin-settings' });
}

function navigateToSetting(setting) {
    if (setting.routeName) {
        router.push({ name: setting.routeName });
        return;
    }
    router.push({
        name: 'admin-settings-page',
        params: {
            category: route.params.category,
            setting: setting.id
        }
    });
}
</script>
