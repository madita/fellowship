<template>
    <div>
        <!-- Loading state -->
        <v-skeleton-loader v-if="isLoading" type="article, article, article" />

        <!-- Dynamic sections rendering -->
        <template v-else-if="sections.length > 0">
            <template v-for="section in sections" :key="section.id">
                <!-- SVG Decoration above section (optional) -->
                <svg-decoration
                    v-if="section.config?.showDecoration"
                    :style-type="section.config?.decorationStyle || 'wave1'"
                    :color="section.config?.decorationColor || '#f2f5f8'"
                    :background-type="section.config?.decorationBackgroundType || 'transparent'"
                    :background-color="section.config?.decorationBackgroundColor || 'transparent'"
                    :background-theme="section.config?.decorationBackgroundTheme || 'background'"
                    position="top"
                />

                <v-sheet
                    :id="section.anchor_id"
                    :class="section.config?.background || ''"
                    :style="section.config?.backgroundColor ? { backgroundColor: section.config.backgroundColor } : {}"
                    class="homepage-section"
                >
                    <!-- Full-width sections (no container) -->
                <template v-if="section.config?.fullWidth">
                    <v-row no-gutters>
                        <v-col
                            v-for="(colWidth, colIndex) in getColumnWidths(section.layout)"
                            :key="colIndex"
                            :cols="12"
                            :md="colWidth"
                        >
                            <template v-for="widget in getWidgetsForColumn(section, colIndex + 1)" :key="widget.id">
                                <component
                                    :is="getWidgetComponent(widget.type)"
                                    :content="widget.content"
                                    :config="widget.config"
                                    class="homepage-widget"
                                />
                            </template>
                        </v-col>
                    </v-row>
                </template>

                <!-- Contained sections (with container) -->
                <v-container v-else :class="section.config?.containerClass || ''">
                    <v-row>
                        <v-col
                            v-for="(colWidth, colIndex) in getColumnWidths(section.layout)"
                            :key="colIndex"
                            :cols="12"
                            :md="colWidth"
                        >
                            <template v-for="widget in getWidgetsForColumn(section, colIndex + 1)" :key="widget.id">
                                <component
                                    :is="getWidgetComponent(widget.type)"
                                    :content="widget.content"
                                    :config="widget.config"
                                    class="homepage-widget"
                                />
                            </template>
                        </v-col>
                    </v-row>
                </v-container>
                </v-sheet>
            </template>
        </template>

        <!-- Fallback for old widget-only rendering (no sections) -->
        <template v-else-if="widgets.length > 0">
            <component
                v-for="widget in widgets"
                :key="widget.id"
                :is="getWidgetComponent(widget.type)"
                :content="widget.content"
                :config="widget.config"
                :id="widget.anchor_id"
                class="homepage-widget"
            />
        </template>

        <!-- Fallback: Original static content when no widgets -->
        <template v-else>
            <v-sheet>
                <v-container class="py-6 pt-lg-15 text-center">
                    <h1 class="text-h4 text-sm-h3 text-md-h2 text-lg-h1">Your awesome <span class="text-primary">community</span></h1>
                    <h2 class="text-h6 text-sm-h5 mt-4 w-full w-md-8-12 w-xl-half mx-auto">Lorem ipsum dolor sit, amet consectetur adipisicing elit. Commodi ex facilis ad atque natus tenetur debitis qui quisquam iure amet.</h2>
                    <div class="mt-8">
                        <v-btn to="/auth/signup" size="large" class="my-1 mx-sm-1 w-full w-sm-auto" color="primary">Join Now!</v-btn>
                        <v-btn size="large" class="my-1 mx-sm-1 w-full w-sm-auto">Learn more</v-btn>
                    </div>
                </v-container>
            </v-sheet>

            <Partners />
            <Stats />

            <v-container>
                <v-divider></v-divider>
            </v-container>

            <Feature2 />
            <Feature1 id="#feature1" />
            <CallToAction />
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useHomepageStore } from '@/store/homepageStore';
import { getWidgetComponent } from '@/components/landing/widgets';

// Section components
import SvgDecoration from '@/components/landing/SvgDecoration.vue';

// Fallback components
import Partners from '@/components/landing/Partners.vue';
import Stats from '@/components/landing/Stats.vue';
import Feature1 from '@/components/landing/Feature1.vue';
import CallToAction from '@/components/landing/CallToAction.vue';
import Feature2 from '@/components/landing/Feature2.vue';

const homepageStore = useHomepageStore();
const isLoading = ref(true);

const sections = computed(() => homepageStore.activeSections || []);
const widgets = computed(() => homepageStore.activeWidgets || []);

/**
 * Get column widths for a given layout
 */
function getColumnWidths(layout) {
    const layoutMap = {
        '1-col': [12],
        '2-col': [6, 6],
        '3-col': [4, 4, 4],
        '4-col': [3, 3, 3, 3],
        '2-1-col': [8, 4], // 66% / 33%
        '1-2-col': [4, 8], // 33% / 66%
    };
    return layoutMap[layout] || [12];
}

/**
 * Get widgets for a specific column in a section
 */
function getWidgetsForColumn(section, columnNumber) {
    if (!section.widgets) return [];
    return section.widgets
        .filter(w => w.column === columnNumber && w.enabled)
        .sort((a, b) => a.order - b.order);
}

// Function to load homepage content
async function loadHomepageContent() {
    isLoading.value = true;
    try {
        // Try to fetch sections first (new approach)
        await homepageStore.fetchPublicSections();

        // If no sections but widgets exist, fallback to widget-only mode
        if (sections.value.length === 0) {
            await homepageStore.fetchPublicWidgets();
        }
    } catch (error) {
        console.error('Failed to load homepage:', error);

        // Try fallback to widgets if sections fail
        try {
            await homepageStore.fetchPublicWidgets();
        } catch (widgetError) {
            console.error('Failed to load widgets as fallback:', widgetError);
        }
    } finally {
        isLoading.value = false;
    }
}

// Locale change handler
function onLocaleChange() {
    loadHomepageContent();
}

onMounted(async () => {
    await loadHomepageContent();

    // Listen for locale changes to refetch content in new language
    window.addEventListener('locale-changed', onLocaleChange);
});

onUnmounted(() => {
    // Clean up locale change listener
    window.removeEventListener('locale-changed', onLocaleChange);
});
</script>
