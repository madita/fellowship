// Live state of a migration batch.
//
// Imports run in the background, so the migration screen has to answer one
// question at any moment: is something running right now? The dashboard
// restores the running batch from the API after a reload, and these helpers
// turn its status payload into what the indicator shows — kept pure so they
// can be tested without mounting the page.

// A run writes to its log on every row, so the log's last update is a
// heartbeat. Silence for this long means the process is gone — a killed
// worker, a timed-out request, a crashed CLI run — and the "running" state
// is a leftover rather than live progress.
export const RUN_STALLED_AFTER_MS = 2 * 60 * 1000;

export const RUNNING_STATES = ['pending', 'running'];

export const isRunActive = (batchStatus) => RUNNING_STATES.includes(batchStatus?.status);

const toTime = (value) => {
    if (!value) return null;
    const time = new Date(value).getTime();
    return Number.isNaN(time) ? null : time;
};

// The newest heartbeat in the batch: the top-level field when the API sends
// one, otherwise the newest timestamp any of its migrations carries.
export const lastUpdateTime = (batchStatus) => {
    const candidates = [toTime(batchStatus?.lastUpdateAt)];

    for (const migration of batchStatus?.migrations || []) {
        candidates.push(toTime(migration?.updatedAt));
        candidates.push(toTime(migration?.completedAt));
        candidates.push(toTime(migration?.startedAt));
    }

    const known = candidates.filter(time => time !== null);
    return known.length ? Math.max(...known) : null;
};

// An old backend sends no timestamps at all — then we cannot tell a dead run
// from a slow one, and claiming it died would be worse than staying quiet.
export const isRunStalled = (batchStatus, now = Date.now(), threshold = RUN_STALLED_AFTER_MS) => {
    if (!isRunActive(batchStatus)) return false;

    const last = lastUpdateTime(batchStatus);
    return last === null ? false : now - last >= threshold;
};

export const minutesSinceUpdate = (batchStatus, now = Date.now()) => {
    const last = lastUpdateTime(batchStatus);
    return last === null ? 0 : Math.max(0, Math.floor((now - last) / 60000));
};

// What the indicator needs in one object: which migration is working, how
// far the batch has come and whether anything failed.
export const runSummary = (batchStatus) => {
    const migrations = batchStatus?.migrations || [];
    const pending = migrations.filter(migration => RUNNING_STATES.includes(migration?.status));
    const current = pending.find(migration => migration.status === 'running') || pending[0] || null;

    const sum = (field) => migrations.reduce(
        (total, migration) => total + (Number(migration?.[field]) || 0),
        0
    );

    const processed = sum('processed');
    const total = sum('total');

    return {
        active: isRunActive(batchStatus),
        status: batchStatus?.status || null,
        // The migration currently doing the work — a batch can hold several.
        name: current?.name || null,
        currentItem: current?.currentItem || null,
        processed,
        total,
        errors: sum('errors'),
        percentage: total > 0 ? Math.min(100, Math.round((processed / total) * 1000) / 10) : null,
        steps: Number(batchStatus?.summary?.total) || migrations.length,
        stepsDone: Number(batchStatus?.summary?.completed) || 0,
        failed: Number(batchStatus?.summary?.failed) || 0,
    };
};
