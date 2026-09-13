import { describe, it, expect } from 'vitest';
import {
    isRunActive,
    isRunStalled,
    lastUpdateTime,
    minutesSinceUpdate,
    runSummary,
    RUN_STALLED_AFTER_MS,
} from '@/utils/migrationRun.js';

const iso = (msAgo, now = Date.UTC(2026, 8, 13, 12, 0, 0)) => new Date(now - msAgo).toISOString();
const NOW = Date.UTC(2026, 8, 13, 12, 0, 0);

const batch = (status, migrations = [], extra = {}) => ({
    batchId: 'batch-1',
    status,
    summary: { pending: 0, running: 0, completed: 0, failed: 0, total: migrations.length },
    migrations,
    ...extra,
});

const migration = (overrides = {}) => ({
    key: 'import_1',
    name: 'Legacy Users',
    status: 'running',
    total: 100,
    processed: 40,
    errors: 0,
    currentItem: 'Sam Vimes',
    startedAt: iso(60000),
    updatedAt: iso(10000),
    completedAt: null,
    ...overrides,
});

describe('isRunActive', () => {
    it('counts a queued batch as active — the work is coming, not done', () => {
        expect(isRunActive(batch('pending'))).toBe(true);
        expect(isRunActive(batch('running'))).toBe(true);
    });

    it('is false once the batch finished, and for no batch at all', () => {
        expect(isRunActive(batch('completed'))).toBe(false);
        expect(isRunActive(batch('completed_with_errors'))).toBe(false);
        expect(isRunActive(null)).toBe(false);
        expect(isRunActive(undefined)).toBe(false);
    });
});

describe('lastUpdateTime', () => {
    it('prefers the heartbeat the API sends for the whole batch', () => {
        const status = batch('running', [migration()], { lastUpdateAt: iso(1000) });
        expect(lastUpdateTime(status)).toBe(NOW - 1000);
    });

    it('falls back to the newest timestamp on the migrations', () => {
        const status = batch('running', [
            migration({ updatedAt: iso(50000) }),
            migration({ key: 'import_2', updatedAt: iso(5000) }),
        ]);
        expect(lastUpdateTime(status)).toBe(NOW - 5000);
    });

    it('is null when nothing carries a usable timestamp', () => {
        expect(lastUpdateTime(batch('running', [migration({
            startedAt: null, updatedAt: null, completedAt: null,
        })]))).toBe(null);
        expect(lastUpdateTime(batch('running', [migration({
            startedAt: 'not a date', updatedAt: null, completedAt: null,
        })]))).toBe(null);
        expect(lastUpdateTime(null)).toBe(null);
    });
});

describe('isRunStalled', () => {
    it('is false while the run keeps writing', () => {
        expect(isRunStalled(batch('running', [migration({ updatedAt: iso(5000) })]), NOW)).toBe(false);
    });

    it('is true once the log has been silent past the threshold', () => {
        // Silent throughout: the start counts as activity too, so a run that
        // began a minute ago has not been quiet long enough to worry about.
        const silent = batch('running', [migration({
            startedAt: iso(RUN_STALLED_AFTER_MS + 5000),
            updatedAt: iso(RUN_STALLED_AFTER_MS + 1000),
        })]);
        expect(isRunStalled(silent, NOW)).toBe(true);
        expect(minutesSinceUpdate(silent, NOW)).toBe(2);
    });

    it('never calls a finished batch stalled', () => {
        const old = batch('completed', [migration({ status: 'completed', updatedAt: iso(86400000) })]);
        expect(isRunStalled(old, NOW)).toBe(false);
    });

    it('stays quiet when there is no timestamp to judge by', () => {
        const blind = batch('running', [migration({ startedAt: null, updatedAt: null, completedAt: null })]);
        expect(isRunStalled(blind, NOW)).toBe(false);
        expect(minutesSinceUpdate(blind, NOW)).toBe(0);
    });
});

describe('runSummary', () => {
    it('reports the migration that is working and the batch progress', () => {
        const status = batch('running', [
            migration({ key: 'import_1', name: 'Wiki Terms', status: 'completed', processed: 100, total: 100, currentItem: null }),
            migration({ key: 'import_2', name: 'Wiki Pages', status: 'running', processed: 30, total: 200, errors: 2 }),
        ], { summary: { pending: 0, running: 1, completed: 1, failed: 0, total: 2 } });

        const summary = runSummary(status);

        expect(summary.active).toBe(true);
        expect(summary.name).toBe('Wiki Pages');
        expect(summary.currentItem).toBe('Sam Vimes');
        expect(summary.processed).toBe(130);
        expect(summary.total).toBe(300);
        expect(summary.errors).toBe(2);
        expect(summary.percentage).toBe(43.3);
        expect(summary.steps).toBe(2);
        expect(summary.stepsDone).toBe(1);
    });

    it('names the queued migration when none has started yet', () => {
        const summary = runSummary(batch('pending', [
            migration({ status: 'pending', processed: 0, total: 0, currentItem: null }),
        ]));

        expect(summary.name).toBe('Legacy Users');
        expect(summary.percentage).toBe(null);
    });

    it('survives an empty or missing batch', () => {
        expect(runSummary(null).active).toBe(false);
        expect(runSummary(null).processed).toBe(0);
        expect(runSummary(batch('running')).percentage).toBe(null);
    });

    it('never reports more than a full bar when a total is under-counted', () => {
        const summary = runSummary(batch('running', [migration({ processed: 150, total: 100 })]));
        expect(summary.percentage).toBe(100);
    });
});
