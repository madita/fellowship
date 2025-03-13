<template>
    <!-- Bind modelValue to the dialog's v-model -->
    <VDialog v-model="internalModelValue" width="auto">

        <v-card
            min-width="400"
            prepend-icon="mdi-calendar"
            title="Event Details"
        >
            <!-- Toolbar with filter and column selector icons -->
            <v-card-text class="pb-0">
                <v-row>
                    <v-col cols="8">
                        <v-text-field
                            v-model="search"
                            label="Search"
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            hide-details
                            single-line
                        ></v-text-field>
                    </v-col>
                    <v-col cols="4" class="d-flex justify-end">
                        <v-btn
                            icon
                            variant="text"
                            color="primary"
                            class="mr-2"
                            @click="openFilterDialog"
                        >
                            <v-icon>mdi-filter</v-icon>
                            <v-badge
                                v-if="activeFiltersCount > 0"
                                :content="activeFiltersCount.toString()"
                                color="primary"
                                offset-x="12"
                                offset-y="12"
                            ></v-badge>
                        </v-btn>

                        <v-menu
                            v-model="showColumnsBox"
                            :close-on-content-click="false"
                            location="bottom"
                        >
                            <template v-slot:activator="{ props }">
                                <v-btn
                                    icon
                                    v-bind="props"
                                    color="primary"
                                    variant="text"
                                >
                                    <v-icon>mdi-view-column</v-icon>
                                </v-btn>
                            </template>

                            <v-card min-width="300">
                                <v-card-title>Select Options</v-card-title>
                                <v-card-text>
                                    <v-list>
                                        <v-list-item v-for="(item, i) in allColumnOptions" :key="i">
                                            <v-checkbox
                                                v-model="visibleColumns"
                                                :label="item.title"
                                                :value="item.value"
                                                hide-details
                                                density="compact"
                                            ></v-checkbox>
                                        </v-list-item>
                                    </v-list>
                                </v-card-text>
                                <v-card-actions>
                                    <v-spacer></v-spacer>
                                    <v-btn
                                        color="primary"
                                        variant="text"
                                        @click="showColumnsBox = false"
                                    >
                                        Done
                                    </v-btn>
                                </v-card-actions>
                            </v-card>
                        </v-menu>

<!--                        <v-btn-->
<!--                            icon-->
<!--                            variant="text"-->
<!--                            color="primary"-->
<!--                            @click="showColumnsBox1 = true"-->
<!--                        >-->
<!--                            <v-icon>mdi-view-column</v-icon>-->
<!--                        </v-btn>-->
<!--                        <v-card v-if="showColumnsBox">-->
<!--                            <v-card-title>Column Visibility</v-card-title>-->
<!--                            <v-card-text>-->
<!--                                <v-select-->
<!--                                    v-model="visibleColumns"-->
<!--                                    :items="allColumnOptions"-->
<!--                                    label="Select Columns"-->
<!--                                    multiple-->
<!--                                    chips-->
<!--                                    closable-chips-->
<!--                                    variant="outlined"-->
<!--                                ></v-select>-->
<!--                            </v-card-text>-->
<!--                            <v-card-actions>-->
<!--                                <v-spacer></v-spacer>-->
<!--                                <v-btn color="error" variant="text" @click="resetColumns">Reset</v-btn>-->
<!--                                <v-btn color="primary" @click="showColumnsDialog = false">Apply</v-btn>-->
<!--                            </v-card-actions>-->
<!--                        </v-card>-->
                    </v-col>
                </v-row>
            </v-card-text>

            <!-- Filter Dialog -->
            <v-dialog v-model="showFilterDialog" max-width="700">
                <v-card>
                    <v-card-title>Filter Options</v-card-title>
                    <v-card-text>
                        <v-row>
                            <v-col cols="12" sm="6">
                                <v-select v-if="eventDays.length>1"
                                          v-model="filterData['days']"
                                          :items="eventDays"
                                          label="Days"
                                          outlined
                                          multiple
                                />
                            </v-col>

                            <template v-if="profileId > 0">
                                <v-col cols="12" sm="6" v-for="(field) in fields.form" :key="`field-${field.name}`">


                                    <v-select
                                        v-if="field.type==='select'"
                                        :items="field.options"
                                        item-title="value"
                                        item-value="key"
                                        v-model="filterData[field.name]"
                                        :label="field.label"
                                    ></v-select>

                                    <!--                            <v-select-->
                                    <!--                                v-else-if="field.type==='taxonomy'"-->
                                    <!--                                :items="taxonomieItems[field.name]"-->
                                    <!--                                item-title="title"-->
                                    <!--                                item-value="id"-->
                                    <!--                                v-model="formData[field.name]"-->
                                    <!--                                :label="field.label"-->
                                    <!--                                multiple-->
                                    <!--                                chips-->
                                    <!--                                @focus ="getTerms(field.name, field.options)"-->
                                    <!--                            ></v-select>-->
                                    <v-combobox
                                        v-else-if="field.type==='taxonomy'"
                                        v-model="filterData[field.name]"
                                        :items="taxonomieItems[field.name]"
                                        item-title="title"
                                        item-value="id"
                                        :label="field.label"
                                        chips
                                        clearable
                                        multiple
                                        @focus ="getTerms(field.name, field.options)"
                                    ></v-combobox>


                                    <v-textarea
                                        v-else-if="field.type==='textarea'"
                                        :label="name"
                                        :id="name"
                                        v-model="filterData[field.name]"
                                        :value="filterData[field.name]"
                                    ></v-textarea>

                                    <v-text-field
                                        v-else
                                        :label="field.label"
                                        v-model="filterData[field.name]"
                                        :value="filterData[field.name]"
                                    ></v-text-field>


                                </v-col>

                            </template>

<!--                            <v-col cols="12" sm="6">-->
<!--                                <v-combobox-->
<!--                                    v-model="filterData.games"-->
<!--                                    :items="taxonomieItems.games || []"-->
<!--                                    item-title="title"-->
<!--                                    item-value="id"-->
<!--                                    label="Filter by Games"-->
<!--                                    chips-->
<!--                                    clearable-->
<!--                                    multiple-->
<!--                                    @focus="getTerms('games', 'games')"-->
<!--                                ></v-combobox>-->
<!--                            </v-col>-->
<!--                            <v-col cols="12" sm="6">-->
<!--                                <v-combobox-->
<!--                                    v-model="filterData.breakfast"-->
<!--                                    :items="taxonomieItems.breakfast || []"-->
<!--                                    item-title="title"-->
<!--                                    item-value="id"-->
<!--                                    label="Filter by Breakfast"-->
<!--                                    chips-->
<!--                                    clearable-->
<!--                                    multiple-->
<!--                                    @focus="getTerms('breakfast', 'breakfast')"-->
<!--                                ></v-combobox>-->
<!--                            </v-col>-->
<!--                        </v-row>-->
<!--                        <v-row>-->
<!--                            <v-col cols="12" sm="6">-->
<!--                                <v-combobox-->
<!--                                    v-model="filterData.drinks"-->
<!--                                    :items="taxonomieItems.drinks || []"-->
<!--                                    item-title="title"-->
<!--                                    item-value="id"-->
<!--                                    label="Filter by Drinks"-->
<!--                                    chips-->
<!--                                    clearable-->
<!--                                    multiple-->
<!--                                    @focus="getTerms('drinks', 'drinks')"-->
<!--                                ></v-combobox>-->
<!--                            </v-col>-->
<!--                            <v-col cols="12" sm="6">-->
<!--                                <v-combobox-->
<!--                                    v-model="filterData.meals"-->
<!--                                    :items="taxonomieItems.meals || []"-->
<!--                                    item-title="title"-->
<!--                                    item-value="id"-->
<!--                                    label="Filter by Meals"-->
<!--                                    chips-->
<!--                                    clearable-->
<!--                                    multiple-->
<!--                                    @focus="getTerms('meals', 'meals')"-->
<!--                                ></v-combobox>-->
<!--                            </v-col>-->
<!--                        </v-row>-->
<!--                        <v-row>-->
<!--                            <v-col cols="12" sm="6">-->
<!--                                <v-combobox-->
<!--                                    v-model="filterData.allergies"-->
<!--                                    :items="taxonomieItems.allergies || []"-->
<!--                                    item-title="title"-->
<!--                                    item-value="id"-->
<!--                                    label="Filter by Allergies"-->
<!--                                    chips-->
<!--                                    clearable-->
<!--                                    multiple-->
<!--                                    @focus="getTerms('allergies', 'allergies')"-->
<!--                                ></v-combobox>-->
<!--                            </v-col>-->
                        </v-row>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="error" variant="text" @click="clearAllFilters">Clear All</v-btn>
                        <v-btn color="primary" @click="showFilterDialog = false">Apply Filters</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <!-- Columns Dialog -->
            <v-dialog v-model="showColumnsDialog" max-width="500">
                <v-card>
                    <v-card-title>Column Visibility</v-card-title>
                    <v-card-text>
                        <v-select
                            v-model="visibleColumns"
                            :items="allColumnOptions"
                            label="Select Columns"
                            multiple
                            chips
                            closable-chips
                            variant="outlined"
                        ></v-select>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="error" variant="text" @click="resetColumns">Reset</v-btn>
                        <v-btn color="primary" @click="showColumnsDialog = false">Apply</v-btn>
                    </v-card-actions>
                </v-card>
            </v-dialog>

            <v-data-table
                v-model:expanded="expanded"
                :items="filteredRecords"
                :headers="visibleHeaders"
                :search="search"
                expand-mode="single"
                show-expand
                item-value="id"
            >

                <!-- Custom column filters -->
                <template v-slot:top>
                    <v-row class="pa-4">
                        <!-- Your existing filters can go here -->
                    </v-row>
                </template>

                <template v-slot:item.user="{ value }">
                    <UserAvatar
                        :user="value[0]"/> {{value[0].username}}
                </template>
                <template v-slot:item.days="{ value }">
                    <v-chip v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail}}</v-chip>
                </template>
                <template v-slot:item.games="{ value }">
                    <v-chip v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.breakfast="{ value }">
                    <v-chip v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.drinks="{ value }">
                    <v-chip v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.allergies="{ value }">
                    <v-chip v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.meals="{ value }">
                    <v-chip v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.remarks="{ value }">
                    <template v-if="value && value.length > 0"><v-tooltip location="top"><template v-slot:activator="{ props }">
                        <v-icon v-bind="props">mdi-info</v-icon>
                    </template>
                        <span>Has remarks</span></v-tooltip></template>
                    <template v-else>-</template>
                </template>
                <template v-slot:expanded-row="{ columns, item }">
                    <tr>
                        <td :colspan="columns.length">
                            {{ item.remarks }}
                        </td>
                    </tr>
                </template>
            </v-data-table>


            <template v-slot:actions>
                <v-btn
                    class="ms-auto"
                    text="Close"
                    @click="internalModelValue = false"
                ></v-btn>
            </template>
        </v-card>
    </VDialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from "axios";
import UserAvatar from "@/components/common/UserAvatar.vue";

const search = ref('');
const expanded = ref([]);
const taxonomieItems = ref({});
const filterData = ref({
    games: [],
    breakfast: [],
    drinks: [],
    meals: [],
    allergies: []
});

// Dialog control
const showFilterDialog = ref(false);
const showColumnsDialog = ref(false);
const showColumnsBox = ref(false);
const fields = ref();
const profile = ref();

// Computed property to count active filters
const activeFiltersCount = computed(() => {
    return Object.values(filterData.value).reduce((count, filters) => {
        return count + filters.length;
    }, 0);
});

// Function to clear all filters
const clearAllFilters = () => {
    Object.keys(filterData.value).forEach(key => {
        filterData.value[key] = [];
    });
};

// Function to reset columns to default
const resetColumns = () => {
    visibleColumns.value = [...allColumns.value];
};
const openFilterDialog = () => {
    // profileForm()
    // if (profileId.value > 0) {
    //     profileForm()
    // }
    showFilterDialog.value = true
};

// Define the modelValue prop
const props = defineProps({
    modelValue: Boolean,
    eventGuests: Object,
    event: Object
});

const localGuests = ref(props.eventGuests);
const localEvent = ref(props.event);

const emit = defineEmits(['update:modelValue']);
const textField = ref('');

// Create an internal computed property for modelValue
const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// Column visibility management
const allColumns = computed(() => {
    if (!localGuests.value || localGuests.value.length === 0) return [];
    return [...new Set(localGuests.value.flatMap(obj => Object.keys(obj)))]
        .filter(key => key !== 'id');
});

const allColumnOptions = computed(() => {
    return allColumns.value.map(column => ({
        title: column.charAt(0).toUpperCase() + column.slice(1),
        value: column
    }));
});

// Default visible columns (you can customize this list)
const visibleColumns = ref([]);


// Initialize visible columns when data is loaded
watch(allColumns, (newColumns) => {
    if (newColumns.length > 0 && visibleColumns.value.length === 0) {
        visibleColumns.value = [...newColumns];
    }
}, { immediate: true });

const visibleHeaders = computed(() => {
    const headers = visibleColumns.value.map(column => ({
        title: column.charAt(0).toUpperCase() + column.slice(1),
        key: column
    }));

    // Always add the expand column
    headers.push({ title: '', key: 'data-table-expand' });
    return headers;
});

const filteredRecords = computed(() => {
    if (!localGuests.value) return [];

    // Start with removing the 'type' property from each record
    let results = localGuests.value.map(({ id, ...rest }) => rest);

    // Apply filters for each taxonomy
    Object.keys(filterData.value).forEach(key => {
        const selectedIds = filterData.value[key].map(item =>
            typeof item === 'object' ? item.id : item
        );

        if (selectedIds.length > 0) {
            results = results.filter(record => {
                // Handle case where the field might not exist on some records
                if (!record[key]) return false;

                // Handle array of objects with title property (like games, breakfast, etc.)
                if (Array.isArray(record[key]) && record[key].length > 0 && record[key][0].hasOwnProperty('id')) {
                    return record[key].some(item => selectedIds.includes(item.id));
                }

                // Handle array of primitive values (like days)
                if (Array.isArray(record[key])) {
                    return record[key].some(item => selectedIds.includes(item));
                }

                // Handle simple value
                return selectedIds.includes(record[key]);
            });
        }
    });

    // Apply text search if present
    if (search.value) {
        const searchLower = search.value.toLowerCase();
        results = results.filter(record => {
            return Object.entries(record).some(([key, value]) => {
                // Skip searching through complex objects/arrays
                if (typeof value === 'object' && value !== null) {
                    return false;
                }

                // Convert value to string and search
                return String(value).toLowerCase().includes(searchLower);
            });
        });
    }

    return results;
});

const getTerms = async (name, taxonomy) => {
    // Only fetch if we don't already have the data
    if (!taxonomieItems.value[name] || taxonomieItems.value[name].length === 0) {
        try {
            const response = await axios.get(`/api/tag/terms/${taxonomy}`);
            taxonomieItems.value[name] = response.data.terms;
            console.log(`Loaded ${name} taxonomy:`, taxonomieItems.value[name]);
        } catch (error) {
            console.error(`Error fetching ${name} taxonomy:`, error);
        }
    }
};

// Function to load all taxonomies at once
const loadAllTaxonomies = async () => {
    const taxonomies = ['games', 'breakfast', 'drinks', 'meals', 'allergies'];

    for (const taxonomy of taxonomies) {
        await getTerms(taxonomy, taxonomy);
    }
};

//duplicate code...same in profileDialog
const profileId = computed(() => {

    if (!localEvent.value.extendedProps) return null;

    return localEvent.value.extendedProps.event_profile_id;
});

const profileForm = async () => {

    await axios.get(`/api/datatable/event-profiles/${profileId.value}`).then((response) => {

        profile.value = response.data

        // fields.value = JSON.parse(profile.value.options)
        fields.value = profile.value.options

    }).catch((error) => {
        console.log(error)
        if (error.response.status === 422) {
            // this.creating.errors = error.response.data
            this.editing.errors = error.response.data
        }
    })
}

watch(
    () => props.eventGuests,
    (newGuests) => {
        localGuests.value = newGuests ? JSON.parse(JSON.stringify(newGuests)) : null;
        // Load all taxonomies when guests data is loaded
        if (newGuests) {
            loadAllTaxonomies();
        }
    },
    {immediate: true}
);

watch(
    () => props.event,
    (newEvent) => {
        // console.log('profilewatch', profileId)
        localEvent.value = newEvent ? JSON.parse(JSON.stringify(newEvent)) : null;
        if (profileId.value > 0) {
            profileForm()
        }
    },
    {immediate: true} // Trigger immediately to initialize localEvent
);


const eventDays = computed(() => {
    // console.log('profile',localEvent, props.event)
    if (!localEvent.value.start || !localEvent.value.end) return [];

    const start = new Date(localEvent.value.start);
    const end = new Date(localEvent.value.end);
    const days = [];

    const formatter = new Intl.DateTimeFormat("en-US", {weekday: "long"});

    while (start <= end) {
        days.push(formatter.format(new Date(start)));
        start.setDate(start.getDate() + 1);
    }

    return days;
});


// Watch for changes in filter data to trigger reactivity
watch(filterData, () => {
    console.log('Filter data changed:', filterData.value);
}, {deep: true});
</script>
