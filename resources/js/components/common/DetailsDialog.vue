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

                        <v-btn
                            icon
                            variant="text"
                            color="primary"
                            class="mr-2"
                            @click="exportToCsv"
                            title="Export to CSV"
                        >
                            <v-icon>mdi-file-export</v-icon>
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
                                <div>
                                    <div class="text-subtitle-1 mb-2">Attendance Status</div>
                                    <v-checkbox
                                        v-for="type in availableTypes"
                                        :key="type.value"
                                        v-model="typeFilter"
                                        :label="type.text"
                                        :value="type.value"
                                        density="compact"
                                        hide-details
                                        class="mb-1"
                                    ></v-checkbox>
                                </div>
                            </v-col>

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
                                        :label="field.label"
                                        :id="field.name"
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
                :expanded="expanded"
                :items="filteredRecords"
                :headers="visibleHeaders"
                :search="search"
                show-expand
                item-value="id"
                item-key="id"
                @click:expand="handleExpandClick"
            >
                <template v-for="header in slotHeaders" :key="header.key" v-slot:[`item.${header.key}`]="{ value }">
                    <!-- Skip the user column as it has its own template -->
                    <template v-if="header.key === 'user'">
                        <UserAvatar
                            :user="value[0]"/> {{value[0].username}}
                    </template>
                    <template v-if="header.key !== 'user'">
                        <!-- Arrays with objects (games, breakfast, etc.) -->
                        <template v-if="value && Array.isArray(value) && value.length > 0 && typeof value[0] === 'object' && 'title' in value[0]">
                            <v-chip v-for="itemDetail in value" :key="itemDetail.id">
                                {{ itemDetail.title }}
                            </v-chip>
                        </template>
                        <!-- Array of primitives (days) -->
                        <template v-else-if="value && Array.isArray(value)">
                            <v-chip v-for="(itemDetail, index) in value" :key="index">
                                {{ itemDetail }}
                            </v-chip>
                        </template>
                        <!-- Special case for remarks -->
                        <template v-else-if="header.key === 'other'">
                            <v-tooltip location="top" v-if="value && value.length > 0">
                                <template v-slot:activator="{ props }">
                                    <v-icon v-bind="props">mdi-information-outline</v-icon>
                                </template>
                                <span>{{ value }}</span>
                            </v-tooltip>
                            <template v-else>
                                <v-icon>mdi-information-off-outline</v-icon>
                            </template>
                        </template>

                        <!-- Default case for non-array values -->
                        <template v-else>
                            {{ value || '-' }}
                        </template>
                    </template>
                </template>

                <template v-slot:expanded-row="{ columns, item }">
                    <tr>
                        <td :colspan="columns.length">
                            {{ item.other }}
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
const filterData = ref({});

// Type filter - will be populated dynamically
const typeFilter = ref([]); // Default to showing 'going' attendees
const availableTypes = ref([]);

// Dialog control
const showFilterDialog = ref(false);
const showColumnsDialog = ref(false);
const showColumnsBox = ref(false);
const fields = ref({form: []});
const profile = ref();

// Computed property to count active filters
const activeFiltersCount = computed(() => {
    let count = 0;
    Object.values(filterData.value).forEach(filters => {
        if (Array.isArray(filters)) {
            count += filters.length;
        } else if (filters) {
            count += 1;
        }
    });
    return count;
});

// Function to clear all filters
const clearAllFilters = () => {
    Object.keys(filterData.value).forEach(key => {
        if (Array.isArray(filterData.value[key])) {
            filterData.value[key] = [];
        } else {
            filterData.value[key] = '';
        }
    });
    typeFilter.value = availableTypes.value.map(type => type.value);
};

// Function to reset columns to default
const resetColumns = () => {
    visibleColumns.value = [...allColumns.value];
};

const openFilterDialog = () => {
    showFilterDialog.value = true;
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

const slotHeaders = computed(() => {
    return visibleColumns.value.map(column => ({
        title: column.charAt(0).toUpperCase() + column.slice(1),
        key: column
    }));
});

// Get unique types from guests and populate availableTypes
const updateAvailableTypes = () => {
    if (!localGuests.value) return;

    const types = new Set();
    localGuests.value.forEach(guest => {
        if (guest.type) types.add(guest.type);
    });

    availableTypes.value = Array.from(types).map(type => ({
        value: type,
        text: type.charAt(0).toUpperCase() + type.slice(1)
    }));

    // If typeFilter is empty, set it to include all types
    if (typeFilter.value.length === 0) {
        typeFilter.value = availableTypes.value.map(type => type.value);
    }
};

const filteredRecords = computed(() => {
    if (!localGuests.value) return [];

    // Filter by selected types first
    let results = localGuests.value.filter(guest => {
        return typeFilter.value.includes(guest.type);
    });

    // Apply filters for each taxonomy
    Object.keys(filterData.value).forEach(key => {
        if(typeof filterData.value[key] === "string") {
            if (filterData.value[key]) {  // Only filter if there's a value
                results = results.filter(record => {
                    return record[key] === filterData.value[key];
                });
            }
        } else {
            const selectedIds = filterData.value[key]?.map(item =>
                typeof item === 'object' ? item.id : item
            ) || [];

            if (selectedIds.length > 0) {
                results = results.filter(record => {
                    // Handle case where the field might not exist on some records
                    if (!record[key]) return false;

                    // Handle array of objects with title property (like games, breakfast, etc.)
                    if (Array.isArray(record[key]) && record[key].length > 0 && record[key][0]?.hasOwnProperty('id')) {
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
    if (!localEvent.value?.extendedProps) return 0;
    return localEvent.value.extendedProps.event_profile_id || 0;
});

const profileForm = async () => {
    try {
        const response = await axios.get(`/api/datatable/event-profiles/${profileId.value}`);
        profile.value = response.data;
        fields.value = profile.value.options;
    } catch (error) {
        console.error('Error fetching profile:', error);
    }
};

watch(
    () => props.eventGuests,
    (newGuests) => {
        localGuests.value = newGuests ? JSON.parse(JSON.stringify(newGuests)) : null;
        // Load all taxonomies when guests data is loaded
        if (newGuests) {
            loadAllTaxonomies();
            updateAvailableTypes();
        }
    },
    {immediate: true}
);

watch(
    () => props.event,
    (newEvent) => {
        localEvent.value = newEvent ? JSON.parse(JSON.stringify(newEvent)) : null;
        if (profileId.value > 0) {
            profileForm();
        }
    },
    {immediate: true}
);

// This is the main function that fixes the expand issue
const handleExpandClick = (event, { item }) => {
    if (!item?.id) return;

    // If the item is already in the expanded array, remove it (collapse)
    if (expanded.value.includes(item.id)) {
        expanded.value = [];
    } else {
        // Otherwise, replace the entire expanded array with just this item's ID
        expanded.value = [item.id];
    }

    console.log('Expanded state:', expanded.value);
};

const eventDays = computed(() => {
    if (!localEvent.value?.start || !localEvent.value?.end) return [];

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

// Function to format a value for CSV export
const formatValueForCsv = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    // Handle arrays of objects (like games, breakfast, etc.)
    if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'object' && value[0] !== null) {
        return value.map(item => item.title || item.name || JSON.stringify(item)).join(', ');
    }

    // Handle arrays of primitive values (like days)
    if (Array.isArray(value)) {
        return value.join(', ');
    }

    // Handle user objects
    if (typeof value === 'object' && value !== null && Array.isArray(value) && value.length > 0 && value[0]?.username) {
        return value[0].username;
    }

    // Handle other objects
    if (typeof value === 'object' && value !== null) {
        return JSON.stringify(value);
    }

    // Handle string values that might contain commas or quotes
    if (typeof value === 'string') {
        // Escape quotes by doubling them and wrap in quotes if contains comma, quote or newline
        const needsQuotes = value.includes(',') || value.includes('"') || value.includes('\n') || value.includes('\r');
        const escapedValue = value.replace(/"/g, '""');
        return needsQuotes ? `"${escapedValue}"` : escapedValue;
    }

    return String(value);
};

// Function to export data to CSV
const exportToCsv = () => {
    if (!localGuests.value || localGuests.value.length === 0) {
        console.error('No data to export');
        return;
    }

    // Filter guests by the current type filter
    const guestsToExport = localGuests.value.filter(guest => {
        return typeFilter.value.includes(guest.type);
    });

    if (guestsToExport.length === 0) {
        console.error('No data to export after filtering');
        return;
    }

    // Get visible columns (headers) and add the 'Type' column
    const headers = ['Type', ...visibleHeaders.value
        .filter(header => header.key !== 'data-table-expand')
        .map(header => header.title)];

    // Create CSV header row
    let csvContent = headers.join(',') + '\n';

    // Add data rows
    guestsToExport.forEach(guest => {
        // Start with the type column
        const row = [formatValueForCsv(guest.type)];

        // Add the rest of the columns
        visibleHeaders.value
            .filter(header => header.key !== 'data-table-expand')
            .forEach(header => {
                const value = guest[header.key];
                row.push(formatValueForCsv(value));
            });

        csvContent += row.join(',') + '\n';
    });

    // Create a Blob with the CSV data
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });

    // Create a download link
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    // Set link properties
    link.setAttribute('href', url);
    link.setAttribute('download', `event_guests_${new Date().toISOString().slice(0, 10)}.csv`);
    link.style.visibility = 'hidden';

    // Add link to document, trigger click, and remove
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>
