import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useSettingsStore } from '@/store/settingStore';

describe('Sandbox Collaboration Settings', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useSettingsStore();
    });

    describe('sandboxCollaborationEnabled getter', () => {
        it('returns false by default', () => {
            expect(store.sandboxCollaborationEnabled).toBe(false);
        });

        it('returns true when set to true', () => {
            store.appSettings.sandbox_collaboration_enabled = true;
            expect(store.sandboxCollaborationEnabled).toBe(true);
        });

        it('returns false when set to false', () => {
            store.appSettings.sandbox_collaboration_enabled = false;
            expect(store.sandboxCollaborationEnabled).toBe(false);
        });

        it('handles string "true"', () => {
            store.appSettings.sandbox_collaboration_enabled = 'true';
            expect(store.sandboxCollaborationEnabled).toBe(true);
        });

        it('handles string "false"', () => {
            store.appSettings.sandbox_collaboration_enabled = 'false';
            expect(store.sandboxCollaborationEnabled).toBe(false);
        });

        it('handles string "1"', () => {
            store.appSettings.sandbox_collaboration_enabled = '1';
            expect(store.sandboxCollaborationEnabled).toBe(true);
        });

        it('handles string "0"', () => {
            store.appSettings.sandbox_collaboration_enabled = '0';
            expect(store.sandboxCollaborationEnabled).toBe(false);
        });

        it('returns false for null', () => {
            store.appSettings.sandbox_collaboration_enabled = null;
            expect(store.sandboxCollaborationEnabled).toBe(false);
        });

        it('returns false for undefined', () => {
            store.appSettings.sandbox_collaboration_enabled = undefined;
            expect(store.sandboxCollaborationEnabled).toBe(false);
        });
    });

    describe('collaboration fallback logic', () => {
        it('should skip collaboration when setting is disabled', () => {
            store.appSettings.sandbox_collaboration_enabled = false;
            const collaborationEnabled = store.sandboxCollaborationEnabled;

            // When disabled, editor should init without collaboration
            expect(collaborationEnabled).toBe(false);
        });

        it('should attempt collaboration when setting is enabled', () => {
            store.appSettings.sandbox_collaboration_enabled = true;
            const collaborationEnabled = store.sandboxCollaborationEnabled;

            expect(collaborationEnabled).toBe(true);
        });
    });
});

describe('y-websocket v3 synced event compatibility', () => {
    // y-websocket v3 emits synced as (boolean), not ({ synced })
    function handleSyncedEvent(isSynced) {
        return typeof isSynced === 'boolean' ? isSynced : isSynced?.synced ?? isSynced;
    }

    it('handles v3 boolean true', () => {
        expect(handleSyncedEvent(true)).toBe(true);
    });

    it('handles v3 boolean false', () => {
        expect(handleSyncedEvent(false)).toBe(false);
    });

    it('handles v2 object { synced: true }', () => {
        expect(handleSyncedEvent({ synced: true })).toBe(true);
    });

    it('handles v2 object { synced: false }', () => {
        expect(handleSyncedEvent({ synced: false })).toBe(false);
    });

    it('handles undefined gracefully', () => {
        expect(handleSyncedEvent(undefined)).toBe(undefined);
    });
});
