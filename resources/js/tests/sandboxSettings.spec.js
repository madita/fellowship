import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useSettingsStore } from '@/store/settingStore';

describe('Sandbox Settings Store', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useSettingsStore();
    });

    describe('sandboxEnabled getter', () => {
        it('returns true by default', () => {
            expect(store.sandboxEnabled).toBe(true);
        });

        it('returns false when set to false', () => {
            store.appSettings.sandbox_enabled = false;
            expect(store.sandboxEnabled).toBe(false);
        });

        it('handles string "true"', () => {
            store.appSettings.sandbox_enabled = 'true';
            expect(store.sandboxEnabled).toBe(true);
        });

        it('handles string "false"', () => {
            store.appSettings.sandbox_enabled = 'false';
            expect(store.sandboxEnabled).toBe(false);
        });

        it('handles string "1"', () => {
            store.appSettings.sandbox_enabled = '1';
            expect(store.sandboxEnabled).toBe(true);
        });

        it('handles string "0"', () => {
            store.appSettings.sandbox_enabled = '0';
            expect(store.sandboxEnabled).toBe(false);
        });
    });

    describe('sandboxPublicEnabled getter', () => {
        it('returns true by default', () => {
            expect(store.sandboxPublicEnabled).toBe(true);
        });

        it('returns false when set to false', () => {
            store.appSettings.sandbox_public_enabled = false;
            expect(store.sandboxPublicEnabled).toBe(false);
        });

        it('handles string "true"', () => {
            store.appSettings.sandbox_public_enabled = 'true';
            expect(store.sandboxPublicEnabled).toBe(true);
        });

        it('handles string "false"', () => {
            store.appSettings.sandbox_public_enabled = 'false';
            expect(store.sandboxPublicEnabled).toBe(false);
        });
    });
});

describe('Sandbox Role Limits Logic', () => {
    // Test the merge logic that getUserLimits implements on the backend
    // Mirror the logic here to validate the expected behavior

    function resolveUserLimits(userRoles, roleLimits) {
        const keys = ['max_sandboxes', 'max_collaborators', 'max_versions'];
        const matchedRoles = userRoles.filter(r => roleLimits[r]);

        if (matchedRoles.length === 0) {
            return { max_sandboxes: 0, max_collaborators: 0, max_versions: 0 };
        }

        const resolved = { max_sandboxes: null, max_collaborators: null, max_versions: null };

        for (const role of matchedRoles) {
            for (const key of keys) {
                const roleValue = parseInt(roleLimits[role]?.[key] ?? 0, 10);
                const currentValue = resolved[key];

                if (currentValue === null) {
                    resolved[key] = roleValue;
                } else if (roleValue === 0 || currentValue === 0) {
                    resolved[key] = 0;
                } else {
                    resolved[key] = Math.max(currentValue, roleValue);
                }
            }
        }

        for (const key of keys) {
            if (resolved[key] === null) resolved[key] = 0;
        }

        return resolved;
    }

    it('returns defaults for user with no matching roles', () => {
        const result = resolveUserLimits(['guest'], { user: { max_sandboxes: 5 } });
        expect(result).toEqual({ max_sandboxes: 0, max_collaborators: 0, max_versions: 0 });
    });

    it('applies single role limits', () => {
        const result = resolveUserLimits(['user'], {
            user: { max_sandboxes: 3, max_collaborators: 5, max_versions: 10 },
        });
        expect(result).toEqual({ max_sandboxes: 3, max_collaborators: 5, max_versions: 10 });
    });

    it('0 means unlimited and wins over any number', () => {
        const result = resolveUserLimits(['user', 'admin'], {
            user: { max_sandboxes: 3, max_collaborators: 5, max_versions: 10 },
            admin: { max_sandboxes: 0, max_collaborators: 0, max_versions: 0 },
        });
        expect(result).toEqual({ max_sandboxes: 0, max_collaborators: 0, max_versions: 0 });
    });

    it('picks the higher (more permissive) value across roles', () => {
        const result = resolveUserLimits(['user', 'moderator'], {
            user: { max_sandboxes: 3, max_collaborators: 5, max_versions: 10 },
            moderator: { max_sandboxes: 10, max_collaborators: 2, max_versions: 20 },
        });
        expect(result).toEqual({ max_sandboxes: 10, max_collaborators: 5, max_versions: 20 });
    });

    it('treats missing keys as 0 (unlimited)', () => {
        const result = resolveUserLimits(['user'], {
            user: { max_sandboxes: 5 },
        });
        expect(result.max_sandboxes).toBe(5);
        expect(result.max_collaborators).toBe(0);
        expect(result.max_versions).toBe(0);
    });

    it('single role with all zeros means unlimited', () => {
        const result = resolveUserLimits(['user'], {
            user: { max_sandboxes: 0, max_collaborators: 0, max_versions: 0 },
        });
        expect(result).toEqual({ max_sandboxes: 0, max_collaborators: 0, max_versions: 0 });
    });
});

describe('Sandbox Visibility Options', () => {
    it('should exclude public option when sandbox_public_enabled is false', () => {
        const allOptions = ['private', 'members', 'public'];
        const publicEnabled = false;

        const options = publicEnabled
            ? allOptions
            : allOptions.filter(o => o !== 'public');

        expect(options).toEqual(['private', 'members']);
        expect(options).not.toContain('public');
    });

    it('should include public option when sandbox_public_enabled is true', () => {
        const allOptions = ['private', 'members', 'public'];
        const publicEnabled = true;

        const options = publicEnabled
            ? allOptions
            : allOptions.filter(o => o !== 'public');

        expect(options).toEqual(['private', 'members', 'public']);
    });
});
