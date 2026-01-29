<template>
    <div class="flex-grow-1">
        <v-container fluid class="pa-2 pa-sm-4">
            <v-card elevation="2">
                <v-card-title class="text-h6 text-sm-h5 font-weight-bold pa-3 pa-sm-6 bg-gradient">
                    <v-icon class="mr-2 mr-sm-3" :size="$vuetify.display.mobile ? 24 : 28">mdi-cog</v-icon>
                    <span class="d-none d-sm-inline">Application </span>Settings
                </v-card-title>

                <v-divider></v-divider>

                <!-- Search Bar -->
                <div class="pa-4">
                    <v-text-field
                        v-model="searchQuery"
                        prepend-inner-icon="mdi-magnify"
                        label="Search settings..."
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        clearable
                        @click:clear="searchQuery = ''"
                    ></v-text-field>
                </div>

                <!-- Quick Stats -->
                <div class="px-4 pb-2">
                    <v-row dense>
                        <v-col cols="6" sm="4" md="2">
                            <v-card variant="tonal" color="primary">
                                <v-card-text class="text-center pa-3">
                                    <div class="text-h5 font-weight-bold">{{ categoriesCount }}</div>
                                    <div class="text-caption">Categories</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col cols="6" sm="4" md="2">
                            <v-card variant="tonal" color="success">
                                <v-card-text class="text-center pa-3">
                                    <div class="text-h5 font-weight-bold">{{ totalSettings }}</div>
                                    <div class="text-caption">Settings</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                        <v-col v-if="searchQuery" cols="12" sm="4" md="2">
                            <v-card variant="tonal" color="info">
                                <v-card-text class="text-center pa-3">
                                    <div class="text-h5 font-weight-bold">{{ filteredSettingsCount }}</div>
                                    <div class="text-caption">Results</div>
                                </v-card-text>
                            </v-card>
                        </v-col>
                    </v-row>
                </div>

                <v-divider class="mt-2"></v-divider>

                <v-card-text class="pa-4 pa-md-6">
                    <!-- No Results -->
                    <v-alert
                        v-if="searchQuery && filteredSettingsCount === 0"
                        type="info"
                        variant="tonal"
                        class="mb-4"
                    >
                        No settings found matching "{{ searchQuery }}". Try a different search term.
                    </v-alert>

                    <!-- Settings grouped by category -->
                    <div
                        v-for="category in filteredCategories"
                        :key="category.id"
                        class="mb-6"
                    >
                        <!-- Category Header -->
                        <div class="d-flex align-center mb-3">
                            <v-avatar :color="category.color" size="36" class="mr-3">
                                <v-icon color="white" size="20">{{ category.icon }}</v-icon>
                            </v-avatar>
                            <div>
                                <div class="text-h6 font-weight-medium">{{ category.title }}</div>
                                <div class="text-caption text-medium-emphasis">{{ category.description }}</div>
                            </div>
                        </div>

                        <!-- Settings Grid -->
                        <v-row>
                            <v-col
                                v-for="setting in getFilteredSettings(category)"
                                :key="`${category.id}-${setting.id}`"
                                cols="12"
                                sm="6"
                                lg="4"
                            >
                                <settings-item-card
                                    :title="setting.title"
                                    :description="setting.description"
                                    :icon="setting.icon"
                                    :color="category.color"
                                    @click="navigateToSetting(category.id, setting.id)"
                                />
                            </v-col>
                        </v-row>

                        <v-divider v-if="category !== filteredCategories[filteredCategories.length - 1]" class="mt-4"></v-divider>
                    </div>
                </v-card-text>
            </v-card>
        </v-container>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { settingsCategories, getTotalSettingsCount } from '@/configs/settingsConfig';
import SettingsItemCard from '@/components/settings/SettingsItemCard.vue';

const router = useRouter();
const searchQuery = ref('');

const categoriesCount = computed(() => settingsCategories.length);
const totalSettings = computed(() => getTotalSettingsCount());

// Filter categories based on search query
const filteredCategories = computed(() => {
    if (!searchQuery.value) {
        return settingsCategories;
    }

    const query = searchQuery.value.toLowerCase();
    return settingsCategories.filter(category => {
        // Check if category matches
        const categoryMatches = category.title.toLowerCase().includes(query) ||
            category.description.toLowerCase().includes(query);

        // Check if any settings in this category match
        const hasMatchingSettings = category.settings.some(setting =>
            setting.title.toLowerCase().includes(query) ||
            setting.description.toLowerCase().includes(query)
        );

        return categoryMatches || hasMatchingSettings;
    });
});

// Get filtered settings for a category
function getFilteredSettings(category) {
    if (!searchQuery.value) {
        return category.settings;
    }

    const query = searchQuery.value.toLowerCase();

    // If category title matches, show all settings
    const categoryMatches = category.title.toLowerCase().includes(query) ||
        category.description.toLowerCase().includes(query);

    if (categoryMatches) {
        return category.settings;
    }

    // Otherwise filter settings
    return category.settings.filter(setting =>
        setting.title.toLowerCase().includes(query) ||
        setting.description.toLowerCase().includes(query)
    );
}

// Count total filtered settings
const filteredSettingsCount = computed(() => {
    return filteredCategories.value.reduce((total, category) => {
        return total + getFilteredSettings(category).length;
    }, 0);
});

function navigateToSetting(categoryId, settingId) {
    router.push({
        name: 'admin-settings-page',
        params: {
            category: categoryId,
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
