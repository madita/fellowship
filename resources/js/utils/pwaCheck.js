/**
 * Check PWA installation eligibility
 */
export async function checkPWAStatus() {
    const status = {
        serviceWorkerSupported: 'serviceWorker' in navigator,
        serviceWorkerRegistered: false,
        manifestAccessible: false,
        isHTTPS: window.location.protocol === 'https:' || window.location.hostname === 'localhost',
        isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone,
    };

    // Check service worker registration
    if (status.serviceWorkerSupported) {
        try {
            const registration = await navigator.serviceWorker.getRegistration('/');
            status.serviceWorkerRegistered = !!registration;
            if (registration) {
                status.serviceWorkerState = registration.active?.state;
            }
        } catch (error) {
            console.error('Error checking service worker:', error);
        }
    }

    // Check manifest accessibility
    try {
        const response = await fetch('/manifest.json');
        status.manifestAccessible = response.ok;
        if (response.ok) {
            status.manifestData = await response.json();
        }
    } catch (error) {
        console.error('Error fetching manifest:', error);
    }

    return status;
}

/**
 * Log PWA status to console
 */
export async function logPWAStatus() {
    const status = await checkPWAStatus();

    console.group('🔍 PWA Status Check');
    console.log('✓ Service Worker Support:', status.serviceWorkerSupported ? '✅' : '❌');
    console.log('✓ Service Worker Registered:', status.serviceWorkerRegistered ? '✅' : '❌');
    if (status.serviceWorkerState) {
        console.log('  └─ State:', status.serviceWorkerState);
    }
    console.log('✓ Manifest Accessible:', status.manifestAccessible ? '✅' : '❌');
    console.log('✓ HTTPS/Localhost:', status.isHTTPS ? '✅' : '❌');
    console.log('✓ Running as PWA:', status.isStandalone ? '✅' : '❌');

    if (status.manifestData) {
        console.log('📱 Manifest Data:', status.manifestData);
    }

    // Provide recommendations
    console.group('💡 Recommendations');
    if (!status.serviceWorkerRegistered) {
        console.warn('Service Worker not registered. Refresh the page and check again.');
    }
    if (!status.manifestAccessible) {
        console.error('Manifest not accessible at /manifest.json. Check route configuration.');
    }
    if (!status.isHTTPS) {
        console.warn('PWA requires HTTPS or localhost. Install may not be available on this domain.');
    }
    if (status.serviceWorkerRegistered && status.manifestAccessible && status.isHTTPS && !status.isStandalone) {
        console.log('✅ PWA is ready for installation!');
        console.log('');
        console.log('📱 HOW TO INSTALL:');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('');
        console.log('Option 1: Install Icon (Chrome/Edge)');
        console.log('  • Look for install icon in address bar (⊕ or ⬇ icon)');
        console.log('  • May take 2-3 page visits to appear');
        console.log('  • Browser checks engagement before showing');
        console.log('');
        console.log('Option 2: Browser Menu (Always Available)');
        console.log('  Chrome: Menu (⋮) → "Install Fellowship..."');
        console.log('  Edge: Menu (⋯) → Apps → "Install this site as an app"');
        console.log('  Firefox: Address bar → App icon → Install');
        console.log('');
        console.log('Option 3: Trigger Engagement');
        console.log('  1. Close and reopen browser');
        console.log('  2. Visit the site again');
        console.log('  3. Navigate to 2-3 different pages');
        console.log('  4. Wait ~30 seconds on the site');
        console.log('  5. Check address bar for install icon');
        console.log('');
        console.log('🔍 CHECK INSTALLATION ELIGIBILITY:');
        console.log('  Chrome DevTools → Application → Manifest');
        console.log('  Look for "Add to home screen" section');
        console.log('  Any warnings listed there will block installation');
        console.log('');
    }
    console.groupEnd();

    console.groupEnd();

    return status;
}
