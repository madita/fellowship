import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

// version.js reads window.App once at module load, so each case needs the
// module graph reset before importing it again.
describe('app version', () => {
    const original = window.App;

    beforeEach(() => {
        vi.resetModules();
    });

    afterEach(() => {
        window.App = original;
    });

    it('reads the version Laravel put on window.App', async () => {
        window.App = { version: '2.0.0-alpha.1' };

        const { appVersion, appVersionLabel } = await import('@/utils/version.js');

        expect(appVersion).toBe('2.0.0-alpha.1');
        expect(appVersionLabel).toBe('v2.0.0-alpha.1');
    });

    it('treats a missing window.App as an unknown version', async () => {
        delete window.App;

        const { appVersion, appVersionLabel } = await import('@/utils/version.js');

        expect(appVersion).toBeNull();
        expect(appVersionLabel).toBe('');
    });

    it('treats a window.App without a version as unknown', async () => {
        window.App = { baseUrl: 'http://localhost' };

        const { appVersion, appVersionLabel } = await import('@/utils/version.js');

        expect(appVersion).toBeNull();
        expect(appVersionLabel).toBe('');
    });
});
