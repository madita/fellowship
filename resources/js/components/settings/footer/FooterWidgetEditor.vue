<template>
  <v-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" max-width="800px" scrollable persistent>
    <v-card v-if="widget">
      <v-card-title class="d-flex align-center bg-primary text-white">
        <v-icon class="mr-2">{{ widgetIcon }}</v-icon>
        Edit {{ widgetDefinition?.name || widget.type }}
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" @click="cancel"></v-btn>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="pa-6" style="max-height: 600px; overflow-y: auto;">
        <!-- Widget Type Display -->
        <v-alert type="info" variant="tonal" density="compact" class="mb-4">
          <div class="d-flex align-center">
            <v-icon class="mr-2">{{ widgetIcon }}</v-icon>
            <div>
              <div class="font-weight-bold">{{ widgetDefinition?.name }}</div>
              <div class="text-caption">{{ widgetDefinition?.description }}</div>
            </div>
          </div>
        </v-alert>

        <!-- Quicklinks Widget -->
        <template v-if="editedWidget.type === 'quicklinks'">
          <v-text-field
            v-model="editedWidget.config.title"
            label="Section Title"
            hint="Title displayed above the links"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <div class="text-subtitle-2 mb-3">Links</div>
          <div v-if="editedWidget.config.links && editedWidget.config.links.length > 0" class="mb-3">
            <v-card
              v-for="(link, idx) in editedWidget.config.links"
              :key="idx"
              variant="outlined"
              class="mb-2"
            >
              <v-card-text class="d-flex align-center gap-2 pa-3">
                <div class="flex-grow-1">
                  <div class="font-weight-medium">{{ link.label }}</div>
                  <div class="text-caption text-grey">{{ link.url }}</div>
                  <v-chip v-if="link.authOnly" size="x-small" color="warning" class="mt-1">Auth Only</v-chip>
                </div>
                <v-btn icon size="small" variant="text" @click="editQuicklink(idx)">
                  <v-icon size="small">mdi-pencil</v-icon>
                </v-btn>
                <v-btn icon size="small" variant="text" color="error" @click="deleteQuicklink(idx)">
                  <v-icon size="small">mdi-delete</v-icon>
                </v-btn>
              </v-card-text>
            </v-card>
          </div>
          <v-btn prepend-icon="mdi-plus" @click="showQuicklinkDialog = true">
            Add Link
          </v-btn>
        </template>

        <!-- Contact Widget -->
        <template v-if="editedWidget.type === 'contact'">
          <v-text-field
            v-model="editedWidget.config.title"
            label="Section Title"
            hint="Title displayed above contact information"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <div class="text-subtitle-2 mb-3">Display Options</div>
          <v-switch
            v-model="editedWidget.config.showAddress"
            label="Show Address"
            color="primary"
            hide-details
            class="mb-2"
          ></v-switch>
          <v-switch
            v-model="editedWidget.config.showPhone"
            label="Show Phone"
            color="primary"
            hide-details
            class="mb-2"
          ></v-switch>
          <v-switch
            v-model="editedWidget.config.showEmail"
            label="Show Email"
            color="primary"
            hide-details
          ></v-switch>

          <v-alert type="info" variant="tonal" class="mt-4">
            <div class="text-caption">
              Contact information is pulled from your global settings (Contact Address, Phone, Email).
              Go to Settings → General to update these values.
            </div>
          </v-alert>
        </template>

        <!-- Newsletter Widget -->
        <template v-if="editedWidget.type === 'newsletter'">
          <v-text-field
            v-model="editedWidget.config.title"
            label="Section Title"
            hint="Title displayed above the newsletter form"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <v-textarea
            v-model="editedWidget.config.description"
            label="Description"
            hint="Short description encouraging users to subscribe"
            persistent-hint
            rows="2"
            class="mb-4"
          ></v-textarea>

          <v-text-field
            v-model="editedWidget.config.buttonText"
            label="Button Text"
            hint="Text displayed on the subscribe button"
            persistent-hint
          ></v-text-field>

          <v-alert type="info" variant="tonal" class="mt-4">
            <div class="text-caption">
              Newsletter integration settings can be configured in Settings → Advanced.
              Make sure to enable and configure your newsletter provider (Mailchimp, MailerLite, etc.).
            </div>
          </v-alert>
        </template>

        <!-- Social Media Widget -->
        <template v-if="editedWidget.type === 'social'">
          <v-text-field
            v-model="editedWidget.config.title"
            label="Section Title"
            hint="Title displayed above social media links"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <div class="text-subtitle-2 mb-3">Display Options</div>
          <v-switch
            v-model="editedWidget.config.showTwitter"
            label="Show Twitter/X"
            color="primary"
            hide-details
            class="mb-2"
          ></v-switch>
          <v-switch
            v-model="editedWidget.config.showFacebook"
            label="Show Facebook"
            color="primary"
            hide-details
            class="mb-2"
          ></v-switch>
          <v-switch
            v-model="editedWidget.config.showInstagram"
            label="Show Instagram"
            color="primary"
            hide-details
          ></v-switch>

          <v-alert type="info" variant="tonal" class="mt-4">
            <div class="text-caption">
              Social media URLs are pulled from your global settings.
              Go to Settings → General to update your social media links.
            </div>
          </v-alert>
        </template>

        <!-- Text Widget -->
        <template v-if="editedWidget.type === 'text'">
          <v-text-field
            v-model="editedWidget.config.title"
            label="Section Title (Optional)"
            hint="Leave empty for no title"
            persistent-hint
            class="mb-4"
          ></v-text-field>

          <v-textarea
            v-model="editedWidget.config.content"
            label="Content"
            hint="Text content for this footer section. Supports line breaks."
            persistent-hint
            rows="8"
            placeholder="Enter your custom text here..."
          ></v-textarea>
        </template>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4">
        <v-btn @click="cancel">Cancel</v-btn>
        <v-spacer></v-spacer>
        <v-btn color="primary" @click="save">
          <v-icon class="mr-1">mdi-content-save</v-icon>
          Save Changes
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Quicklink Dialog -->
    <v-dialog v-model="showQuicklinkDialog" max-width="500">
      <v-card>
        <v-card-title class="bg-primary text-white">
          {{ editingQuicklinkIndex !== null ? 'Edit Link' : 'Add Link' }}
        </v-card-title>

        <v-card-text class="pa-4">
          <v-text-field
            v-model="currentQuicklink.label"
            label="Link Label"
            hint="Display text for this link"
            persistent-hint
            class="mb-3"
          ></v-text-field>

          <v-text-field
            v-model="currentQuicklink.url"
            label="Link URL"
            hint="URL or path (e.g., /about or https://example.com)"
            persistent-hint
            class="mb-3"
            placeholder="/about or https://example.com"
          ></v-text-field>

          <v-switch
            v-model="currentQuicklink.external"
            label="Open in New Tab"
            color="primary"
            hide-details
            class="mb-2"
          ></v-switch>

          <v-switch
            v-model="currentQuicklink.authOnly"
            label="Visible Only to Signed-In Users"
            color="warning"
            hide-details
          ></v-switch>
        </v-card-text>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn @click="cancelQuicklinkEdit">Cancel</v-btn>
          <v-btn color="primary" @click="saveQuicklink">
            {{ editingQuicklinkIndex !== null ? 'Update' : 'Add' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { getWidgetDefinition } from '@/configs/footerWidgetTypes';

const props = defineProps({
  modelValue: Boolean,
  widget: Object
});

const emit = defineEmits(['update:modelValue', 'save']);

const editedWidget = ref(null);
const showQuicklinkDialog = ref(false);
const editingQuicklinkIndex = ref(null);
const currentQuicklink = ref({
  label: '',
  url: '',
  external: false,
  authOnly: false
});

const widgetDefinition = computed(() => {
  return props.widget ? getWidgetDefinition(props.widget.type) : null;
});

const widgetIcon = computed(() => widgetDefinition.value?.icon || 'mdi-widgets');

watch(() => props.widget, (newWidget) => {
  if (newWidget) {
    editedWidget.value = JSON.parse(JSON.stringify(newWidget));
    // Ensure config exists
    if (!editedWidget.value.config) {
      editedWidget.value.config = widgetDefinition.value?.defaultConfig || {};
    }
    // Ensure links array exists for quicklinks
    if (editedWidget.value.type === 'quicklinks' && !editedWidget.value.config.links) {
      editedWidget.value.config.links = [];
    }
  }
}, { immediate: true });

function editQuicklink(index) {
  editingQuicklinkIndex.value = index;
  currentQuicklink.value = { ...editedWidget.value.config.links[index] };
  showQuicklinkDialog.value = true;
}

function deleteQuicklink(index) {
  editedWidget.value.config.links.splice(index, 1);
}

function saveQuicklink() {
  if (!editedWidget.value.config.links) {
    editedWidget.value.config.links = [];
  }

  if (editingQuicklinkIndex.value !== null) {
    editedWidget.value.config.links[editingQuicklinkIndex.value] = { ...currentQuicklink.value };
  } else {
    editedWidget.value.config.links.push({ ...currentQuicklink.value });
  }

  cancelQuicklinkEdit();
}

function cancelQuicklinkEdit() {
  showQuicklinkDialog.value = false;
  editingQuicklinkIndex.value = null;
  currentQuicklink.value = {
    label: '',
    url: '',
    external: false,
    authOnly: false
  };
}

function save() {
  emit('save', editedWidget.value);
}

function cancel() {
  emit('update:modelValue', false);
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
