import { ref, onMounted } from 'vue';

export function usePWA() {
    const isInstallable = ref(false);
    const isInstalled = ref(false);
    const deferredPrompt = ref(null);
    const updateAvailable = ref(false);
    const registration = ref(null);

    // Register service worker
    const registerServiceWorker = async () => {
        if ('serviceWorker' in navigator) {
            try {
                registration.value = await navigator.serviceWorker.register('/service-worker.js', {
                    scope: '/',
                });

                console.log('Service Worker registered successfully:', registration.value);

                // Check for updates
                registration.value.addEventListener('updatefound', () => {
                    const newWorker = registration.value.installing;

                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // New service worker available
                            updateAvailable.value = true;
                            console.log('New service worker available');
                        }
                    });
                });

                // Check if already installed
                if (registration.value.active) {
                    checkIfInstalled();
                }
            } catch (error) {
                console.error('Service Worker registration failed:', error);
            }
        }
    };

    // Check if app is installed
    const checkIfInstalled = () => {
        // Check if running in standalone mode (installed PWA)
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            isInstalled.value = true;
        }
    };

    // Listen for beforeinstallprompt event
    const setupInstallPrompt = () => {
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent the mini-infobar from appearing on mobile
            e.preventDefault();
            // Stash the event so it can be triggered later
            deferredPrompt.value = e;
            // Update UI to show install button
            isInstallable.value = true;

            console.log('PWA install prompt available');
        });

        window.addEventListener('appinstalled', () => {
            // Hide the install promotion
            isInstallable.value = false;
            isInstalled.value = true;
            deferredPrompt.value = null;

            console.log('PWA was installed');
        });
    };

    // Trigger install prompt
    const promptInstall = async () => {
        if (!deferredPrompt.value) {
            console.log('No install prompt available');
            return false;
        }

        // Show the install prompt
        deferredPrompt.value.prompt();

        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.value.userChoice;

        if (outcome === 'accepted') {
            console.log('User accepted the install prompt');
            isInstallable.value = false;
        } else {
            console.log('User dismissed the install prompt');
        }

        // Clear the deferredPrompt
        deferredPrompt.value = null;

        return outcome === 'accepted';
    };

    // Update service worker
    const updateServiceWorker = () => {
        if (!registration.value || !registration.value.waiting) {
            return;
        }

        // Send message to service worker to skip waiting
        registration.value.waiting.postMessage({ type: 'SKIP_WAITING' });

        // Reload the page when the new service worker takes control
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            window.location.reload();
        });
    };

    // Clear cache
    const clearCache = async () => {
        if ('caches' in window) {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map(name => caches.delete(name)));
            console.log('All caches cleared');
        }

        if (registration.value && registration.value.active) {
            registration.value.active.postMessage({ type: 'CLEAR_CACHE' });
        }
    };

    // Initialize PWA on mount
    onMounted(() => {
        registerServiceWorker();
        setupInstallPrompt();
        checkIfInstalled();
    });

    return {
        isInstallable,
        isInstalled,
        updateAvailable,
        promptInstall,
        updateServiceWorker,
        clearCache,
    };
}
