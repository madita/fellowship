import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useSettingsStore } from '@/store/settingStore';

/**
 * Composable for handling lazy loading of images and other elements
 */
export function useLazyLoading() {
    const settingsStore = useSettingsStore();
    const observer = ref(null);
    const loadedElements = ref(new Set());

    /**
     * Check if lazy loading is enabled based on settings
     */
    const isEnabled = computed(() => {
        return settingsStore.lazyLoadingEnabled;
    });

    /**
     * Get the loading attribute value
     */
    const loadingAttribute = computed(() => {
        return isEnabled.value ? 'lazy' : 'eager';
    });

    /**
     * Create an intersection observer for custom lazy loading
     */
    function createObserver(options = {}) {
        if (typeof IntersectionObserver === 'undefined') {
            return null;
        }

        const defaultOptions = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1,
        };

        return new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    loadElement(element);
                    observer.value?.unobserve(element);
                }
            });
        }, { ...defaultOptions, ...options });
    }

    /**
     * Load an element (swap data-src to src)
     */
    function loadElement(element) {
        const dataSrc = element.getAttribute('data-src');
        const dataSrcset = element.getAttribute('data-srcset');
        const dataBackgroundImage = element.getAttribute('data-background-image');

        if (dataSrc) {
            element.src = dataSrc;
            element.removeAttribute('data-src');
        }

        if (dataSrcset) {
            element.srcset = dataSrcset;
            element.removeAttribute('data-srcset');
        }

        if (dataBackgroundImage) {
            element.style.backgroundImage = `url(${dataBackgroundImage})`;
            element.removeAttribute('data-background-image');
        }

        element.classList.remove('lazy');
        element.classList.add('lazy-loaded');
        loadedElements.value.add(element);
    }

    /**
     * Observe an element for lazy loading
     */
    function observe(element) {
        if (!isEnabled.value) {
            // If lazy loading is disabled, load immediately
            loadElement(element);
            return;
        }

        if (!observer.value) {
            observer.value = createObserver();
        }

        if (observer.value) {
            observer.value.observe(element);
        } else {
            // Fallback for browsers without IntersectionObserver
            loadElement(element);
        }
    }

    /**
     * Stop observing an element
     */
    function unobserve(element) {
        observer.value?.unobserve(element);
    }

    /**
     * Initialize lazy loading for all elements with .lazy class
     */
    function initializeLazyElements(container = document) {
        const lazyElements = container.querySelectorAll('.lazy[data-src], .lazy[data-srcset], .lazy[data-background-image]');
        lazyElements.forEach((element) => {
            observe(element);
        });
    }

    /**
     * Cleanup observer
     */
    function cleanup() {
        if (observer.value) {
            observer.value.disconnect();
            observer.value = null;
        }
        loadedElements.value.clear();
    }

    /**
     * Get image props with lazy loading attributes
     */
    function getImageProps(src, alt = '', options = {}) {
        const props = {
            alt,
            ...options,
        };

        if (isEnabled.value) {
            props.loading = 'lazy';
            props.src = src;
        } else {
            props.loading = 'eager';
            props.src = src;
        }

        return props;
    }

    return {
        isEnabled,
        loadingAttribute,
        observe,
        unobserve,
        initializeLazyElements,
        cleanup,
        getImageProps,
        loadElement,
    };
}

/**
 * Vue directive for lazy loading
 * Usage: v-lazy="imageSrc" or v-lazy:background="imageUrl"
 */
const lazyObserver = typeof IntersectionObserver !== 'undefined'
    ? new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const dataSrc = el.getAttribute('data-src');
                const dataBackgroundImage = el.getAttribute('data-background-image');

                if (dataSrc) {
                    el.src = dataSrc;
                    el.removeAttribute('data-src');
                }

                if (dataBackgroundImage) {
                    el.style.backgroundImage = `url(${dataBackgroundImage})`;
                    el.removeAttribute('data-background-image');
                }

                el.classList.remove('lazy');
                el.classList.add('lazy-loaded');
                lazyObserver.unobserve(el);
            }
        });
    }, { rootMargin: '50px', threshold: 0.1 })
    : null;

export const vLazy = {
    mounted(el, binding) {
        if (binding.arg === 'background') {
            // Background image lazy loading
            el.setAttribute('data-background-image', binding.value);
            el.classList.add('lazy');
        } else {
            // Regular image lazy loading
            if (el.tagName === 'IMG') {
                el.setAttribute('data-src', binding.value);
                el.classList.add('lazy');
                // Set a placeholder or low-res image
                el.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
            }
        }

        if (lazyObserver) {
            lazyObserver.observe(el);
        } else {
            // Fallback: load immediately if IntersectionObserver not supported
            if (el.tagName === 'IMG') {
                el.src = binding.value;
            } else if (binding.arg === 'background') {
                el.style.backgroundImage = `url(${binding.value})`;
            }
        }
    },
    updated(el, binding) {
        if (binding.value !== binding.oldValue) {
            if (binding.arg === 'background') {
                el.style.backgroundImage = `url(${binding.value})`;
            } else if (el.tagName === 'IMG') {
                el.src = binding.value;
            }
        }
    },
    unmounted(el) {
        if (lazyObserver) {
            lazyObserver.unobserve(el);
        }
    },
};

export default useLazyLoading;
