<template>
    <img
        ref="imageRef"
        :src="computedSrc"
        :alt="alt"
        :loading="loadingAttribute"
        :class="imageClasses"
        :style="imageStyle"
        @load="onLoad"
        @error="onError"
    />
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useSettingsStore } from '@/store/settingStore';

const props = defineProps({
    src: {
        type: String,
        required: true,
    },
    alt: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
    },
    fallback: {
        type: String,
        default: '/images/placeholder.png',
    },
    lazy: {
        type: Boolean,
        default: true, // Default to lazy, but respects global setting
    },
    fadeIn: {
        type: Boolean,
        default: true,
    },
    objectFit: {
        type: String,
        default: null,
        validator: (value) => ['contain', 'cover', 'fill', 'none', 'scale-down'].includes(value),
    },
    aspectRatio: {
        type: String,
        default: null,
    },
    width: {
        type: [String, Number],
        default: null,
    },
    height: {
        type: [String, Number],
        default: null,
    },
});

const emit = defineEmits(['load', 'error']);

const settingsStore = useSettingsStore();
const imageRef = ref(null);
const isLoaded = ref(false);
const hasError = ref(false);

/**
 * Check if lazy loading is enabled (global setting AND prop)
 */
const lazyLoadingEnabled = computed(() => {
    const globalEnabled = settingsStore.lazyLoadingEnabled;
    return globalEnabled && props.lazy;
});

/**
 * Get the loading attribute
 */
const loadingAttribute = computed(() => {
    return lazyLoadingEnabled.value ? 'lazy' : 'eager';
});

/**
 * Compute the actual src to use
 */
const computedSrc = computed(() => {
    if (hasError.value && props.fallback) {
        return props.fallback;
    }
    return props.src;
});

/**
 * Compute image classes
 */
const imageClasses = computed(() => {
    return {
        'app-image': true,
        'app-image--loading': !isLoaded.value && !hasError.value,
        'app-image--loaded': isLoaded.value,
        'app-image--error': hasError.value,
        'app-image--fade-in': props.fadeIn,
    };
});

/**
 * Compute image styles
 */
const imageStyle = computed(() => {
    const style = {};

    if (props.objectFit) {
        style.objectFit = props.objectFit;
    }

    if (props.aspectRatio) {
        style.aspectRatio = props.aspectRatio;
    }

    if (props.width) {
        style.width = typeof props.width === 'number' ? `${props.width}px` : props.width;
    }

    if (props.height) {
        style.height = typeof props.height === 'number' ? `${props.height}px` : props.height;
    }

    return style;
});

/**
 * Handle image load
 */
function onLoad(event) {
    isLoaded.value = true;
    emit('load', event);
}

/**
 * Handle image error
 */
function onError(event) {
    hasError.value = true;
    emit('error', event);
}

/**
 * Reset state when src changes
 */
watch(() => props.src, () => {
    isLoaded.value = false;
    hasError.value = false;
});
</script>

<style scoped>
.app-image {
    max-width: 100%;
    height: auto;
}

.app-image--fade-in {
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.app-image--fade-in.app-image--loaded {
    opacity: 1;
}

.app-image--loading {
    background-color: #f0f0f0;
}

.app-image--error {
    opacity: 0.5;
}
</style>
