<template>
    <v-container fluid>
        <v-row>
            <!-- Left Panel - Map Display -->
            <v-col cols="12" :md="activeEditForm ? 8 : 12">
                <v-card>
                    <v-card-title>
                        <template v-if="activeBuildingId">
                            {{ getActiveBuildingName() }}
                            <v-chip class="ml-2" color="primary" size="small">
                                <v-icon start size="small">mdi-floor-plan</v-icon>
                                Floor Plan
                            </v-chip>
                            <v-spacer></v-spacer>
                            <v-btn icon="mdi-exit-to-app" variant="text" @click="exitBuilding" title="Exit Building"></v-btn>
                        </template>
                        <template v-else>
                            Preview Map
                        </template>
                        <v-spacer v-if="!activeBuildingId"></v-spacer>
                        <v-btn-toggle v-model="mapMode" mandatory>
                            <v-btn value="view">
                                <v-icon>mdi-eye</v-icon>
                                View
                            </v-btn>
                            <v-btn value="edit">
                                <v-icon>mdi-pencil</v-icon>
                                Edit
                            </v-btn>
                        </v-btn-toggle>
                    </v-card-title>

                    <!-- Floor selector when in a building -->
                    <v-toolbar v-if="activeBuildingId" density="compact" color="surface-variant">
                        <template v-slot:prepend>
                            <v-btn-toggle v-model="activeFloorId" mandatory>
                                <v-btn
                                    v-for="floor in getBuildingFloors()"
                                    :key="floor.id"
                                    :value="floor.id"
                                    :prepend-icon="floor.level === 0 ? 'mdi-floor-plan' : (floor.level > 0 ? 'mdi-floor-up' : 'mdi-floor-down')"
                                    :title="`Level ${floor.level}: ${floor.name}`"
                                    density="compact"
                                >
                                    {{ floor.name }}
                                </v-btn>
                            </v-btn-toggle>
                        </template>

                        <template v-slot:append>
                            <v-menu v-if="mapMode === 'edit'">
                                <template v-slot:activator="{ props }">
                                    <v-btn
                                        color="primary"
                                        v-bind="props"
                                        prepend-icon="mdi-plus"
                                        variant="tonal"
                                        size="small"
                                    >
                                        Add Elements
                                    </v-btn>
                                </template>
                                <v-list>
                                    <v-list-item @click="startFloorTool('room')" prepend-icon="mdi-select">
                                        <v-list-item-title>Add Room</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="startFloorTool('wall')" prepend-icon="mdi-wall">
                                        <v-list-item-title>Draw Wall</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="startFloorTool('door')" prepend-icon="mdi-door">
                                        <v-list-item-title>Add Door</v-list-item-title>
                                    </v-list-item>
                                    <v-list-item @click="startFloorTool('stairs')" prepend-icon="mdi-stairs">
                                        <v-list-item-title>Add Stairs</v-list-item-title>
                                    </v-list-item>
                                </v-list>
                            </v-menu>

                            <v-btn
                                v-if="mapMode === 'edit'"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-floor-plan"
                                @click="showFloorSettingsDialog = true"
                                size="small"
                            >
                                Floor Settings
                            </v-btn>

                            <v-btn
                                v-if="mapMode === 'edit'"
                                variant="text"
                                color="primary"
                                prepend-icon="mdi-plus"
                                @click="addNewFloor"
                                size="small"
                            >
                                Add Floor
                            </v-btn>
                        </template>
                    </v-toolbar>

                    <v-card-text>
                        <div class="map-container" id="admin-map"></div>
                        <div class="mt-2">
                            <v-alert v-if="mapMode === 'edit' && !activeBuildingId" type="info" variant="tonal" density="compact">
                                <span v-if="!activeTool">Select a tool from the editor panel to start editing the map.</span>
                                <span v-else-if="activeTool === 'location'">Click on the map to add a new location.</span>
                                <span v-else-if="activeTool === 'road'">Click to add points to your road. Double-click to finish.</span>
                                <span v-else-if="activeTool === 'region'">Click to add points to your region. Double-click to finish.</span>
                                <span v-else-if="activeTool === 'decoration'">Click on the map to add the selected decoration.</span>
                                <span v-else-if="activeTool === 'building'">Click on the map to add a new building.</span>
                            </v-alert>

                            <v-alert v-if="mapMode === 'edit' && activeBuildingId" type="info" variant="tonal" density="compact">
                                <span v-if="!floorToolActive">Use the Add Elements menu to start editing the floor plan.</span>
                                <span v-else-if="floorToolActive === 'room'">Click to add points to define a room area. Double-click to finish.</span>
                                <span v-else-if="floorToolActive === 'wall'">Click to add points for a wall. Double-click to finish.</span>
                                <span v-else-if="floorToolActive === 'door'">Click to place a door on the floor plan.</span>
                                <span v-else-if="floorToolActive === 'stairs'">Click to place a staircase on the floor plan.</span>
                            </v-alert>
                        </div>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Right Panel - Either Editor Controls or Active Edit Form -->
            <v-col cols="12" md="4" v-if="!activeEditForm">
                <v-card class="preview-card">
                    <v-card-title>Map Editor</v-card-title>
                    <v-card-text class="preview-content">
                        <v-expansion-panels v-model="activePanel">
                            <!-- Map Settings Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Map Settings</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-form>
                                        <v-text-field
                                            v-model="mapSettings.title"
                                            label="Map Title"
                                            variant="outlined"
                                            density="compact"
                                        ></v-text-field>
                                        <v-row>
                                            <v-col cols="6">
                                                <v-text-field
                                                    v-model="mapSettings.centerLat"
                                                    label="Center Latitude"
                                                    type="number"
                                                    step="0.01"
                                                    variant="outlined"
                                                    density="compact"
                                                ></v-text-field>
                                            </v-col>
                                            <v-col cols="6">
                                                <v-text-field
                                                    v-model="mapSettings.centerLng"
                                                    label="Center Longitude"
                                                    type="number"
                                                    step="0.01"
                                                    variant="outlined"
                                                    density="compact"
                                                ></v-text-field>
                                            </v-col>
                                        </v-row>
                                        <v-slider
                                            v-model="mapSettings.zoom"
                                            label="Default Zoom"
                                            min="1"
                                            max="18"
                                            step="1"
                                            thumb-label
                                        ></v-slider>
                                        <v-slider
                                            v-model="mapSettings.sepia"
                                            label="Sepia"
                                            min="0"
                                            max="100"
                                            step="5"
                                            thumb-label
                                            suffix="%"
                                        ></v-slider>
                                        <v-slider
                                            v-model="mapSettings.saturation"
                                            label="Saturation"
                                            min="100"
                                            max="150"
                                            step="5"
                                            thumb-label
                                            suffix="%"
                                        ></v-slider>
                                        <v-slider
                                            v-model="mapSettings.hue"
                                            label="Hue Rotation"
                                            min="0"
                                            max="360"
                                            step="5"
                                            thumb-label
                                            suffix="deg"
                                        ></v-slider>
                                        <v-btn color="primary" block @click="applyMapSettings">
                                            Apply Settings
                                        </v-btn>
                                    </v-form>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <!-- Locations Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Locations</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-btn color="primary" block class="mb-3" @click="startTool('location')">
                                        <v-icon>mdi-map-marker-plus</v-icon>
                                        Add New Location
                                    </v-btn>
                                    <v-list lines="two">
                                        <v-list-item
                                            v-for="(loc, id) in locations"
                                            :key="id"
                                            :value="id"
                                            :title="loc.name"
                                            :subtitle="`(${loc.coords[0].toFixed(2)}, ${loc.coords[1].toFixed(2)})`"
                                        >
                                            <template v-slot:prepend>
                                                <v-icon color="primary">mdi-map-marker</v-icon>
                                            </template>
                                            <template v-slot:append>
                                                <v-btn icon="mdi-pencil" density="compact" variant="text" @click="editLocation(id)"></v-btn>
                                                <v-btn icon="mdi-delete" density="compact" variant="text" @click="deleteLocation(id)"></v-btn>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <!-- Roads Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Roads</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-btn color="primary" block class="mb-3" @click="startTool('road')">
                                        <v-icon>mdi-road-variant</v-icon>
                                        Draw New Road
                                    </v-btn>
                                    <v-list lines="two">
                                        <v-list-item
                                            v-for="(road, index) in roads"
                                            :key="index"
                                            :value="index"
                                            :title="road.name"
                                            :subtitle="'Type: ' + road.type"
                                        >
                                            <template v-slot:prepend>
                                                <div class="color-box" :style="{ backgroundColor: road.style.color }"></div>
                                            </template>
                                            <template v-slot:append>
                                                <v-btn icon="mdi-pencil" density="compact" variant="text" @click="editRoad(index)"></v-btn>
                                                <v-btn icon="mdi-delete" density="compact" variant="text" @click="deleteRoad(index)"></v-btn>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <!-- Regions Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Regions</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-btn color="primary" block class="mb-3" @click="startTool('region')">
                                        <v-icon>mdi-shape-polygon-plus</v-icon>
                                        Draw New Region
                                    </v-btn>
                                    <v-list lines="two">
                                        <v-list-item
                                            v-for="(region, index) in regions"
                                            :key="index"
                                            :value="index"
                                            :title="region.name"
                                        >
                                            <template v-slot:prepend>
                                                <div class="color-box" :style="{ backgroundColor: region.style.color }"></div>
                                            </template>
                                            <template v-slot:append>
                                                <v-btn icon="mdi-pencil" density="compact" variant="text" @click="editRegion(index)"></v-btn>
                                                <v-btn icon="mdi-delete" density="compact" variant="text" @click="deleteRegion(index)"></v-btn>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <!-- Decorations Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Decorations</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-chip-group v-model="selectedDecoration" column>
                                        <v-chip filter variant="outlined" label value="seaMonster" @click="startTool('decoration', 'seaMonster')">
                                            <v-icon start>mdi-waves</v-icon>
                                            Sea Monster
                                        </v-chip>
                                        <v-chip filter variant="outlined" label value="compass" @click="startTool('decoration', 'compass')">
                                            <v-icon start>mdi-compass</v-icon>
                                            Compass
                                        </v-chip>
                                        <v-chip filter variant="outlined" label value="treasure" @click="startTool('decoration', 'treasure')">
                                            <v-icon start>mdi-treasure-chest</v-icon>
                                            Treasure
                                        </v-chip>
                                        <v-chip filter variant="outlined" label value="mountain" @click="startTool('decoration', 'mountain')">
                                            <v-icon start>mdi-mountain</v-icon>
                                            Mountain
                                        </v-chip>
                                        <v-chip filter variant="outlined" label value="forest" @click="startTool('decoration', 'forest')">
                                            <v-icon start>mdi-pine-tree</v-icon>
                                            Forest
                                        </v-chip>
                                        <v-chip filter variant="outlined" label value="building" @click="startTool('decoration', 'building')">
                                            <v-icon start>mdi-home</v-icon>
                                            Building
                                        </v-chip>
                                    </v-chip-group>
                                    <v-list class="mt-3">
                                        <v-list-subheader>Placed Decorations</v-list-subheader>
                                        <v-list-item
                                            v-for="(deco, index) in decorations"
                                            :key="index"
                                            :value="index"
                                            :title="deco.type"
                                            :subtitle="`(${deco.coords[0].toFixed(2)}, ${deco.coords[1].toFixed(2)})`"
                                        >
                                            <template v-slot:prepend>
                                                <v-icon>{{ decorationIcons[deco.type] || 'mdi-star' }}</v-icon>
                                            </template>
                                            <template v-slot:append>
                                                <v-btn icon="mdi-delete" density="compact" variant="text" @click="deleteDecoration(index)"></v-btn>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <!-- Buildings Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Buildings</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-btn color="primary" block class="mb-3" @click="startTool('building')">
                                        <v-icon>mdi-office-building-plus</v-icon>
                                        Add New Building
                                    </v-btn>

                                    <v-list lines="two">
                                        <v-list-item
                                            v-for="(building, index) in buildings"
                                            :key="building.id"
                                            :value="building.id"
                                            :title="building.name"
                                            :subtitle="`(${building.coords[0].toFixed(2)}, ${building.coords[1].toFixed(2)})`"
                                        >
                                            <template v-slot:prepend>
                                                <v-icon color="primary">{{ building.icon || 'mdi-home' }}</v-icon>
                                            </template>
                                            <template v-slot:append>
                                                <v-btn icon="mdi-door-open" density="compact" variant="text" @click="enterBuilding(building.id)" title="Enter Building"></v-btn>
                                                <v-btn icon="mdi-pencil" density="compact" variant="text" @click="editBuilding(index)" title="Edit Building"></v-btn>
                                                <v-btn icon="mdi-delete" density="compact" variant="text" @click="deleteBuilding(index)" title="Delete Building"></v-btn>
                                            </template>
                                        </v-list-item>
                                    </v-list>
                                </v-expansion-panel-text>
                            </v-expansion-panel>

                            <!-- Import/Export Panel -->
                            <v-expansion-panel>
                                <v-expansion-panel-title>Import/Export</v-expansion-panel-title>
                                <v-expansion-panel-text>
                                    <v-row>
                                        <v-col cols="6">
                                            <v-btn color="primary" block @click="exportMapData">
                                                <v-icon start>mdi-export</v-icon>
                                                Export Map
                                            </v-btn>
                                        </v-col>
                                        <v-col cols="6">
                                            <v-btn color="secondary" block @click="importDialog = true">
                                                <v-icon start>mdi-import</v-icon>
                                                Import Map
                                            </v-btn>
                                        </v-col>
                                    </v-row>
                                </v-expansion-panel-text>
                            </v-expansion-panel>
                        </v-expansion-panels>
                    </v-card-text>
                </v-card>
            </v-col>

            <!-- Active Edit Form Panel (replaces dialog approach) -->
            <v-col cols="12" md="4" v-if="activeEditForm">
                <v-card class="edit-form-card">
                    <v-card-title class="d-flex align-center">
                        <span>{{ getEditFormTitle() }}</span>
                        <v-spacer></v-spacer>
                        <v-btn icon="mdi-close" variant="text" @click="closeEditForm"></v-btn>
                    </v-card-title>
                    <v-divider></v-divider>

                    <!-- Location Edit Form -->
                    <v-card-text v-if="activeEditForm === 'location'">
                        <v-form ref="locationForm">
                            <v-text-field
                                v-model="locationData.id"
                                label="ID (no spaces)"
                                variant="outlined"
                                density="compact"
                                :rules="[v => !!v || 'ID is required', v => /^[a-zA-Z0-9_-]+$/.test(v) || 'ID must only contain letters, numbers, underscores, and hyphens']"
                            ></v-text-field>
                            <v-text-field
                                v-model="locationData.name"
                                label="Name"
                                variant="outlined"
                                density="compact"
                                :rules="[v => !!v || 'Name is required']"
                            ></v-text-field>
                            <v-textarea
                                v-model="locationData.description"
                                label="Description"
                                variant="outlined"
                                auto-grow
                                density="compact"
                            ></v-textarea>
                            <v-row>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="locationData.lat"
                                        label="Latitude"
                                        type="number"
                                        step="0.01"
                                        variant="outlined"
                                        density="compact"
                                        :rules="[v => !!v || 'Latitude is required']"
                                    ></v-text-field>
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field
                                        v-model="locationData.lng"
                                        label="Longitude"
                                        type="number"
                                        step="0.01"
                                        variant="outlined"
                                        density="compact"
                                        :rules="[v => !!v || 'Longitude is required']"
                                    ></v-text-field>
                                </v-col>
                            </v-row>
                        </v-form>
                    </v-card-text>

                    <!-- Road Edit Form -->
                    <v-card-text v-else-if="activeEditForm === 'road'">
                        <v-form ref="roadFormRef">
                            <v-text-field
                                v-model="roadForm.name"
                                label="Name"
                                variant="outlined"
                                density="compact"
                                :rules="[v => !!v || 'Name is required']"
                            ></v-text-field>
                            <v-select
                                v-model="roadForm.type"
                                label="Road Type"
                                :items="['main', 'secondary', 'minor']"
                                variant="outlined"
                                density="compact"
                            ></v-select>

<!--                            <v-color-picker-->
<!--                                v-model="roadForm.color"-->
<!--                                :swatches="roadColorSwatches"-->
<!--                                show-swatches-->
<!--                                mode="hex"-->
<!--                            ></v-color-picker>-->

                            <v-slider
                                v-model="roadForm.weight"
                                label="Width"
                                min="1"
                                max="10"
                                step="0.5"
                                thumb-label
                            ></v-slider>

                            <v-slider
                                v-model="roadForm.opacity"
                                label="Opacity"
                                min="0.1"
                                max="1"
                                step="0.1"
                                thumb-label
                            ></v-slider>

                            <v-checkbox v-model="roadForm.isDashed" label="Dashed line"></v-checkbox>

                            <v-text-field
                                v-if="roadForm.isDashed"
                                v-model="roadForm.dashArray"
                                label="Dash Pattern (e.g. '5, 5')"
                                variant="outlined"
                                density="compact"
                            ></v-text-field>

                            <div class="my-4">
                                <p class="text-body-2 mb-2">Points: {{ tempDrawingPoints.length }}</p>
                                <v-btn
                                    color="warning"
                                    variant="outlined"
                                    size="small"
                                    class="mr-2"
                                    :disabled="tempDrawingPoints.length === 0"
                                    @click="removeLastPoint"
                                >
                                    <v-icon>mdi-undo</v-icon>
                                    Remove Last Point
                                </v-btn>
                                <v-btn
                                    color="error"
                                    variant="outlined"
                                    size="small"
                                    :disabled="tempDrawingPoints.length === 0"
                                    @click="resetDrawing"
                                >
                                    <v-icon>mdi-delete</v-icon>
                                    Clear All Points
                                </v-btn>
                            </div>
                        </v-form>
                    </v-card-text>

                    <!-- Region Edit Form -->
                    <v-card-text v-else-if="activeEditForm === 'region'">
                        <v-form ref="regionForm">
                            <v-text-field
                                v-model="regionForm.name"
                                label="Name"
                                variant="outlined"
                                density="compact"
                                :rules="[v => !!v || 'Name is required']"
                            ></v-text-field>

<!--                            <v-color-picker-->
<!--                                v-model="regionForm.color"-->
<!--                                :swatches="regionColorSwatches"-->
<!--                                show-swatches-->
<!--                                mode="hex"-->
<!--                            ></v-color-picker>-->

                            <v-slider
                                v-model="regionForm.fillOpacity"
                                label="Fill Opacity"
                                min="0.1"
                                max="0.5"
                                step="0.05"
                                thumb-label
                            ></v-slider>

                            <div class="my-4">
                                <p class="text-body-2 mb-2">Points: {{ tempDrawingPoints.length }}</p>
                                <v-btn
                                    color="warning"
                                    variant="outlined"
                                    size="small"
                                    class="mr-2"
                                    :disabled="tempDrawingPoints.length === 0"
                                    @click="removeLastPoint"
                                >
                                    <v-icon>mdi-undo</v-icon>
                                    Remove Last Point
                                </v-btn>
                                <v-btn
                                    color="error"
                                    variant="outlined"
                                    size="small"
                                    :disabled="tempDrawingPoints.length === 0"
                                    @click="resetDrawing"
                                >
                                    <v-icon>mdi-delete</v-icon>
                                    Clear All Points
                                </v-btn>
                            </div>
                        </v-form>
                    </v-card-text>

                    <v-divider></v-divider>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <v-btn color="error" variant="text" @click="closeEditForm">Cancel</v-btn>
                        <v-btn
                            v-if="(activeEditForm === 'road' && tempDrawingPoints.length >= 2) ||
                   (activeEditForm === 'region' && tempDrawingPoints.length >= 3)"
                            color="success"
                            @click="saveCurrentForm"
                        >
                            <v-icon start>mdi-check</v-icon>
                            Finish Drawing
                        </v-btn>
                        <v-btn
                            v-else
                            color="primary"
                            @click="saveCurrentForm"
                        >
                            Save
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-col>
        </v-row>

        <!-- Import Dialog -->
        <v-dialog v-model="importDialog" max-width="500px">
            <v-card>
                <v-card-title>Import Map Data</v-card-title>
                <v-card-text>
                    <v-textarea
                        v-model="importData"
                        label="Paste JSON data here"
                        variant="outlined"
                        auto-grow
                        rows="10"
                    ></v-textarea>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="importDialog = false" text>Cancel</v-btn>
                    <v-btn @click="importMapData" color="primary">Import</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Export Dialog -->
        <v-dialog v-model="exportDialog" max-width="500px">
            <v-card>
                <v-card-title>Export Map Data</v-card-title>
                <v-card-text>
                    <v-textarea
                        v-model="exportData"
                        label="Copy this JSON data"
                        variant="outlined"
                        auto-grow
                        rows="10"
                        readonly
                    ></v-textarea>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="copyExportData" color="primary">Copy to Clipboard</v-btn>
                    <v-btn @click="exportDialog = false" text>Close</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Building Form Dialog -->
        <v-dialog v-model="buildingDialog" max-width="500px">
            <v-card>
                <v-card-title>
                    {{ editingIndex === null ? 'Add New Building' : 'Edit Building' }}
                </v-card-title>
                <v-card-text>
                    <v-form ref="buildingFormRef">
                        <v-text-field
                            v-model="buildingForm.id"
                            label="ID (no spaces)"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'ID is required', v => /^[a-zA-Z0-9_-]+$/.test(v) || 'ID must only contain letters, numbers, underscores, and hyphens']"
                        ></v-text-field>
                        <v-text-field
                            v-model="buildingForm.name"
                            label="Name"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'Name is required']"
                        ></v-text-field>
                        <v-textarea
                            v-model="buildingForm.description"
                            label="Description"
                            variant="outlined"
                            auto-grow
                            density="compact"
                        ></v-textarea>
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="buildingForm.coords[0]"
                                    label="Latitude"
                                    type="number"
                                    step="0.01"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[v => !!v || 'Latitude is required']"
                                ></v-text-field>
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="buildingForm.coords[1]"
                                    label="Longitude"
                                    type="number"
                                    step="0.01"
                                    variant="outlined"
                                    density="compact"
                                    :rules="[v => !!v || 'Longitude is required']"
                                ></v-text-field>
                            </v-col>
                        </v-row>
                        <v-select
                            v-model="buildingForm.icon"
                            label="Icon"
                            :items="[
                { value: 'mdi-home', title: 'House' },
                { value: 'mdi-office-building', title: 'Office Building' },
                { value: 'mdi-castle', title: 'Castle' },
                { value: 'mdi-warehouse', title: 'Warehouse' },
                { value: 'mdi-store', title: 'Shop' },
                { value: 'mdi-church', title: 'Temple/Church' },
                { value: 'mdi-tower-fire', title: 'Tower' },
                { value: 'mdi-glass-mug-variant', title: 'Tavern/Inn' },
              ]"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="compact"
                        >
                            <template v-slot:selection="{ item }">
                                <v-icon :icon="item.value" class="mr-2"></v-icon>
                                {{ item.title }}
                            </template>
                            <template v-slot:item="{ item, props }">
                                <v-list-item v-bind="props">
                                    <template v-slot:prepend>
                                        <v-icon :icon="item.value"></v-icon>
                                    </template>
                                    <v-list-item-title>{{ item.title }}</v-list-item-title>
                                </v-list-item>
                            </template>
                        </v-select>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="buildingDialog = false" text>Cancel</v-btn>
                    <v-btn @click="saveBuilding" color="primary">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Floor Settings Dialog -->
        <v-dialog v-model="floorSettingsDialog" max-width="500px">
            <v-card>
                <v-card-title>Floor Settings</v-card-title>
                <v-card-text>
                    <v-form ref="floorSettingsForm">
                        <v-text-field
                            v-model="floorForm.name"
                            label="Floor Name"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'Floor name is required']"
                        ></v-text-field>
                        <v-text-field
                            v-model="floorForm.level"
                            label="Floor Level (0 = ground, negative = basement)"
                            type="number"
                            variant="outlined"
                            density="compact"
                        ></v-text-field>
                        <v-divider class="my-3"></v-divider>
                        <p class="text-subtitle-1">Grid Settings</p>
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="floorPlanSettings.width"
                                    label="Width (m)"
                                    type="number"
                                    min="5"
                                    max="100"
                                    variant="outlined"
                                    density="compact"
                                ></v-text-field>
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="floorPlanSettings.height"
                                    label="Height (m)"
                                    type="number"
                                    min="5"
                                    max="100"
                                    variant="outlined"
                                    density="compact"
                                ></v-text-field>
                            </v-col>
                        </v-row>
                        <v-row>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="floorPlanSettings.gridSize"
                                    label="Grid Cell Size (m)"
                                    type="number"
                                    min="0.1"
                                    max="5"
                                    step="0.1"
                                    variant="outlined"
                                    density="compact"
                                ></v-text-field>
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="floorPlanSettings.pixelsPerMeter"
                                    label="Pixels per Meter"
                                    type="number"
                                    min="10"
                                    max="100"
                                    variant="outlined"
                                    density="compact"
                                ></v-text-field>
                            </v-col>
                        </v-row>
                        <v-checkbox
                            v-model="floorPlanSettings.showGrid"
                            label="Show Grid"
                        ></v-checkbox>
                        <v-checkbox
                            v-model="floorPlanSettings.snapToGrid"
                            label="Snap to Grid"
                        ></v-checkbox>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="floorSettingsDialog = false" text>Cancel</v-btn>
                    <v-btn @click="saveFloorSettings" color="primary">Apply Settings</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Room Settings Dialog -->
        <v-dialog v-model="roomDialog" max-width="500px">
            <v-card>
                <v-card-title>
                    {{ editingIndex === null ? 'Add New Room' : 'Edit Room' }}
                </v-card-title>
                <v-card-text>
                    <v-form ref="roomForm">
                        <v-text-field
                            v-model="roomForm.id"
                            label="ID (no spaces)"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'ID is required', v => /^[a-zA-Z0-9_-]+$/.test(v) || 'ID must only contain letters, numbers, underscores, and hyphens']"
                        ></v-text-field>
                        <v-text-field
                            v-model="roomForm.name"
                            label="Room Name"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'Room name is required']"
                        ></v-text-field>
                        <v-select
                            v-model="roomForm.type"
                            label="Room Type"
                            :items="[
                { value: 'room', title: 'Regular Room' },
                { value: 'hallway', title: 'Hallway/Corridor' },
                { value: 'entrance', title: 'Entrance Hall' },
                { value: 'stairs', title: 'Stairwell' },
                { value: 'bathroom', title: 'Bathroom' },
                { value: 'kitchen', title: 'Kitchen' },
                { value: 'storage', title: 'Storage Room' },
              ]"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="compact"
                        ></v-select>
                        <v-textarea
                            v-model="roomForm.description"
                            label="Description"
                            variant="outlined"
                            auto-grow
                            density="compact"
                        ></v-textarea>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="roomDialog = false" text>Cancel</v-btn>
                    <v-btn @click="saveRoom" color="primary">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Door Settings Dialog -->
        <v-dialog v-model="doorDialog" max-width="500px">
            <v-card>
                <v-card-title>
                    {{ editingIndex === null ? 'Add New Door' : 'Edit Door' }}
                </v-card-title>
                <v-card-text>
                    <v-form ref="doorForm">
                        <v-text-field
                            v-model="doorForm.id"
                            label="ID (no spaces)"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'ID is required', v => /^[a-zA-Z0-9_-]+$/.test(v) || 'ID must only contain letters, numbers, underscores, and hyphens']"
                        ></v-text-field>
                        <v-text-field
                            v-model="doorForm.name"
                            label="Door Name"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'Door name is required']"
                        ></v-text-field>
                        <v-select
                            v-model="doorForm.leadsTo"
                            label="Leads To"
                            :items="getDoorDestinations()"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'Destination is required']"
                        ></v-select>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="doorDialog = false" text>Cancel</v-btn>
                    <v-btn @click="saveDoor" color="primary">Save</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- New Floor Dialog -->
        <v-dialog v-model="newFloorDialog" max-width="500px">
            <v-card>
                <v-card-title>Add New Floor</v-card-title>
                <v-card-text>
                    <v-form ref="newFloorForm">
                        <v-text-field
                            v-model="floorForm.id"
                            label="ID (no spaces)"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'ID is required', v => /^[a-zA-Z0-9_-]+$/.test(v) || 'ID must only contain letters, numbers, underscores, and hyphens']"
                        ></v-text-field>
                        <v-text-field
                            v-model="floorForm.name"
                            label="Floor Name"
                            variant="outlined"
                            density="compact"
                            :rules="[v => !!v || 'Floor name is required']"
                        ></v-text-field>
                        <v-text-field
                            v-model="floorForm.level"
                            label="Floor Level (0 = ground, negative = basement)"
                            type="number"
                            variant="outlined"
                            density="compact"
                        ></v-text-field>
                    </v-form>
                </v-card-text>
                <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn @click="newFloorDialog = false" text>Cancel</v-btn>
                    <v-btn @click="saveNewFloor" color="primary">Add Floor</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Snackbar for notifications -->
        <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
            {{ snackbar.text }}
            <template v-slot:actions>
                <v-btn variant="text" @click="snackbar.show = false">Close</v-btn>
            </template>
        </v-snackbar>
    </v-container>
</template>

<script>
import { defineComponent, ref, reactive, onMounted, onUnmounted, watch, nextTick, computed } from 'vue';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';

export default defineComponent({
    name: 'MapAdmin',
    setup() {
        // Map references
        let map = null;
        let drawingLayer = null;
        let tempMarkers = [];
        let locationMarkers = {};
        let buildingMarkers = {};
        let roadLayers = {};
        let regionLayers = {};
        let decorationMarkers = {};

        // Debug mode
        const DEBUG = true;
        function logDebug(message) {
            if (DEBUG) {
                console.log(`[MapAdmin] ${message}`);
            }
        }

        // UI State
        const activePanel = ref(0);
        const mapMode = ref('view');
        const activeTool = ref(null);
        const activeDecoType = ref(null);
        const tempDrawingPoints = ref([]);
        const editingIndex = ref(null);
        const selectedDecoration = ref([]);
        const activeEditForm = ref(null);

        // Building and Indoor mode state
        const activeBuildingId = ref(null);
        const activeFloorId = ref(null);
        const indoorMap = ref(null);
        const floorElements = ref({
            walls: [],
            rooms: [],
            doors: []
        });
        const floorToolActive = ref(null);

        // Dialog Controls
        const importDialog = ref(false);
        const exportDialog = ref(false);
        const buildingDialog = ref(false);
        const floorSettingsDialog = ref(false);
        const roomDialog = ref(false);
        const doorDialog = ref(false);
        const newFloorDialog = ref(false);
        const snackbar = reactive({
            show: false,
            text: '',
            color: 'success'
        });

        // Form Data
        const locationData = reactive({
            id: '',
            name: '',
            description: '',
            lat: 0,
            lng: 0
        });

        const buildingForm = reactive({
            id: '',
            name: '',
            description: '',
            coords: [0, 0],
            icon: 'mdi-home'
        });

        const floorForm = reactive({
            id: '',
            name: '',
            level: 0,
            backgroundImage: null
        });

        const roomForm = reactive({
            id: '',
            name: '',
            type: 'room',
            description: '',
            coords: []
        });

        const doorForm = reactive({
            id: '',
            name: '',
            coords: [0, 0],
            leadsTo: ''
        });

        const roadForm = reactive({
            name: '',
            type: 'main',
            color: '#8B4513',
            weight: 3,
            opacity: 0.8,
            isDashed: false,
            dashArray: ''
        });

        const regionForm = reactive({
            name: '',
            color: '#4a2c82',
            fillOpacity: 0.2
        });

        // Map Data
        const mapSettings = reactive({
            title: 'Realm of Eldoria',
            centerLat: 41.40,
            centerLng: -8.15,
            zoom: 11,
            sepia: 20,
            saturation: 120,
            hue: 10,
            indoorMode: false
        });

        const locations = reactive({
            castle: {
                name: "Crystalspire Castle",
                coords: [41.40, -8.20],
                description: "The magnificent royal castle, home to the rulers of Eldoria."
            },
            forest: {
                name: "Mistwood Forest",
                coords: [41.35, -8.05],
                description: "An ancient magical forest where mystical creatures and fae folk dwell."
            },
            mountains: {
                name: "Dragonscale Mountains",
                coords: [41.55, -8.10],
                description: "Towering peaks where dragons once ruled, now home to the dwarven kingdom."
            },
            port: {
                name: "Port Silvermoon",
                coords: [41.45, -8.30],
                description: "A bustling coastal town known for trade and pirate legends."
            },
            plains: {
                name: "Whispering Plains",
                coords: [41.25, -8.15],
                description: "Vast grasslands where nomadic tribes follow ancient magical ley lines."
            }
        });

        const roads = ref([
            {
                name: "King's Highway",
                type: "main",
                path: [
                    [41.40, -8.20],
                    [41.42, -8.25],
                    [41.44, -8.27],
                    [41.45, -8.30]
                ],
                style: { color: "#8B4513", weight: 5, opacity: 0.8 }
            },
            {
                name: "Eastern Royal Road",
                type: "main",
                path: [
                    [41.40, -8.20],
                    [41.38, -8.15],
                    [41.37, -8.10],
                    [41.36, -8.07],
                    [41.35, -8.05]
                ],
                style: { color: "#8B4513", weight: 5, opacity: 0.8 }
            }
        ]);

        const regions = ref([
            {
                name: "Kingdom of Eldoria",
                coords: [
                    [41.50, -8.30],
                    [41.55, -8.15],
                    [41.45, -8.00],
                    [41.35, -8.10],
                    [41.30, -8.25]
                ],
                style: {color: "#4a2c82", fillOpacity: 0.2}
            }
        ]);

        const decorations = ref([
            { type: "seaMonster", coords: [41.20, -8.40] },
            { type: "compass", coords: [41.25, -8.35] },
            { type: "treasure", coords: [41.42, -8.22] }
        ]);

        // Buildings data structure
        const buildings = ref([
            {
                id: 'castle_main',
                name: 'Castle Main Hall',
                description: 'The grand hall of Crystalspire Castle',
                coords: [41.40, -8.20],
                icon: 'mdi-castle',
                floors: [
                    {
                        id: 'ground_floor',
                        name: 'Ground Floor',
                        level: 0,
                        backgroundImage: null,
                        walls: [
                            // Example wall coordinates for floor plan
                            [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]]
                        ],
                        rooms: [
                            {
                                id: 'throne_room',
                                name: 'Throne Room',
                                type: 'room',
                                coords: [[2, 2], [8, 2], [8, 8], [2, 8], [2, 2]],
                                description: 'The royal throne room where the king holds court'
                            }
                        ],
                        doors: [
                            {
                                id: 'main_entrance',
                                name: 'Main Entrance',
                                coords: [5, 0],
                                leadsTo: 'outside'
                            }
                        ]
                    },
                    {
                        id: 'upper_floor',
                        name: 'Upper Floor',
                        level: 1,
                        backgroundImage: null,
                        walls: [
                            // Upper floor walls
                            [[1, 1], [9, 1], [9, 9], [1, 9], [1, 1]]
                        ],
                        rooms: [
                            {
                                id: 'royal_chambers',
                                name: 'Royal Chambers',
                                type: 'room',
                                coords: [[2, 2], [8, 2], [8, 8], [2, 8], [2, 2]],
                                description: 'The private chambers of the royal family'
                            }
                        ],
                        doors: [
                            {
                                id: 'stairs',
                                name: 'Main Staircase',
                                coords: [5, 5],
                                leadsTo: 'ground_floor'
                            }
                        ]
                    }
                ]
            },
            {
                id: 'tavern',
                name: 'Silver Dragon Tavern',
                description: 'A popular tavern in Port Silvermoon',
                coords: [41.44, -8.28],
                icon: 'mdi-glass-mug-variant',
                floors: [
                    {
                        id: 'tavern_main',
                        name: 'Main Floor',
                        level: 0,
                        backgroundImage: null,
                        walls: [
                            [[0, 0], [6, 0], [6, 8], [0, 8], [0, 0]]
                        ],
                        rooms: [
                            {
                                id: 'common_room',
                                name: 'Common Room',
                                type: 'room',
                                coords: [[1, 1], [5, 1], [5, 6], [1, 6], [1, 1]],
                                description: 'The main tavern hall, filled with tables and a large fireplace'
                            }
                        ],
                        doors: [
                            {
                                id: 'tavern_entrance',
                                name: 'Entrance',
                                coords: [3, 0],
                                leadsTo: 'outside'
                            }
                        ]
                    },
                    {
                        id: 'tavern_cellar',
                        name: 'Cellar',
                        level: -1,
                        backgroundImage: null,
                        walls: [
                            [[1, 1], [5, 1], [5, 5], [1, 5], [1, 1]]
                        ],
                        rooms: [
                            {
                                id: 'wine_cellar',
                                name: 'Wine Cellar',
                                type: 'room',
                                coords: [[1.5, 1.5], [4.5, 1.5], [4.5, 4.5], [1.5, 4.5], [1.5, 1.5]],
                                description: 'Storage for wine and spirits'
                            }
                        ],
                        doors: [
                            {
                                id: 'cellar_stairs',
                                name: 'Cellar Stairs',
                                coords: [3, 2],
                                leadsTo: 'tavern_main'
                            }
                        ]
                    }
                ]
            }
        ]);

        const decorationIcons = {
            seaMonster: 'mdi-waves',
            compass: 'mdi-compass',
            treasure: 'mdi-treasure-chest',
            mountain: 'mdi-mountain',
            forest: 'mdi-pine-tree',
            building: 'mdi-home'
        };

        // Color swatches
        const roadColorSwatches = [
            '#8B4513', '#A0522D', '#8B6914', '#6B4226',
            '#654321', '#A67B5B', '#483C32', '#4A3C2A'
        ];

        const regionColorSwatches = [
            '#4a2c82', '#2c824a', '#82542c', '#2c6482',
            '#822c2c', '#6c2c82', '#2c8282', '#827e2c'
        ];

        // Import/Export data
        const importData = ref('');
        const exportData = ref('');

        // Floor plan scale and grid settings
        const floorPlanSettings = reactive({
            gridSize: 1, // in meters
            pixelsPerMeter: 50,
            showGrid: true,
            snapToGrid: true,
            width: 20, // width of floor in meters
            height: 20 // height of floor in meters
        });

        // Watch for map mode changes
        watch(mapMode, (newMode) => {
            if (newMode === 'view') {
                activeTool.value = null;
                resetDrawing();
                closeEditForm();
            }
        });

        // Watch for active tool changes
        watch(activeTool, (newTool) => {
            // Reset drawing when changing tools
            if (newTool !== 'road' && newTool !== 'region') {
                resetDrawing();
            }
        });

        // Helper: Convert HSV color object to hex string
        function HSVtoHex(colorObj) {
            if (!colorObj || typeof colorObj !== 'object') return colorObj;

            const { h, s, v } = colorObj;

            // If any values are missing, return the original
            if (h === undefined || s === undefined || v === undefined) return colorObj;

            const c = v * s;
            const x = c * (1 - Math.abs((h / 60) % 2 - 1));
            const m = v - c;
            let r = 0, g = 0, b = 0;

            if (h < 60) { r = c; g = x; b = 0; }
            else if (h < 120) { r = x; g = c; b = 0; }
            else if (h < 180) { r = 0; g = c; b = x; }
            else if (h < 240) { r = 0; g = x; b = c; }
            else if (h < 300) { r = x; g = 0; b = c; }
            else { r = c; g = 0; b = x; }

            const R = Math.round((r + m) * 255);
            const G = Math.round((g + m) * 255);
            const B = Math.round((b + m) * 255);

            return '#' + ((1 << 24) + (R << 16) + (G << 8) + B).toString(16).slice(1).toUpperCase();
        }

        // Watch for color changes and convert if needed
        watch(() => roadForm.color, (newVal) => {
            if (typeof newVal === 'object') {
                roadForm.color = HSVtoHex(newVal);
            }
        });

        watch(() => regionForm.color, (newVal) => {
            if (typeof newVal === 'object') {
                regionForm.color = HSVtoHex(newVal);
            }
        });

        // Lifecycle hooks
        onMounted(() => {
            logDebug('Component mounted');
            initMap();
        });

        onUnmounted(() => {
            if (map) {
                map.remove();
                map = null;
            }
        });

        // Map Initialization
        function initMap() {
            // Fix Leaflet's icon paths
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png'
            });

            // Create map
            map = L.map('admin-map', {
                doubleClickZoom: false // Disable default double-click zoom
            }).setView(
                [mapSettings.centerLat, mapSettings.centerLng],
                mapSettings.zoom
            );

            // Add base layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; Fantasy Map | OSM Contributors',
                maxZoom: 18
            }).addTo(map);

            // Apply map styling
            applyMapFilter();

            // Initialize drawing layer
            drawingLayer = L.layerGroup().addTo(map);

            // Draw all elements
            drawAllLocations();
            drawAllRoads();
            drawAllRegions();
            drawAllBuildings();
            drawAllDecorations();

            // Set up map events
            map.on('click', handleMapClick);

            // Explicitly handle double-click at the DOM level rather than relying on Leaflet
            document.getElementById('admin-map').addEventListener('dblclick', function(e) {
                // Don't let the event bubble up to Leaflet
                e.stopPropagation();
                e.preventDefault();

                if (mapMode.value === 'edit' && (activeTool.value === 'road' || activeTool.value === 'region')) {
                    logDebug('DOM double-click captured');

                    // Complete the current drawing if enough points
                    if ((activeTool.value === 'road' && tempDrawingPoints.value.length >= 2) ||
                        (activeTool.value === 'region' && tempDrawingPoints.value.length >= 3)) {
                        saveCurrentForm();
                    } else {
                        showSnackbar(`A ${activeTool.value} must have at least ${activeTool.value === 'road' ? '2' : '3'} points`, 'warning');
                    }
                }
            });

            logDebug('Map initialized with custom double-click handler');
        }

        function applyMapFilter() {
            if (!map) return;
            const filterValue = `sepia(${mapSettings.sepia}%) saturate(${mapSettings.saturation}%) hue-rotate(${mapSettings.hue}deg)`;
            map.getContainer().style.filter = filterValue;
        }

        function applyMapSettings() {
            if (!map) return;
            map.setView(
                [mapSettings.centerLat, mapSettings.centerLng],
                mapSettings.zoom
            );
            applyMapFilter();
            showSnackbar('Map settings applied', 'success');
        }

        // Location functions
        function drawAllLocations() {
            // Clear existing markers
            Object.values(locationMarkers).forEach(marker => {
                if (map) map.removeLayer(marker);
            });
            locationMarkers = {};

            // Custom icon for locations
            const locationIcon = L.icon({
                iconUrl: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iIzRhMmM4MiIgd2lkdGg9IjM2IiBoZWlnaHQ9IjM2Ij48cGF0aCBkPSJNMTIgMkM4LjEzIDIgNSA1LjEzIDUgOWMwIDUuMjUgNyAxMyA3IDEzczctNy43NSA3LTEzYzAtMy44Ny0zLjEzLTctNy03eiIgc3Ryb2tlPSIjZmZmIiBzdHJva2Utd2lkdGg9IjEuNSIvPjxjaXJjbGUgY3g9IjEyIiBjeT0iOSIgcj0iMi41IiBmaWxsPSIjZmZmIi8+PC9zdmc+',
                iconSize: [32, 32],
                iconAnchor: [16, 32],
                popupAnchor: [0, -32]
            });

            // Add each location
            Object.keys(locations).forEach(locId => {
                const loc = locations[locId];
                const marker = L.marker(loc.coords, {icon: locationIcon}).addTo(map);
                marker.bindTooltip(loc.name);

                // When marker is clicked in edit mode, edit the location
                marker.on('click', () => {
                    if (mapMode.value === 'edit' && !activeTool.value) {
                        editLocation(locId);
                    }
                });

                locationMarkers[locId] = marker;
            });
        }

        function editLocation(id) {
            logDebug(`Editing location with id ${id}`);
            if (!locations[id]) {
                showSnackbar(`Location with id ${id} not found`, 'error');
                return;
            }

            editingIndex.value = id;
            const loc = locations[id];

            locationData.id = id;
            locationData.name = loc.name || '';
            locationData.description = loc.description || '';
            locationData.lat = loc.coords ? loc.coords[0] : mapSettings.centerLat;
            locationData.lng = loc.coords ? loc.coords[1] : mapSettings.centerLng;

            // Show the edit form
            activeEditForm.value = 'location';
        }

        function deleteLocation(id) {
            if (confirm(`Are you sure you want to delete "${locations[id].name}"?`)) {
                if (locationMarkers[id]) {
                    map.removeLayer(locationMarkers[id]);
                    delete locationMarkers[id];
                }
                delete locations[id];
                showSnackbar('Location deleted', 'success');
            }
        }

        // Road functions
        function drawAllRoads() {
            // Clear existing roads
            Object.values(roadLayers).forEach(layer => {
                if (map) map.removeLayer(layer);
            });
            roadLayers = {};

            // Add each road
            roads.value.forEach((road, index) => {
                const roadLayer = L.polyline(road.path, road.style).addTo(map);
                roadLayer.bindTooltip(road.name);

                // Make roads clickable for editing
                roadLayer.on('click', () => {
                    if (mapMode.value === 'edit' && !activeTool.value) {
                        editRoad(index);
                    }
                });

                roadLayers[index] = roadLayer;
            });
        }

        function editRoad(index) {
            logDebug(`Editing road at index ${index}`);
            if (!roads.value[index]) {
                showSnackbar(`Road at index ${index} not found`, 'error');
                return;
            }

            // Set editing state
            editingIndex.value = index;

            // Get the road data
            const road = roads.value[index];

            // Set form values
            roadForm.name = road.name || 'Unknown Road';
            roadForm.type = road.type || 'main';
            roadForm.color = road.style?.color || '#8B4513';
            roadForm.weight = road.style?.weight || 3;
            roadForm.opacity = road.style?.opacity || 0.8;
            roadForm.isDashed = !!road.style?.dashArray;
            roadForm.dashArray = road.style?.dashArray || '';

            // Copy path to drawing points
            tempDrawingPoints.value = [...(road.path || [])];

            // Set the active tool to road editing
            activeTool.value = 'road';

            // Draw the current path
            drawTempShape();

            // Show the form
            activeEditForm.value = 'road';
        }

        function deleteRoad(index) {
            if (confirm(`Are you sure you want to delete "${roads.value[index].name}"?`)) {
                if (roadLayers[index]) {
                    map.removeLayer(roadLayers[index]);
                    delete roadLayers[index];
                }
                roads.value.splice(index, 1);
                drawAllRoads();
                showSnackbar('Road deleted', 'success');
            }
        }

        // Region functions
        function drawAllRegions() {
            // Clear existing regions
            Object.values(regionLayers).forEach(layer => {
                if (map) map.removeLayer(layer);
            });
            regionLayers = {};

            // Add each region
            regions.value.forEach((region, index) => {
                const regionLayer = L.polygon(region.coords, region.style).addTo(map);
                regionLayer.bindTooltip(region.name);

                // Make regions clickable for editing
                regionLayer.on('click', () => {
                    if (mapMode.value === 'edit' && !activeTool.value) {
                        editRegion(index);
                    }
                });

                regionLayers[index] = regionLayer;
            });
        }

        function editRegion(index) {
            logDebug(`Editing region at index ${index}`);
            if (!regions.value[index]) {
                showSnackbar(`Region at index ${index} not found`, 'error');
                return;
            }

            // Set editing state
            editingIndex.value = index;

            // Get the region data
            const region = regions.value[index];

            // Set form values
            regionForm.name = region.name || 'Unknown Region';
            regionForm.color = region.style?.color || '#4a2c82';
            regionForm.fillOpacity = region.style?.fillOpacity || 0.2;

            // Copy coords to drawing points
            tempDrawingPoints.value = [...(region.coords || [])];

            // Set the active tool to region editing
            activeTool.value = 'region';

            // Draw the current shape
            drawTempShape();

            // Show the form
            activeEditForm.value = 'region';
        }

        function deleteRegion(index) {
            if (confirm(`Are you sure you want to delete "${regions.value[index].name}"?`)) {
                if (regionLayers[index]) {
                    map.removeLayer(regionLayers[index]);
                    delete regionLayers[index];
                }
                regions.value.splice(index, 1);
                drawAllRegions();
                showSnackbar('Region deleted', 'success');
            }
        }

        // Draw buildings on map
        function drawAllBuildings() {
            console.log('drawbuldings')
            // Clear existing building markers
            if (buildingMarkers) {
                Object.values(buildingMarkers).forEach(marker => {
                    if (map) map.removeLayer(marker);
                });
            }
            buildingMarkers = {};

            // Add each building
            buildings.value.forEach((building, index) => {
                console.log('building',building)
                // Create custom icon based on building type
                const buildingIcon = L.divIcon({
                    className: 'building-icon',
                    html: `<i class="${building.icon}" style="font-size: 24px; color: #82362c;"></i>`,
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });


                const marker = L.marker(building.coords, { icon: buildingIcon }).addTo(map);
                marker.bindTooltip(building.name);

                // When marker is clicked in edit mode, edit the building
                marker.on('click', () => {
                    if (mapMode.value === 'edit' && activeTool.value === 'building') {
                        // Open building form with these coordinates
                        editBuilding(index);
                    } else if (mapMode.value === 'view' || !activeTool.value) {
                        // In view mode or when no tool is active, enter the building
                        enterBuilding(building.id);
                    }
                });

                buildingMarkers[building.id] = marker;
            });

            logDebug(`Drew ${buildings.value.length} buildings on map`);
        }

        function drawAllBuildings2() {
            console.log('drawbuldings')
            // Clear existing markers
            // Object.values(buildingMarkers).forEach(marker => {
            //     if (map) map.removeLayer(marker);
            // });
            // buildingMarkers = {};

            // Custom icon for building
            // const buildingIcon = L.icon({
            //     iconUrl: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0iIzRhMmM4MiIgd2lkdGg9IjM2IiBoZWlnaHQ9IjM2Ij48cGF0aCBkPSJNMTIgMkM4LjEzIDIgNSA1LjEzIDUgOWMwIDUuMjUgNyAxMyA3IDEzczctNy43NSA3LTEzYzAtMy44Ny0zLjEzLTctNy03eiIgc3Ryb2tlPSIjZmZmIiBzdHJva2Utd2lkdGg9IjEuNSIvPjxjaXJjbGUgY3g9IjEyIiBjeT0iOSIgcj0iMi41IiBmaWxsPSIjZmZmIi8+PC9zdmc+',
            //     iconSize: [32, 32],
            //     iconAnchor: [16, 32],
            //     popupAnchor: [0, -32]
            // });

            // const buildingIcon = L.divIcon({
            //     className: 'building-icon',
            //     html: `<i class="${building.icon}" style="font-size: 24px; color: #4a2c82;"></i>`,
            //     iconSize: [24, 24],
            //     iconAnchor: [12, 12]
            // });

            // Add each location
            // Object.keys(buildings).forEach(locId => {
            //     const loc = buildings[locId];
            //     console.log(loc);
            //     // const marker = L.marker(loc.coords, {icon: buildingIcon}).addTo(map);
            //     // marker.bindTooltip(loc.name);
            //     //
            //     // // When marker is clicked in edit mode, edit the location
            //     // marker.on('click', () => {
            //     //     if (mapMode.value === 'edit' && !activeTool.value) {
            //     //         editBuilding(locId);
            //     //     }
            //     // });
            //
            //     // buildingMarkers[locId] = marker;
            // });
        }

        // Decoration functions
        function drawAllDecorations() {
            // Clear existing decorations
            Object.values(decorationMarkers).forEach(marker => {
                if (map) map.removeLayer(marker);
            });
            decorationMarkers = {};

            // Draw each decoration
            decorations.value.forEach((deco, index) => {
                let icon;

                if (deco.type === 'seaMonster') {
                    icon = L.divIcon({
                        className: 'sea-monster-icon',
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="40" height="40"><path fill="#2a6b8f" d="M22,10c-0.3-1.5-1.9-2.1-3.3-2.1c-1.3,0-2.8,0.4-3.4,1.6c-1.5-2.5-4.5-3.2-7.3-3.2c-2.6,0-5.3,1.2-6.2,3.9 c-0.4,1.1-0.1,2.2,0.6,3.1c1.4,1.7,4.1,2.1,6.2,2.3c1.5,0.1,3.1,0.2,4.5-0.3c0.9-0.3,1.7-0.8,2.5-1.4c0.9,0.9,2.1,1.4,3.4,1.4 c2,0,3.9-1.3,3.9-3.4C23,11.2,22.3,10.4,22,10z"/><circle fill="#fff" cx="8" cy="9" r="1"/></svg>',
                        iconSize: [40, 40],
                        iconAnchor: [20, 20]
                    });
                } else if (deco.type === 'compass') {
                    icon = L.divIcon({
                        className: 'compass-icon',
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="60" height="60"><circle cx="50" cy="50" r="45" fill="rgba(255,255,255,0.6)" stroke="#4a2c82" stroke-width="2"/><polygon points="50,5 55,50 50,95 45,50" fill="#4a2c82"/><polygon points="5,50 50,45 95,50 50,55" fill="#4a2c82"/><circle cx="50" cy="50" r="5" fill="#4a2c82"/></svg>',
                        iconSize: [60, 60],
                        iconAnchor: [30, 30]
                    });
                } else if (deco.type === 'treasure') {
                    icon = L.divIcon({
                        className: 'treasure-icon',
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="#c9a61b" d="M5,5v14h14V5H5z M17,17H7V7h10V17z M8,9h8v2H8V9z M8,12h8v2H8V12z"/></svg>',
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                } else if (deco.type === 'mountain') {
                    icon = L.divIcon({
                        className: 'mountain-icon',
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30"><path fill="#82542c" d="M14,10l-2-5L8,10l-5,9h18L14,10z"/></svg>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    });
                } else if (deco.type === 'forest') {
                    icon = L.divIcon({
                        className: 'forest-icon',
                        html: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="30" height="30"><path fill="#2c824a" d="M12,2L4,9h2v13h12V9h2L12,2z"/></svg>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    });
                }

                if (icon) {
                    const marker = L.marker(deco.coords, {icon: icon}).addTo(map);
                    marker.bindTooltip(deco.type);
                    decorationMarkers[index] = marker;
                }
            });
        }

        function deleteDecoration(index) {
            if (confirm(`Are you sure you want to delete this ${decorations.value[index].type}?`)) {
                if (decorationMarkers[index]) {
                    map.removeLayer(decorationMarkers[index]);
                    delete decorationMarkers[index];
                }
                decorations.value.splice(index, 1);
                drawAllDecorations();
                showSnackbar('Decoration deleted', 'success');
            }
        }

        // Tool functions
        function startTool(tool, decoType = null) {
            mapMode.value = 'edit';
            activeTool.value = tool;
            activeDecoType.value = decoType;
            editingIndex.value = null;

            // Clear any previous drawing
            resetDrawing();

            // Initialize the appropriate form if needed
            if (tool === 'location') {
                locationData.id = `loc_${Date.now()}`;
                locationData.name = 'New Location';
                locationData.description = '';
                locationData.lat = mapSettings.centerLat;
                locationData.lng = mapSettings.centerLng;
            } else if (tool === 'road') {
                roadForm.name = 'New Road';
                roadForm.type = 'main';
                roadForm.color = '#8B4513';
                roadForm.weight = 3;
                roadForm.opacity = 0.8;
                roadForm.isDashed = false;
                roadForm.dashArray = '';

                // Open the road edit form
                activeEditForm.value = 'road';
            } else if (tool === 'region') {
                regionForm.name = 'New Region';
                regionForm.color = '#4a2c82';
                regionForm.fillOpacity = 0.2;

                // Open the region edit form
                activeEditForm.value = 'region';
            } else {
                // Reset the edit form
                activeEditForm.value = null;
            }

            logDebug(`Started ${tool} tool ${decoType ? 'with ' + decoType : ''}`);
        }

        function resetDrawing() {
            // Clear temporary points
            tempDrawingPoints.value = [];

            // Clear the drawing layer
            if (drawingLayer) {
                drawingLayer.clearLayers();
            }

            // Remove any temporary markers
            tempMarkers.forEach(marker => {
                if (map) map.removeLayer(marker);
            });
            tempMarkers = [];

            logDebug('Drawing reset');
        }

        function removeLastPoint() {
            if (tempDrawingPoints.value.length > 0) {
                tempDrawingPoints.value.pop();
                drawTempShape();
            }
        }

        function drawTempShape() {
            if (!drawingLayer) return;

            // Clear existing drawing
            drawingLayer.clearLayers();

            // Remove any temporary markers
            tempMarkers.forEach(marker => {
                if (map) map.removeLayer(marker);
            });
            tempMarkers = [];

            if (tempDrawingPoints.value.length === 0) return;

            if (activeTool.value === 'road') {
                // Draw road path if there are at least 2 points
                if (tempDrawingPoints.value.length > 1) {
                    L.polyline(tempDrawingPoints.value, {
                        color: roadForm.color || '#ff4081',
                        weight: roadForm.weight || 4,
                        opacity: roadForm.opacity || 0.8,
                        dashArray: roadForm.isDashed ? roadForm.dashArray : null
                    }).addTo(drawingLayer);
                }

                // Add markers for each point with click to remove
                tempDrawingPoints.value.forEach((point, index) => {
                    const marker = L.circleMarker(point, {
                        radius: 5,
                        color: '#ff4081',
                        fillColor: '#ffffff',
                        fillOpacity: 1,
                        weight: 2
                    }).addTo(map);

                    // Add click handler to remove point
                    marker.on('click', (e) => {
                        // Stop event propagation
                        L.DomEvent.stopPropagation(e);

                        // Check minimum required points
                        if (tempDrawingPoints.value.length <= 2) {
                            showSnackbar('A road must have at least 2 points', 'warning');
                            return;
                        }

                        // Remove the point and redraw
                        tempDrawingPoints.value.splice(index, 1);
                        drawTempShape();
                    });

                    tempMarkers.push(marker);
                });
            } else if (activeTool.value === 'region') {
                // Draw polygon if there are at least 3 points
                if (tempDrawingPoints.value.length >= 3) {
                    L.polygon(tempDrawingPoints.value, {
                        color: regionForm.color || '#ff4081',
                        fillOpacity: regionForm.fillOpacity || 0.2
                    }).addTo(drawingLayer);
                }
                // Otherwise draw a line if there are 2 points
                else if (tempDrawingPoints.value.length === 2) {
                    L.polyline(tempDrawingPoints.value, {
                        color: regionForm.color || '#ff4081',
                        dashArray: '5, 10'
                    }).addTo(drawingLayer);
                }

                // Add markers for each point with click to remove
                tempDrawingPoints.value.forEach((point, index) => {
                    const marker = L.circleMarker(point, {
                        radius: 5,
                        color: '#ff4081',
                        fillColor: '#ffffff',
                        fillOpacity: 1,
                        weight: 2
                    }).addTo(map);

                    // Add click handler to remove point
                    marker.on('click', (e) => {
                        // Stop event propagation
                        L.DomEvent.stopPropagation(e);

                        // Check minimum required points
                        if (tempDrawingPoints.value.length <= 3) {
                            showSnackbar('A region must have at least 3 points', 'warning');
                            return;
                        }

                        // Remove the point and redraw
                        tempDrawingPoints.value.splice(index, 1);
                        drawTempShape();
                    });

                    tempMarkers.push(marker);
                });
            }
        }

        // Map event handlers
        function handleMapClick(e) {
            if (mapMode.value !== 'edit' || !activeTool.value) return;

            const clickLatLng = [e.latlng.lat, e.latlng.lng];

            if (activeTool.value === 'location') {
                // Set location coordinates and show form
                locationData.lat = clickLatLng[0];
                locationData.lng = clickLatLng[1];
                activeEditForm.value = 'location';
            }
            else if (activeTool.value === 'road' || activeTool.value === 'region') {
                // Add point to drawing
                tempDrawingPoints.value.push(clickLatLng);
                drawTempShape();
            }
            else if (activeTool.value === 'building') {

                buildingForm.coords = [clickLatLng[0], clickLatLng[1]];
                activeEditForm.value = 'building';
                buildingDialog.value = true;
            }
            else if (activeTool.value === 'decoration' && activeDecoType.value) {
                // Add decoration directly
                decorations.value.push({
                    type: activeDecoType.value,
                    coords: clickLatLng
                });
                drawAllDecorations();
                showSnackbar(`${activeDecoType.value} added`, 'success');
            }
        }

        // We've replaced this with a direct DOM event listener
        // in the map initialization function

        // Form functions
        function closeEditForm() {
            // Don't close the form if we're in the middle of drawing
            if ((activeTool.value === 'road' || activeTool.value === 'region') && tempDrawingPoints.value.length > 0) {
                if (!confirm('You have unsaved changes. Are you sure you want to discard them?')) {
                    return;
                }
            }

            activeEditForm.value = null;
            editingIndex.value = null;

            // Only reset drawing if we're not trying to draw something else
            if (activeTool.value !== 'road' && activeTool.value !== 'region') {
                resetDrawing();
            }
        }

        function getEditFormTitle() {
            if (!activeEditForm.value) return '';

            if (activeEditForm.value === 'location') {
                return editingIndex.value !== null ? 'Edit Location' : 'Add New Location';
            }
            else if (activeEditForm.value === 'road') {
                return editingIndex.value !== null ? 'Edit Road' : 'Add New Road';
            }
            else if (activeEditForm.value === 'region') {
                return editingIndex.value !== null ? 'Edit Region' : 'Add New Region';
            }

            return 'Edit Item';
        }

        function saveCurrentForm() {
            if (activeEditForm.value === 'location') {
                saveLocation();
            }
            else if (activeEditForm.value === 'road') {
                saveRoad();
            }
            else if (activeEditForm.value === 'region') {
                saveRegion();
            }
        }

        function saveLocation() {
            logDebug('Saving location');

            if (!locationData.id || !locationData.name) {
                showSnackbar('ID and name are required', 'error');
                return;
            }

            const id = locationData.id;
            const coords = [
                parseFloat(locationData.lat),
                parseFloat(locationData.lng)
            ];

            if (isNaN(coords[0]) || isNaN(coords[1])) {
                showSnackbar('Invalid coordinates', 'error');
                return;
            }

            // Create or update location
            locations[id] = {
                name: locationData.name,
                description: locationData.description || '',
                coords: coords
            };

            // Redraw all locations
            drawAllLocations();

            // Reset state
            editingIndex.value = null;
            activeEditForm.value = null;

            showSnackbar('Location saved', 'success');
        }

        function saveRoad() {
            logDebug('Saving road');

            if (tempDrawingPoints.value.length < 2) {
                showSnackbar('A road must have at least 2 points', 'error');
                return;
            }

            if (!roadForm.name) {
                showSnackbar('Road name is required', 'error');
                return;
            }

            const style = {
                color: roadForm.color,
                weight: parseFloat(roadForm.weight) || 3,
                opacity: parseFloat(roadForm.opacity) || 0.8
            };

            if (roadForm.isDashed && roadForm.dashArray) {
                style.dashArray = roadForm.dashArray;
            }

            const roadData = {
                name: roadForm.name,
                type: roadForm.type || 'main',
                path: [...tempDrawingPoints.value],
                style: style
            };

            if (editingIndex.value !== null) {
                // Update existing road
                roads.value[editingIndex.value] = roadData;
            } else {
                // Add new road
                roads.value.push(roadData);
            }

            // Redraw all roads
            drawAllRoads();

            // Reset state
            editingIndex.value = null;
            tempDrawingPoints.value = [];
            activeTool.value = null;
            activeEditForm.value = null;
            resetDrawing();

            showSnackbar('Road saved', 'success');
        }

        function saveRegion() {
            logDebug('Saving region');

            if (tempDrawingPoints.value.length < 3) {
                showSnackbar('A region must have at least 3 points', 'error');
                return;
            }

            if (!regionForm.name) {
                showSnackbar('Region name is required', 'error');
                return;
            }

            const style = {
                color: regionForm.color,
                fillOpacity: parseFloat(regionForm.fillOpacity) || 0.2
            };

            const regionData = {
                name: regionForm.name,
                coords: [...tempDrawingPoints.value],
                style: style
            };

            if (editingIndex.value !== null) {
                // Update existing region
                regions.value[editingIndex.value] = regionData;
            } else {
                // Add new region
                regions.value.push(regionData);
            }

            // Redraw all regions
            drawAllRegions();

            // Reset state
            editingIndex.value = null;
            tempDrawingPoints.value = [];
            activeTool.value = null;
            activeEditForm.value = null;
            resetDrawing();

            showSnackbar('Region saved', 'success');
        }

        // Import/Export Functions
        function exportMapData() {
            const data = {
                mapSettings: mapSettings,
                locations: locations,
                roads: roads.value,
                regions: regions.value,
                decorations: decorations.value,
                buildings: buildings.value
            };

            exportData.value = JSON.stringify(data, null, 2);
            exportDialog.value = true;

            logDebug('Map data exported');
        }

        function copyExportData() {
            navigator.clipboard.writeText(exportData.value)
                .then(() => showSnackbar('Copied to clipboard', 'success'))
                .catch(() => showSnackbar('Failed to copy', 'error'));
        }

        function importMapData() {
            try {
                const data = JSON.parse(importData.value);

                // Validate data structure
                if (!data.mapSettings || !data.locations || !data.roads || !data.regions || !data.decorations) {
                    throw new Error('Invalid data structure');
                }

                // Update map settings
                Object.assign(mapSettings, data.mapSettings);

                // Clear existing locations
                Object.keys(locations).forEach(key => {
                    delete locations[key];
                });

                // Add imported locations
                Object.entries(data.locations).forEach(([key, value]) => {
                    locations[key] = value;
                });

                // Update other data
                roads.value = data.roads;
                regions.value = data.regions;
                decorations.value = data.decorations;

                // Import buildings if available
                if (data.buildings) {
                    buildings.value = data.buildings;
                }

                // Redraw everything if map is initialized
                if (map) {
                    applyMapSettings();
                    drawAllLocations();
                    drawAllRoads();
                    drawAllRegions();
                    drawAllBuildings()
                    drawAllDecorations();

                    // If we're in a building, exit to refresh the view
                    if (activeBuildingId.value) {
                        exitBuilding();
                    }
                }

                importDialog.value = false;
                showSnackbar('Map data imported successfully', 'success');
            } catch (error) {
                showSnackbar('Failed to import data: ' + error.message, 'error');
            }
        }

        function showSnackbar(text, color = 'success') {
            snackbar.text = text;
            snackbar.color = color;
            snackbar.show = true;
        }

        // Building and floor plan functions
        function enterBuilding(buildingId) {
            const building = buildings.value.find(b => b.id === buildingId);
            if (!building) {
                showSnackbar(`Building with ID ${buildingId} not found`, 'error');
                return;
            }

            // Set active building
            activeBuildingId.value = buildingId;

            // Activate the first floor by default
            if (building.floors && building.floors.length > 0) {
                activeFloorId.value = building.floors[0].id;
            } else {
                activeFloorId.value = null;
            }

            // Initialize indoor map
            initIndoorMap();

            showSnackbar(`Entered ${building.name}`, 'success');
        }

        function exitBuilding() {
            // Reset building state
            activeBuildingId.value = null;
            activeFloorId.value = null;

            // Clear floor-related state
            floorElements.value = {
                walls: [],
                rooms: [],
                doors: []
            };
            floorToolActive.value = null;

            // Reinitialize outdoor map
            if (map) {
                map.remove();
                map = null;
            }
            initMap();

            showSnackbar('Exited building', 'success');
        }

        function getActiveBuildingName() {
            if (!activeBuildingId.value) return '';

            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            return building ? building.name : '';
        }

        function getBuildingFloors() {
            if (!activeBuildingId.value) return [];

            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            return building ? building.floors : [];
        }

        function initIndoorMap() {
            logDebug('Initializing indoor map');

            // Clear existing map
            if (map) {
                map.remove();
                map = null;
            }

            // Create a new map instance for indoor use
            indoorMap.value = L.map('admin-map', {
                crs: L.CRS.Simple,
                minZoom: -2,
                maxZoom: 2,
                zoomControl: true,
                doubleClickZoom: false
            });

            // Set map reference
            map = indoorMap.value;

            // Calculate bounds based on floor plan settings
            const pixelsPerMeter = floorPlanSettings.pixelsPerMeter;
            const width = floorPlanSettings.width * pixelsPerMeter;
            const height = floorPlanSettings.height * pixelsPerMeter;

            // Set bounds (origin at top-left)
            const bounds = [
                [0, 0],
                [height, width]
            ];

            // Create a blank white background
            L.rectangle(bounds, {
                color: "#999",
                weight: 1,
                fillColor: "#f8f8f8",
                fillOpacity: 1
            }).addTo(map);

            map.fitBounds(bounds);

            // Create a drawing layer
            drawingLayer = L.layerGroup().addTo(map);

            // Create a grid if needed
            if (floorPlanSettings.showGrid) {
                drawGrid();
            }

            // Draw floor elements
            drawFloorPlan();

            // Add click handler for indoor map
            map.on('click', handleIndoorMapClick);

            // Add double-click handler
            document.getElementById('admin-map').addEventListener('dblclick', function(e) {
                e.stopPropagation();
                e.preventDefault();

                if (mapMode.value === 'edit' && floorToolActive.value) {
                    if (floorToolActive.value === 'room' || floorToolActive.value === 'wall') {
                        if (tempDrawingPoints.value.length >= 3) {
                            handleFloorDrawingComplete();
                        } else {
                            showSnackbar(`Need at least 3 points to complete ${floorToolActive.value}`, 'warning');
                        }
                    }
                }
            });

            logDebug('Indoor map initialized');
        }

        function drawGrid() {
            if (!map) return;

            logDebug('Drawing grid');

            // Create a layer for the grid
            const gridLayer = L.layerGroup().addTo(map);

            // Calculate grid parameters
            const pixelsPerMeter = floorPlanSettings.pixelsPerMeter;
            const gridSize = floorPlanSettings.gridSize * pixelsPerMeter;
            const width = floorPlanSettings.width * pixelsPerMeter;
            const height = floorPlanSettings.height * pixelsPerMeter;

            // Draw horizontal grid lines
            for (let y = 0; y <= height; y += gridSize) {
                L.polyline([[y, 0], [y, width]], {
                    color: '#ddd',
                    weight: 1,
                    opacity: 0.5
                }).addTo(gridLayer);
            }

            // Draw vertical grid lines
            for (let x = 0; x <= width; x += gridSize) {
                L.polyline([[0, x], [height, x]], {
                    color: '#ddd',
                    weight: 1,
                    opacity: 0.5
                }).addTo(gridLayer);
            }

            // Add measurements
            const step = Math.ceil(width / pixelsPerMeter / 10) * 10; // Round to nearest 10m

            for (let x = 0; x <= width; x += step * pixelsPerMeter) {
                if (x > 0) { // Skip the origin
                    L.marker([5, x], {
                        icon: L.divIcon({
                            className: 'grid-label',
                            html: `<div style="background-color: rgba(255,255,255,0.5); padding: 2px; border-radius: 2px; font-size: 10px;">${Math.round(x/pixelsPerMeter)}m</div>`,
                            iconSize: [30, 20],
                            iconAnchor: [15, 10]
                        })
                    }).addTo(gridLayer);
                }
            }

            for (let y = 0; y <= height; y += step * pixelsPerMeter) {
                if (y > 0) { // Skip the origin
                    L.marker([y, 5], {
                        icon: L.divIcon({
                            className: 'grid-label',
                            html: `<div style="background-color: rgba(255,255,255,0.5); padding: 2px; border-radius: 2px; font-size: 10px;">${Math.round(y/pixelsPerMeter)}m</div>`,
                            iconSize: [30, 20],
                            iconAnchor: [15, 10]
                        })
                    }).addTo(gridLayer);
                }
            }

            logDebug('Grid drawing complete');
        }

        function drawFloorPlan() {
            logDebug('Drawing floor plan');

            if (!map || !activeBuildingId.value || !activeFloorId.value) {
                logDebug('Cannot draw floor plan: missing map or building/floor ID');
                return;
            }

            // Clear existing drawing layer
            if (drawingLayer) {
                drawingLayer.clearLayers();
            } else {
                drawingLayer = L.layerGroup().addTo(map);
            }

            // Get current building and floor
            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            if (!building) {
                logDebug('Building not found:', activeBuildingId.value);
                return;
            }

            const floor = building.floors.find(f => f.id === activeFloorId.value);
            if (!floor) {
                logDebug('Floor not found:', activeFloorId.value);
                return;
            }

            logDebug('Drawing floor:', floor.name);

            // Draw walls
            if (floor.walls && floor.walls.length > 0) {
                logDebug(`Drawing ${floor.walls.length} walls`);
                floor.walls.forEach((wall, index) => {
                    // Scale wall coordinates to pixels
                    const scaledWall = wall.map(point => [
                        point[1] * floorPlanSettings.pixelsPerMeter,
                        point[0] * floorPlanSettings.pixelsPerMeter
                    ]);

                    const polyline = L.polyline(scaledWall, {
                        color: '#000',
                        weight: 3,
                        opacity: 1
                    }).addTo(drawingLayer);

                    // Add click handler for editing in edit mode
                    polyline.on('click', () => {
                        if (mapMode.value === 'edit' && !floorToolActive.value) {
                            editWall(index);
                        }
                    });
                });
            } else {
                logDebug('No walls to draw');
            }

            // Draw rooms
            if (floor.rooms && floor.rooms.length > 0) {
                logDebug(`Drawing ${floor.rooms.length} rooms`);
                floor.rooms.forEach((room, index) => {
                    // Scale room coordinates to pixels
                    const scaledCoords = room.coords.map(point => [
                        point[1] * floorPlanSettings.pixelsPerMeter,
                        point[0] * floorPlanSettings.pixelsPerMeter
                    ]);

                    const polygon = L.polygon(scaledCoords, {
                        color: '#3949ab',
                        weight: 2,
                        opacity: 0.8,
                        fillColor: '#c5cae9',
                        fillOpacity: 0.3
                    }).addTo(drawingLayer);

                    // Add click handler for editing in edit mode
                    polygon.on('click', () => {
                        if (mapMode.value === 'edit' && !floorToolActive.value) {
                            editRoom(index);
                        }
                    });

                    // Add room label
                    const center = polygon.getBounds().getCenter();
                    L.marker(center, {
                        icon: L.divIcon({
                            className: 'room-label',
                            html: `<div style="background-color: rgba(255,255,255,0.7); padding: 3px; border-radius: 3px; font-size: 12px;">${room.name}</div>`,
                            iconSize: [100, 20],
                            iconAnchor: [50, 10]
                        })
                    }).addTo(drawingLayer);
                });
            } else {
                logDebug('No rooms to draw');
            }

            // Draw doors
            if (floor.doors && floor.doors.length > 0) {
                logDebug(`Drawing ${floor.doors.length} doors`);
                floor.doors.forEach((door, index) => {
                    // Scale door coordinates to pixels
                    const x = door.coords[0] * floorPlanSettings.pixelsPerMeter;
                    const y = door.coords[1] * floorPlanSettings.pixelsPerMeter;

                    // Draw door icon
                    const doorIcon = door.leadsTo === 'outside' ? 'mdi-door' : 'mdi-door-open';
                    const marker = L.marker([y, x], {
                        icon: L.divIcon({
                            className: 'door-icon',
                            html: `<i class="${doorIcon}" style="font-size: 24px; color: #795548;"></i>`,
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        })
                    }).addTo(drawingLayer);

                    // Add click handler for editing in edit mode
                    marker.on('click', () => {
                        if (mapMode.value === 'edit' && !floorToolActive.value) {
                            editDoor(index);
                        }
                    });

                    marker.bindTooltip(door.name);
                });
            } else {
                logDebug('No doors to draw');
            }

            logDebug('Floor plan drawing completed');
        }

        function handleIndoorMapClick(e) {
            logDebug('Indoor map click detected');

            if (mapMode.value !== 'edit' || !floorToolActive.value) {
                logDebug('Not in edit mode or no floor tool active');
                return;
            }

            // Get click coordinates in floor plan units
            const pixelX = e.latlng.lng;
            const pixelY = e.latlng.lat;

            logDebug(`Click at pixel coordinates: ${pixelX}, ${pixelY}`);

            // Convert to floor plan coordinates (meters)
            let x = pixelX / floorPlanSettings.pixelsPerMeter;
            let y = pixelY / floorPlanSettings.pixelsPerMeter;

            // Snap to grid if enabled
            if (floorPlanSettings.snapToGrid) {
                x = Math.round(x / floorPlanSettings.gridSize) * floorPlanSettings.gridSize;
                y = Math.round(y / floorPlanSettings.gridSize) * floorPlanSettings.gridSize;
            }

            logDebug(`Converted to floor coordinates: ${x}, ${y} meters`);

            if (floorToolActive.value === 'room' || floorToolActive.value === 'wall') {
                // Add point to drawing
                tempDrawingPoints.value.push([x, y]);
                drawTempIndoorShape();

                logDebug(`Added point to ${floorToolActive.value} drawing, total points: ${tempDrawingPoints.value.length}`);
            }
            else if (floorToolActive.value === 'door') {
                // Place door directly
                doorForm.id = `door_${Date.now()}`;
                doorForm.name = 'New Door';
                doorForm.coords = [x, y];
                doorForm.leadsTo = 'outside';

                doorDialog.value = true;
                logDebug('Opened door dialog');
            }
            else if (floorToolActive.value === 'stairs') {
                // Place stairs directly
                doorForm.id = `stairs_${Date.now()}`;
                doorForm.name = 'Stairs';
                doorForm.coords = [x, y];

                // Get list of floors for this building
                const building = buildings.value.find(b => b.id === activeBuildingId.value);
                if (building && building.floors.length > 1) {
                    // Default to connection with adjacent floor
                    const currentFloor = building.floors.find(f => f.id === activeFloorId.value);
                    const currentLevel = currentFloor ? currentFloor.level : 0;

                    // Find adjacent floors
                    const higherFloor = building.floors.find(f => f.level === currentLevel + 1);
                    const lowerFloor = building.floors.find(f => f.level === currentLevel - 1);

                    // Set default connection
                    doorForm.leadsTo = higherFloor ? higherFloor.id : (lowerFloor ? lowerFloor.id : 'outside');

                    doorDialog.value = true;
                    logDebug('Opened stairs dialog');
                } else {
                    showSnackbar('Need at least two floors to create stairs', 'error');
                }
            }
        }

        function drawTempIndoorShape() {
            logDebug('Drawing temporary indoor shape');

            if (!map) {
                logDebug('No map available');
                return;
            }

            // Clear existing temporary drawing
            drawingLayer.clearLayers();

            // Redraw the existing floor elements first
            drawFloorPlan();

            if (tempDrawingPoints.value.length === 0) {
                logDebug('No points to draw');
                return;
            }

            // Scale points to pixels
            const scaledPoints = tempDrawingPoints.value.map(point => [
                point[1] * floorPlanSettings.pixelsPerMeter,
                point[0] * floorPlanSettings.pixelsPerMeter
            ]);

            logDebug(`Drawing ${scaledPoints.length} points for ${floorToolActive.value}`);

            // Draw the shape based on the active tool
            if (floorToolActive.value === 'room') {
                if (scaledPoints.length >= 3) {
                    L.polygon(scaledPoints, {
                        color: '#4caf50',
                        weight: 2,
                        opacity: 0.8,
                        fillColor: '#a5d6a7',
                        fillOpacity: 0.4,
                        dashArray: '5, 5'
                    }).addTo(drawingLayer);

                    logDebug('Drew room polygon');
                } else if (scaledPoints.length === 2) {
                    L.polyline(scaledPoints, {
                        color: '#4caf50',
                        weight: 2,
                        opacity: 0.8,
                        dashArray: '5, 5'
                    }).addTo(drawingLayer);

                    logDebug('Drew room polyline (incomplete)');
                }
            }
            else if (floorToolActive.value === 'wall') {
                L.polyline(scaledPoints, {
                    color: '#000',
                    weight: 3,
                    opacity: 0.8,
                    dashArray: '5, 5'
                }).addTo(drawingLayer);

                logDebug('Drew wall polyline');
            }

            // Draw vertices
            scaledPoints.forEach((point, index) => {
                L.circleMarker(point, {
                    radius: 4,
                    color: '#f44336',
                    fillColor: '#fff',
                    fillOpacity: 1,
                    weight: 2
                }).addTo(drawingLayer);
            });

            logDebug('Drawing complete');
        }

        function handleFloorDrawingComplete() {
            logDebug('Completing floor drawing');

            if (!floorToolActive.value || tempDrawingPoints.value.length < 3) {
                logDebug('Not enough points or no tool active');
                return;
            }

            if (floorToolActive.value === 'room') {
                // Prepare room form data
                roomForm.id = `room_${Date.now()}`;
                roomForm.name = 'New Room';
                roomForm.type = 'room';
                roomForm.description = '';
                roomForm.coords = [...tempDrawingPoints.value];

                // Show room dialog
                roomDialog.value = true;
                logDebug('Opened room dialog');
            }
            else if (floorToolActive.value === 'wall') {
                // Add the wall directly to the floor
                const building = buildings.value.find(b => b.id === activeBuildingId.value);
                if (building) {
                    const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
                    if (floorIndex >= 0) {
                        if (!building.floors[floorIndex].walls) {
                            building.floors[floorIndex].walls = [];
                        }

                        // Add the wall
                        building.floors[floorIndex].walls.push([...tempDrawingPoints.value]);

                        // Reset drawing state
                        tempDrawingPoints.value = [];

                        // Redraw floor plan
                        drawFloorPlan();

                        showSnackbar('Wall added', 'success');
                        logDebug('Wall added successfully');
                    }
                }
            }
        }

        // function initIndoorMap() {
        //     // Clear existing map
        //     if (map) {
        //         map.remove();
        //         map = null;
        //     }
        //
        //     // Create a new map instance for indoor use
        //     indoorMap.value = L.map('admin-map', {
        //         crs: L.CRS.Simple,
        //         minZoom: -2,
        //         maxZoom: 2,
        //         zoomControl: true,
        //         doubleClickZoom: false
        //     });
        //
        //     // Set map reference
        //     map = indoorMap.value;
        //
        //     // Calculate bounds based on floor plan settings
        //     const pixelsPerMeter = floorPlanSettings.pixelsPerMeter;
        //     const width = floorPlanSettings.width * pixelsPerMeter;
        //     const height = floorPlanSettings.height * pixelsPerMeter;
        //
        //     // Set bounds (origin at top-left)
        //     const bounds = [
        //         [0, 0],
        //         [height, width]
        //     ];
        //     map.fitBounds(bounds);
        //
        //     // Create a grid if needed
        //     if (floorPlanSettings.showGrid) {
        //         drawGrid();
        //     }
        //
        //     // Draw floor elements
        //     drawFloorPlan();
        //
        //     // Add click handler for indoor map
        //     map.on('click', handleIndoorMapClick);
        //
        //     // Add double-click handler
        //     document.getElementById('admin-map').addEventListener('dblclick', function(e) {
        //         e.stopPropagation();
        //         e.preventDefault();
        //
        //         if (mapMode.value === 'edit' && floorToolActive.value) {
        //             if (floorToolActive.value === 'room' || floorToolActive.value === 'wall') {
        //                 if (tempDrawingPoints.value.length >= 3) {
        //                     handleFloorDrawingComplete();
        //                 } else {
        //                     showSnackbar(`Need at least 3 points to complete ${floorToolActive.value}`, 'warning');
        //                 }
        //             }
        //         }
        //     });
        // }
        //
        // function drawGrid() {
        //     if (!map) return;
        //
        //     // Create a layer for the grid
        //     const gridLayer = L.layerGroup().addTo(map);
        //
        //     // Calculate grid parameters
        //     const pixelsPerMeter = floorPlanSettings.pixelsPerMeter;
        //     const gridSize = floorPlanSettings.gridSize * pixelsPerMeter;
        //     const width = floorPlanSettings.width * pixelsPerMeter;
        //     const height = floorPlanSettings.height * pixelsPerMeter;
        //
        //     // Draw horizontal grid lines
        //     for (let y = 0; y <= height; y += gridSize) {
        //         L.polyline([[y, 0], [y, width]], {
        //             color: '#ddd',
        //             weight: 1,
        //             opacity: 0.5
        //         }).addTo(gridLayer);
        //     }
        //
        //     // Draw vertical grid lines
        //     for (let x = 0; x <= width; x += gridSize) {
        //         L.polyline([[0, x], [height, x]], {
        //             color: '#ddd',
        //             weight: 1,
        //             opacity: 0.5
        //         }).addTo(gridLayer);
        //     }
        // }
        //
        // function drawFloorPlan() {
        //     if (!map || !activeBuildingId.value || !activeFloorId.value) return;
        //
        //     // Clear existing drawing layer
        //     if (drawingLayer) {
        //         drawingLayer.clearLayers();
        //     } else {
        //         drawingLayer = L.layerGroup().addTo(map);
        //     }
        //
        //     // Get current building and floor
        //     const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //     if (!building) return;
        //
        //     const floor = building.floors.find(f => f.id === activeFloorId.value);
        //     if (!floor) return;
        //
        //     // Draw walls
        //     if (floor.walls && floor.walls.length > 0) {
        //         floor.walls.forEach(wall => {
        //             // Scale wall coordinates to pixels
        //             const scaledWall = wall.map(point => [
        //                 point[1] * floorPlanSettings.pixelsPerMeter,
        //                 point[0] * floorPlanSettings.pixelsPerMeter
        //             ]);
        //
        //             L.polyline(scaledWall, {
        //                 color: '#000',
        //                 weight: 3,
        //                 opacity: 1
        //             }).addTo(drawingLayer);
        //         });
        //     }
        //
        //     // Draw rooms
        //     if (floor.rooms && floor.rooms.length > 0) {
        //         floor.rooms.forEach(room => {
        //             // Scale room coordinates to pixels
        //             const scaledCoords = room.coords.map(point => [
        //                 point[1] * floorPlanSettings.pixelsPerMeter,
        //                 point[0] * floorPlanSettings.pixelsPerMeter
        //             ]);
        //
        //             const polygon = L.polygon(scaledCoords, {
        //                 color: '#3949ab',
        //                 weight: 2,
        //                 opacity: 0.8,
        //                 fillColor: '#c5cae9',
        //                 fillOpacity: 0.3
        //             }).addTo(drawingLayer);
        //
        //             // Add room label
        //             const center = polygon.getBounds().getCenter();
        //             L.marker(center, {
        //                 icon: L.divIcon({
        //                     className: 'room-label',
        //                     html: `<div style="background-color: rgba(255,255,255,0.7); padding: 3px; border-radius: 3px; font-size: 12px;">${room.name}</div>`,
        //                     iconSize: [100, 20],
        //                     iconAnchor: [50, 10]
        //                 })
        //             }).addTo(drawingLayer);
        //         });
        //     }
        //
        //     // Draw doors
        //     if (floor.doors && floor.doors.length > 0) {
        //         floor.doors.forEach(door => {
        //             // Scale door coordinates to pixels
        //             const x = door.coords[0] * floorPlanSettings.pixelsPerMeter;
        //             const y = door.coords[1] * floorPlanSettings.pixelsPerMeter;
        //
        //             // Draw door icon
        //             const doorIcon = door.leadsTo === 'outside' ? 'mdi-door' : 'mdi-door-open';
        //             const marker = L.marker([y, x], {
        //                 icon: L.divIcon({
        //                     className: 'door-icon',
        //                     html: `<i class="${doorIcon}" style="font-size: 24px; color: #795548;"></i>`,
        //                     iconSize: [24, 24],
        //                     iconAnchor: [12, 12]
        //                 })
        //             }).addTo(drawingLayer);
        //
        //             marker.bindTooltip(door.name);
        //         });
        //     }
        // }
        //
        // function handleIndoorMapClick(e) {
        //     if (mapMode.value !== 'edit' || !floorToolActive.value) return;
        //
        //     // Get click coordinates in floor plan units
        //     const pixelX = e.latlng.lng;
        //     const pixelY = e.latlng.lat;
        //
        //     // Convert to floor plan coordinates (meters)
        //     let x = pixelX / floorPlanSettings.pixelsPerMeter;
        //     let y = pixelY / floorPlanSettings.pixelsPerMeter;
        //
        //     // Snap to grid if enabled
        //     if (floorPlanSettings.snapToGrid) {
        //         x = Math.round(x / floorPlanSettings.gridSize) * floorPlanSettings.gridSize;
        //         y = Math.round(y / floorPlanSettings.gridSize) * floorPlanSettings.gridSize;
        //     }
        //
        //     if (floorToolActive.value === 'room' || floorToolActive.value === 'wall') {
        //         // Add point to drawing
        //         tempDrawingPoints.value.push([x, y]);
        //         drawTempIndoorShape();
        //     }
        //     else if (floorToolActive.value === 'door') {
        //         // Place door directly
        //         doorForm.id = `door_${Date.now()}`;
        //         doorForm.name = 'New Door';
        //         doorForm.coords = [x, y];
        //         doorForm.leadsTo = 'outside';
        //
        //         doorDialog.value = true;
        //     }
        //     else if (floorToolActive.value === 'stairs') {
        //         // Place stairs directly
        //         doorForm.id = `stairs_${Date.now()}`;
        //         doorForm.name = 'Stairs';
        //         doorForm.coords = [x, y];
        //
        //         // Get list of floors for this building
        //         const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //         if (building && building.floors.length > 1) {
        //             // Default to connection with adjacent floor
        //             const currentFloor = building.floors.find(f => f.id === activeFloorId.value);
        //             const currentLevel = currentFloor ? currentFloor.level : 0;
        //
        //             // Find adjacent floors
        //             const higherFloor = building.floors.find(f => f.level === currentLevel + 1);
        //             const lowerFloor = building.floors.find(f => f.level === currentLevel - 1);
        //
        //             // Set default connection
        //             doorForm.leadsTo = higherFloor ? higherFloor.id : (lowerFloor ? lowerFloor.id : 'outside');
        //
        //             doorDialog.value = true;
        //         } else {
        //             showSnackbar('Need at least two floors to create stairs', 'error');
        //         }
        //     }
        // }
        //
        // function drawTempIndoorShape() {
        //     if (!map) return;
        //
        //     // Clear existing drawing
        //     if (drawingLayer) {
        //         drawingLayer.clearLayers();
        //     }
        //
        //     if (tempDrawingPoints.value.length === 0) {
        //         drawFloorPlan();
        //         return;
        //     }
        //
        //     // Scale points to pixels
        //     const scaledPoints = tempDrawingPoints.value.map(point => [
        //         point[1] * floorPlanSettings.pixelsPerMeter,
        //         point[0] * floorPlanSettings.pixelsPerMeter
        //     ]);
        //
        //     // Draw the shape based on the active tool
        //     if (floorToolActive.value === 'room') {
        //         if (scaledPoints.length >= 3) {
        //             L.polygon(scaledPoints, {
        //                 color: '#4caf50',
        //                 weight: 2,
        //                 opacity: 0.8,
        //                 fillColor: '#a5d6a7',
        //                 fillOpacity: 0.4,
        //                 dashArray: '5, 5'
        //             }).addTo(drawingLayer);
        //         } else if (scaledPoints.length === 2) {
        //             L.polyline(scaledPoints, {
        //                 color: '#4caf50',
        //                 weight: 2,
        //                 opacity: 0.8,
        //                 dashArray: '5, 5'
        //             }).addTo(drawingLayer);
        //         }
        //     }
        //     else if (floorToolActive.value === 'wall') {
        //         L.polyline(scaledPoints, {
        //             color: '#000',
        //             weight: 3,
        //             opacity: 0.8,
        //             dashArray: '5, 5'
        //         }).addTo(drawingLayer);
        //     }
        //
        //     // Draw vertices
        //     scaledPoints.forEach((point, index) => {
        //         L.circleMarker(point, {
        //             radius: 4,
        //             color: '#f44336',
        //             fillColor: '#fff',
        //             fillOpacity: 1,
        //             weight: 2
        //         }).addTo(drawingLayer);
        //     });
        //
        //     // Redraw the existing floor elements
        //     drawFloorPlan();
        // }
        //
        // function handleFloorDrawingComplete() {
        //     if (!floorToolActive.value || tempDrawingPoints.value.length < 3) return;
        //
        //     if (floorToolActive.value === 'room') {
        //         // Prepare room form data
        //         roomForm.id = `room_${Date.now()}`;
        //         roomForm.name = 'New Room';
        //         roomForm.type = 'room';
        //         roomForm.description = '';
        //         roomForm.coords = [...tempDrawingPoints.value];
        //
        //         // Show room dialog
        //         roomDialog.value = true;
        //     }
        //     else if (floorToolActive.value === 'wall') {
        //         // Add the wall directly to the floor
        //         const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //         if (building) {
        //             const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
        //             if (floorIndex >= 0) {
        //                 if (!building.floors[floorIndex].walls) {
        //                     building.floors[floorIndex].walls = [];
        //                 }
        //
        //                 // Add the wall
        //                 building.floors[floorIndex].walls.push([...tempDrawingPoints.value]);
        //
        //                 // Reset drawing state
        //                 tempDrawingPoints.value = [];
        //
        //                 // Redraw floor plan
        //                 drawFloorPlan();
        //
        //                 showSnackbar('Wall added', 'success');
        //             }
        //         }
        //     }
        // }

        // function startFloorTool(tool) {
        //     floorToolActive.value = tool;
        //     tempDrawingPoints.value = [];
        //
        //     // Clear any existing drawing
        //     if (drawingLayer) {
        //         drawingLayer.clearLayers();
        //     }
        //
        //     // Redraw floor plan
        //     drawFloorPlan();
        //
        //     logDebug(`Started floor tool: ${tool}`);
        // }
        //
        // function saveFloorSettings() {
        //     // Apply to current floor
        //     if (activeBuildingId.value && activeFloorId.value) {
        //         const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //         if (building) {
        //             const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
        //             if (floorIndex >= 0) {
        //                 // Update floor name and level
        //                 building.floors[floorIndex].name = floorForm.name;
        //                 building.floors[floorIndex].level = parseInt(floorForm.level);
        //             }
        //         }
        //     }
        //
        //     // Redraw the floor plan
        //     initIndoorMap();
        //
        //     floorSettingsDialog.value = false;
        //     showSnackbar('Floor settings updated', 'success');
        // }

        function startFloorTool(tool) {
            logDebug(`Starting floor tool: ${tool}`);

            floorToolActive.value = tool;
            tempDrawingPoints.value = [];

            // Clear any existing drawing
            if (drawingLayer) {
                drawingLayer.clearLayers();
                drawFloorPlan();
            }

            showSnackbar(`${tool} tool activated - click on the floor plan`, 'info');
        }

        function saveFloorSettings() {
            // Apply to current floor
            if (activeBuildingId.value && activeFloorId.value) {
                const building = buildings.value.find(b => b.id === activeBuildingId.value);
                if (building) {
                    const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
                    if (floorIndex >= 0) {
                        // Update floor name and level
                        building.floors[floorIndex].name = floorForm.name;
                        building.floors[floorIndex].level = parseInt(floorForm.level);
                    }
                }
            }

            // Redraw the floor plan
            initIndoorMap();

            floorSettingsDialog.value = false;
            showSnackbar('Floor settings updated', 'success');
        }


        // function saveRoom() {
        //     if (!roomForm.id || !roomForm.name) {
        //         showSnackbar('Room ID and name are required', 'error');
        //         return;
        //     }
        //
        //     // Add room to the current floor
        //     const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //     if (building) {
        //         const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
        //         if (floorIndex >= 0) {
        //             if (!building.floors[floorIndex].rooms) {
        //                 building.floors[floorIndex].rooms = [];
        //             }
        //
        //             // Create room data
        //             const roomData = {
        //                 id: roomForm.id,
        //                 name: roomForm.name,
        //                 type: roomForm.type,
        //                 description: roomForm.description,
        //                 coords: [...tempDrawingPoints.value]
        //             };
        //
        //             // Check if we're editing
        //             if (editingIndex.value !== null) {
        //                 building.floors[floorIndex].rooms[editingIndex.value] = roomData;
        //             } else {
        //                 building.floors[floorIndex].rooms.push(roomData);
        //             }
        //
        //             // Reset state
        //             editingIndex.value = null;
        //             tempDrawingPoints.value = [];
        //             floorToolActive.value = null;
        //
        //             // Redraw floor plan
        //             drawFloorPlan();
        //
        //             roomDialog.value = false;
        //             showSnackbar('Room saved', 'success');
        //         }
        //     }
        // }
        //
        // function saveDoor() {
        //     if (!doorForm.id || !doorForm.name || !doorForm.leadsTo) {
        //         showSnackbar('Door ID, name, and destination are required', 'error');
        //         return;
        //     }
        //
        //     // Add door to the current floor
        //     const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //     if (building) {
        //         const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
        //         if (floorIndex >= 0) {
        //             if (!building.floors[floorIndex].doors) {
        //                 building.floors[floorIndex].doors = [];
        //             }
        //
        //             // Create door data
        //             const doorData = {
        //                 id: doorForm.id,
        //                 name: doorForm.name,
        //                 coords: doorForm.coords,
        //                 leadsTo: doorForm.leadsTo
        //             };
        //
        //             // Check if we're editing
        //             if (editingIndex.value !== null) {
        //                 building.floors[floorIndex].doors[editingIndex.value] = doorData;
        //             } else {
        //                 building.floors[floorIndex].doors.push(doorData);
        //             }
        //
        //             // Reset state
        //             editingIndex.value = null;
        //             floorToolActive.value = null;
        //
        //             // Redraw floor plan
        //             drawFloorPlan();
        //
        //             doorDialog.value = false;
        //             showSnackbar('Door saved', 'success');
        //         }
        //     }
        // }
        //
        // function getDoorDestinations() {
        //     const destinations = [{
        //         title: 'Outside (Exit Building)',
        //         value: 'outside'
        //     }];
        //
        //     // Add all floors in this building
        //     const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //     if (building && building.floors) {
        //         building.floors.forEach(floor => {
        //             if (floor.id !== activeFloorId.value) {
        //                 destinations.push({
        //                     title: `${floor.name} (Level ${floor.level})`,
        //                     value: floor.id
        //                 });
        //             }
        //         });
        //     }
        //
        //     return destinations;
        // }
        //
        // function addNewFloor() {
        //     // Reset the form
        //     floorForm.id = `floor_${Date.now()}`;
        //     floorForm.name = 'New Floor';
        //
        //     // Set level based on current floors
        //     const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //     if (building && building.floors) {
        //         // Find highest floor level
        //         const maxLevel = Math.max(...building.floors.map(f => f.level));
        //         floorForm.level = maxLevel + 1;
        //     } else {
        //         floorForm.level = 0;
        //     }
        //
        //     newFloorDialog.value = true;
        // }
        //
        // function saveNewFloor() {
        //     if (!floorForm.id || !floorForm.name) {
        //         showSnackbar('Floor ID and name are required', 'error');
        //         return;
        //     }
        //
        //     // Add floor to building
        //     const building = buildings.value.find(b => b.id === activeBuildingId.value);
        //     if (building) {
        //         const newFloor = {
        //             id: floorForm.id,
        //             name: floorForm.name,
        //             level: parseInt(floorForm.level),
        //             backgroundImage: null,
        //             walls: [],
        //             rooms: [],
        //             doors: []
        //         };
        //
        //         // Add to building
        //         building.floors.push(newFloor);
        //
        //         // Switch to the new floor
        //         activeFloorId.value = newFloor.id;
        //
        //         // Reinitialize floor plan
        //         initIndoorMap();
        //
        //         newFloorDialog.value = false;
        //         showSnackbar('New floor added', 'success');
        //     }
        // }


        function saveRoom() {
            if (!roomForm.id || !roomForm.name) {
                showSnackbar('Room ID and name are required', 'error');
                return;
            }

            // Add room to the current floor
            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            if (building) {
                const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
                if (floorIndex >= 0) {
                    if (!building.floors[floorIndex].rooms) {
                        building.floors[floorIndex].rooms = [];
                    }

                    // Create room data
                    const roomData = {
                        id: roomForm.id,
                        name: roomForm.name,
                        type: roomForm.type,
                        description: roomForm.description,
                        coords: [...tempDrawingPoints.value]
                    };

                    // Check if we're editing
                    if (editingIndex.value !== null) {
                        building.floors[floorIndex].rooms[editingIndex.value] = roomData;
                    } else {
                        building.floors[floorIndex].rooms.push(roomData);
                    }

                    // Reset state
                    editingIndex.value = null;
                    tempDrawingPoints.value = [];
                    floorToolActive.value = null;

                    // Redraw floor plan
                    drawFloorPlan();

                    roomDialog.value = false;
                    showSnackbar('Room saved', 'success');
                }
            }
        }

        function saveDoor() {
            if (!doorForm.id || !doorForm.name || !doorForm.leadsTo) {
                showSnackbar('Door ID, name, and destination are required', 'error');
                return;
            }

            // Add door to the current floor
            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            if (building) {
                const floorIndex = building.floors.findIndex(f => f.id === activeFloorId.value);
                if (floorIndex >= 0) {
                    if (!building.floors[floorIndex].doors) {
                        building.floors[floorIndex].doors = [];
                    }

                    // Create door data
                    const doorData = {
                        id: doorForm.id,
                        name: doorForm.name,
                        coords: doorForm.coords,
                        leadsTo: doorForm.leadsTo
                    };

                    // Check if we're editing
                    if (editingIndex.value !== null) {
                        building.floors[floorIndex].doors[editingIndex.value] = doorData;
                    } else {
                        building.floors[floorIndex].doors.push(doorData);
                    }

                    // Reset state
                    editingIndex.value = null;
                    floorToolActive.value = null;

                    // Redraw floor plan
                    drawFloorPlan();

                    doorDialog.value = false;
                    showSnackbar('Door saved', 'success');
                }
            }
        }

        function getDoorDestinations() {
            const destinations = [{
                title: 'Outside (Exit Building)',
                value: 'outside'
            }];

            // Add all floors in this building
            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            if (building && building.floors) {
                building.floors.forEach(floor => {
                    if (floor.id !== activeFloorId.value) {
                        destinations.push({
                            title: `${floor.name} (Level ${floor.level})`,
                            value: floor.id
                        });
                    }
                });
            }

            return destinations;
        }

        function addNewFloor() {
            // Reset the form
            floorForm.id = `floor_${Date.now()}`;
            floorForm.name = 'New Floor';

            // Set level based on current floors
            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            if (building && building.floors) {
                // Find highest floor level
                const maxLevel = Math.max(...building.floors.map(f => f.level));
                floorForm.level = maxLevel + 1;
            } else {
                floorForm.level = 0;
            }

            newFloorDialog.value = true;
        }

        function saveNewFloor() {
            if (!floorForm.id || !floorForm.name) {
                showSnackbar('Floor ID and name are required', 'error');
                return;
            }

            // Add floor to building
            const building = buildings.value.find(b => b.id === activeBuildingId.value);
            if (building) {
                const newFloor = {
                    id: floorForm.id,
                    name: floorForm.name,
                    level: parseInt(floorForm.level),
                    backgroundImage: null,
                    walls: [],
                    rooms: [],
                    doors: []
                };

                // Add to building
                building.floors.push(newFloor);

                // Switch to the new floor
                activeFloorId.value = newFloor.id;

                // Reinitialize floor plan
                initIndoorMap();

                newFloorDialog.value = false;
                showSnackbar('New floor added', 'success');
            }
        }

        function startTool(tool, decoType = null) {
            mapMode.value = 'edit';
            activeTool.value = tool;
            activeDecoType.value = decoType;
            editingIndex.value = null;

            // Reset floor tool if active
            floorToolActive.value = null;

            // Clear any previous drawing
            resetDrawing();

            // Initialize the appropriate form if needed
            if (tool === 'location') {
                locationData.id = `loc_${Date.now()}`;
                locationData.name = 'New Location';
                locationData.description = '';
                locationData.lat = mapSettings.centerLat;
                locationData.lng = mapSettings.centerLng;
            } else if (tool === 'road') {
                roadForm.name = 'New Road';
                roadForm.type = 'main';
                roadForm.color = '#8B4513';
                roadForm.weight = 3;
                roadForm.opacity = 0.8;
                roadForm.isDashed = false;
                roadForm.dashArray = '';

                // Open the road edit form
                activeEditForm.value = 'road';
            } else if (tool === 'region') {
                regionForm.name = 'New Region';
                regionForm.color = '#4a2c82';
                regionForm.fillOpacity = 0.2;

                // Open the region edit form
                activeEditForm.value = 'region';
            } else if (tool === 'building') {
                // Initialize building form
                buildingForm.id = `building_${Date.now()}`;
                buildingForm.name = 'New Building';
                buildingForm.description = '';
                buildingForm.icon = 'mdi-home';
                buildingForm.coords = [mapSettings.centerLat, mapSettings.centerLng];

                // Show building dialog
                //buildingDialog.value = true;
            } else {
                // Reset the edit form
                activeEditForm.value = null;
            }

            logDebug(`Started ${tool} tool ${decoType ? 'with ' + decoType : ''}`);
        }

        function editBuilding(index) {
            if (index < 0 || index >= buildings.value.length) {
                showSnackbar('Building not found', 'error');
                return;
            }

            editingIndex.value = index;
            const building = buildings.value[index];

            buildingForm.id = building.id;
            buildingForm.name = building.name;
            buildingForm.description = building.description || '';
            buildingForm.coords = [...building.coords];
            buildingForm.icon = building.icon || 'mdi-home';

            buildingDialog.value = true;
        }

        function deleteBuilding(index) {
            if (index < 0 || index >= buildings.value.length) {
                showSnackbar('Building not found', 'error');
                return;
            }

            const building = buildings.value[index];

            if (confirm(`Are you sure you want to delete "${building.name}"?`)) {
                // Remove building
                buildings.value.splice(index, 1);

                // If we're currently in this building, exit
                if (activeBuildingId.value === building.id) {
                    exitBuilding();
                }

                showSnackbar('Building deleted', 'success');
            }
        }

        function saveBuilding() {
            if (!buildingForm.id || !buildingForm.name) {
                showSnackbar('Building ID and name are required', 'error');
                return;
            }

            // Create building data
            const buildingData = {
                id: buildingForm.id,
                name: buildingForm.name,
                description: buildingForm.description,
                coords: [
                    parseFloat(buildingForm.coords[0]),
                    parseFloat(buildingForm.coords[1])
                ],
                icon: buildingForm.icon,
                floors: []
            };

            // Check if we're editing
            if (editingIndex.value !== null) {
                // Keep the existing floors
                buildingData.floors = buildings.value[editingIndex.value].floors || [];

                // Update the building
                buildings.value[editingIndex.value] = buildingData;
            } else {
                // Add default ground floor
                buildingData.floors = [
                    {
                        id: `floor_${Date.now()}`,
                        name: 'Ground Floor',
                        level: 0,
                        backgroundImage: null,
                        walls: [],
                        rooms: [],
                        doors: []
                    }
                ];

                // Add new building
                buildings.value.push(buildingData);
            }

            // Reset state
            editingIndex.value = null;
            buildingDialog.value = false;

            showSnackbar('Building saved', 'success');
        }

        return {
            // State
            activePanel,
            mapMode,
            activeTool,
            tempDrawingPoints,
            editingIndex,
            selectedDecoration,
            activeEditForm,

            // Building/Floor state
            activeBuildingId,
            activeFloorId,
            floorToolActive,
            floorPlanSettings,

            // Data
            mapSettings,
            locations,
            roads,
            regions,
            decorations,
            buildings,
            decorationIcons,
            roadColorSwatches,
            regionColorSwatches,

            // Forms
            locationData,
            roadForm,
            regionForm,
            buildingForm,
            floorForm,
            roomForm,
            doorForm,

            // Dialogs
            importDialog,
            exportDialog,
            buildingDialog,
            floorSettingsDialog,
            roomDialog,
            doorDialog,
            newFloorDialog,
            importData,
            exportData,
            snackbar,

            // Methods - General
            applyMapSettings,
            startTool,
            resetDrawing,
            removeLastPoint,
            closeEditForm,
            getEditFormTitle,
            saveCurrentForm,

            // Methods - Map Elements
            editLocation,
            deleteLocation,
            saveLocation,
            editRoad,
            deleteRoad,
            saveRoad,
            editRegion,
            deleteRegion,
            saveRegion,
            deleteDecoration,

            // Methods - Buildings
            enterBuilding,
            exitBuilding,
            editBuilding,
            deleteBuilding,
            saveBuilding,
            getActiveBuildingName,
            getBuildingFloors,

            // Methods - Floor Plans
            startFloorTool,
            saveFloorSettings,
            saveRoom,
            saveDoor,
            getDoorDestinations,
            addNewFloor,
            saveNewFloor,

            // Methods - Import/Export
            exportMapData,
            copyExportData,
            importMapData
        };
    }
});
</script>

<style scoped>
.map-container {
    width: 100%;
    height: 600px;
    border-radius: 4px;
    overflow: hidden;
    z-index: 1;
}

.color-box {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #ccc;
}

.preview-card, .edit-form-card {
    height: 100%;
}

.preview-content {
    height: calc(100% - 64px);
    overflow-y: auto;
}

/* Fix for Leaflet controls */
:deep(.leaflet-control-zoom) {
    margin: 10px;
}

:deep(.leaflet-control-attribution) {
    font-size: 10px;
    background-color: rgba(255, 255, 255, 0.6);
}
</style>
