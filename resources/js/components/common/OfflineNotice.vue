    <template>
    <v-snackbar
        v-model="isOffline"
        :timeout="-1"
        color="error"
        location="top"
        multi-line
    >
        <div class="d-flex align-center">
            <v-icon class="mr-2">mdi-wifi-off</v-icon>
            <div>
                <div class="font-weight-medium">{{ $t('offline.youreOffline') }}</div>
                <div class="text-caption">{{ $t('offline.someFeatures') }}</div>
            </div>
        </div>

        <template #actions>
            <v-btn
                variant="text"
                @click="checkConnection"
                :loading="checking"
            >
                {{ $t('common.retry') }}
            </v-btn>
        </template>
    </v-snackbar>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const isOffline = ref(false);
const checking = ref(false);

let onlineHandler = null;
let offlineHandler = null;

onMounted(() => {
    // Set initial state
    isOffline.value = !navigator.onLine;

    // Listen for online/offline events
    onlineHandler = () => {
        isOffline.value = false;
        console.log('App is online');
    };

    offlineHandler = () => {
        isOffline.value = true;
        console.log('App is offline');
    };

    window.addEventListener('online', onlineHandler);
    window.addEventListener('offline', offlineHandler);
});

onUnmounted(() => {
    if (onlineHandler) {
        window.removeEventListener('online', onlineHandler);
    }
    if (offlineHandler) {
        window.removeEventListener('offline', offlineHandler);
    }
});

async function checkConnection() {
    checking.value = true;

    try {
        // Try to fetch a small resource
        const response = await fetch('/favicon.ico', {
            method: 'HEAD',
            cache: 'no-cache',
        });

        if (response.ok) {
            isOffline.value = false;
        }
    } catch (error) {
        console.log('Still offline');
    } finally {
        checking.value = false;
    }
}
</script>
