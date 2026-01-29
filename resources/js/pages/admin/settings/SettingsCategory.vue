<template>
    <div class="flex-grow-1">
        <v-container fluid class="pa-2 pa-sm-4">
            <v-card v-if="category" elevation="2">
                <v-card-title class="text-h6 text-sm-h5 font-weight-bold pa-3 pa-sm-6 bg-gradient">
                    <div class="d-flex align-center">
                        <v-btn
                            icon
                            variant="text"
                            color="white"
                            class="mr-2"
                            @click="goBack"
                        >
                            <v-icon>mdi-arrow-left</v-icon>
                        </v-btn>
                        <v-icon class="mr-2 mr-sm-3" :size="$vuetify.display.mobile ? 24 : 28">{{ category.icon }}</v-icon>
                        <span>{{ category.title }}</span>
                    </div>
                </v-card-title>

                <!-- Breadcrumbs -->
                <v-breadcrumbs :items="breadcrumbs" class="px-4 py-2 text-caption">
                    <template v-slot:divider>
                        <v-icon size="small">mdi-chevron-right</v-icon>
                    </template>
                </v-breadcrumbs>

                <v-divider></v-divider>

                <v-card-subtitle class="py-4 px-6 text-body-2">
                    {{ category.description }}
                </v-card-subtitle>

                <v-card-text class="pa-4 pa-md-6">
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
                                @click="navigateToSetting(setting.id)"
                            />
                        </v-col>
                    </v-row>
                </v-card-text>
            </v-card>

            <!-- 404 State -->
            <v-card v-else elevation="2">
                <v-card-text class="text-center py-12">
                    <v-icon size="80" color="grey-lighten-1">mdi-alert-circle-outline</v-icon>
                    <div class="text-h5 mt-4">Category Not Found</div>
                    <div class="text-body-2 text-medium-emphasis mt-2">
                        The settings category you're looking for doesn't exist.
                    </div>
                    <v-btn
                        color="primary"
                        class="mt-6"
                        @click="goBack"
                        prepend-icon="mdi-arrow-left"
                    >
                        Back to Settings
                    </v-btn>
                </v-card-text>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { getCategoryBySlug } from '@/configs/settingsConfig';
import SettingsItemCard from '@/components/settings/SettingsItemCard.vue';

const router = useRouter();
const route = useRoute();

const category = computed(() => getCategoryBySlug(route.params.category));

const breadcrumbs = computed(() => [
    {
        title: 'Settings',
        disabled: false,
        to: { name: 'admin-settings' }
    },
    {
        title: category.value?.title || 'Category',
        disabled: true
    }
]);

function goBack() {
    router.push({ name: 'admin-settings' });
}

function navigateToSetting(settingId) {
    router.push({
        name: 'admin-settings-page',
        params: {
            category: route.params.category,
            setting: settingId
        }
    });
}
</script>

<style scoped>
.bg-gradient {
    background: linear-gradient(135deg, rgb(var(--v-theme-primary)) 0%, rgb(var(--v-theme-secondary)) 100%);
    color: white;
}
</style>
