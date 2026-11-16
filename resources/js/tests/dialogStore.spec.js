import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useDialogStore, createDialogApi } from '@/store/dialogStore.js';

describe('dialogStore', () => {
    let store;

    beforeEach(() => {
        setActivePinia(createPinia());
        store = useDialogStore();
    });

    it('shows a message and resolves when closed', async () => {
        const promise = store.error('Boom');
        expect(store.message.show).toBe(true);
        expect(store.message.type).toBe('error');
        expect(store.message.message).toBe('Boom');

        store.closeMessage();
        await expect(promise).resolves.toBeUndefined();
        expect(store.message.show).toBe(false);
    });

    it('prefers the server message for request errors', () => {
        store.requestError({ response: { data: { message: 'Server said no' } }, message: 'Network' });
        expect(store.message.message).toBe('Server said no');

        store.requestError({ message: 'Network' }, 'Fallback');
        expect(store.message.message).toBe('Fallback');
    });

    it('resolves confirm with the answer', async () => {
        const yes = store.askConfirm({ title: 'T', content: 'C' });
        expect(store.confirm.show).toBe(true);
        store.closeConfirm(true);
        await expect(yes).resolves.toBe(true);

        const no = store.askConfirm('plain string');
        expect(store.confirm.content).toBe('plain string');
        store.closeConfirm(false);
        await expect(no).resolves.toBe(false);
    });

    it('treats an abandoned confirm as no', async () => {
        const first = store.askConfirm('first');
        store.askConfirm('second');
        await expect(first).resolves.toBe(false);
    });

    it('marks delete confirmations as destructive', () => {
        store.confirmDelete('Delete it?');
        expect(store.confirm.destructive).toBe(true);
        expect(store.confirm.color).toBe('error');
    });

    it('exposes the same API through createDialogApi', async () => {
        const api = createDialogApi(store);
        const promise = api.confirm({ content: 'x' });
        store.closeConfirm(true);
        await expect(promise).resolves.toBe(true);
    });
});
