// Shared logic for the migration tool UI.
//
// The migration runs in a fixed order: connect a source → create the
// mappings → run the imports step by step → run the post-import steps →
// assign the legacy users. The dashboard guide and the mappings tab both
// need to know where a mapping sits in that order, whether its imports may
// run yet and what its last run did — that lives here so it can be tested
// without mounting a component.
//
// The API carries the order itself: every target has `step` (1-4), a list
// of target keys it `requires` and sometimes a `hint`. The fallbacks below
// keep the UI sensible against an older backend that does not send them.

// Import phases as documented in docs/migration-mappings.stadtwache.json.
export const FALLBACK_TARGET_STEPS = {
    users: 1,
    legacy_users: 1,
    events: 2,
    gallery_collections: 2,
    wiki_terms: 2,
    forum_categories: 2,
    gallery_images: 3,
    wiki_pages: 3,
    forum_threads: 3,
    forum_posts: 4,
};

// Which target needs which other target imported first.
export const FALLBACK_TARGET_REQUIRES = {
    gallery_images: ['gallery_collections'],
    forum_threads: ['forum_categories'],
    forum_posts: ['forum_threads'],
    wiki_pages: ['wiki_terms'],
};

// Targets whose tables are big enough that the dashboard button can time
// out — those get the "copy CLI command" escape hatch.
export const HEAVY_TARGET_KEYS = ['gallery_images', 'forum_posts'];

// …and so does anything that already imported (or tried to import) at
// least this many rows.
export const HEAVY_ROW_THRESHOLD = 2000;

export const LAST_STEP = 4;

const asStep = (value) => {
    const step = Number(value);
    return Number.isInteger(step) && step >= 1 && step <= LAST_STEP ? step : null;
};

// Index the targets list by key for the lookups below.
export const indexTargets = (targets = []) => {
    const map = {};
    for (const target of targets || []) {
        if (target && target.key) map[target.key] = target;
    }
    return map;
};

// Which import phase a target belongs to. Unknown targets sort last so a
// new target never disappears from the list.
export const targetStep = (target, key = null) => {
    const targetKey = target?.key ?? key;
    return asStep(target?.step) ?? FALLBACK_TARGET_STEPS[targetKey] ?? LAST_STEP;
};

export const mappingStep = (mapping, targetsByKey = {}) =>
    targetStep(targetsByKey[mapping?.target], mapping?.target);

// Target keys that have to be imported before this one.
export const targetRequires = (target, key = null) => {
    const targetKey = target?.key ?? key;
    const requires = target?.requires;
    if (Array.isArray(requires)) return requires.filter(Boolean);
    return FALLBACK_TARGET_REQUIRES[targetKey] || [];
};

// Mappings grouped into the import phases, phases in run order and empty
// ones dropped. Inside a phase mappings of the same target stay together.
export const groupMappingsByStep = (mappings = [], targets = []) => {
    const targetsByKey = indexTargets(targets);
    const groups = new Map();

    for (const mapping of mappings || []) {
        const step = mappingStep(mapping, targetsByKey);
        if (!groups.has(step)) groups.set(step, []);
        groups.get(step).push(mapping);
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a - b)
        .map(([step, rows]) => ({
            step,
            mappings: rows.slice().sort((a, b) =>
                String(a.target).localeCompare(String(b.target))
                || String(a.name).localeCompare(String(b.name))
            ),
        }));
};

// What the "last run" chip says. Returns the pieces; the component does
// the translating and date formatting.
export const runChip = (mapping) => {
    const run = mapping?.last_run;
    if (!run || !run.status) {
        return { state: 'never', color: undefined, icon: 'mdi-minus-circle-outline', key: 'lastRunNever', params: {} };
    }

    const rows = Number(run.processed_items ?? 0) || 0;
    const errors = Number(run.error_count ?? 0) || 0;

    if (run.status === 'pending' || run.status === 'running') {
        return {
            state: 'running',
            color: 'primary',
            icon: 'mdi-progress-clock',
            key: run.status === 'pending' ? 'lastRunPending' : 'lastRunRunning',
            params: { count: rows, total: Number(run.total_items ?? 0) || 0 },
            date: run.started_at || null,
        };
    }

    if (run.status === 'failed') {
        return {
            state: 'failed',
            color: 'error',
            icon: 'mdi-alert-circle-outline',
            key: 'lastRunFailed',
            params: { count: rows, errors },
            date: run.completed_at || run.started_at || null,
        };
    }

    return {
        state: errors > 0 ? 'completedWithErrors' : 'completed',
        color: errors > 0 ? 'warning' : 'success',
        icon: errors > 0 ? 'mdi-alert-outline' : 'mdi-check-circle-outline',
        key: errors > 0 ? 'lastRunCompletedErrors' : 'lastRunCompleted',
        params: { count: rows, errors },
        date: run.completed_at || run.started_at || null,
    };
};

// A target counts as imported once at least one of its mappings finished.
export const targetImported = (targetKey, mappings = []) =>
    (mappings || []).some(mapping =>
        mapping?.target === targetKey && mapping?.last_run?.status === 'completed'
    );

// Targets this mapping depends on that have not been imported yet. A
// target with no mapping at all counts as missing too — the import would
// find nothing to link to.
export const missingPrerequisites = (mapping, targets = [], mappings = []) => {
    const targetsByKey = indexTargets(targets);
    const required = targetRequires(targetsByKey[mapping?.target], mapping?.target);

    return required
        .filter(key => key !== mapping?.target && !targetImported(key, mappings))
        .map(key => ({ key, label: targetsByKey[key]?.label || key }));
};

export const canRunMapping = (mapping, targets = [], mappings = []) =>
    missingPrerequisites(mapping, targets, mappings).length === 0;

// Big imports belong on the command line, where nothing times out.
export const isHeavyMapping = (mapping, target = null) => {
    const targetKey = target?.key ?? mapping?.target;
    if (target?.heavy === true) return true;
    if (HEAVY_TARGET_KEYS.includes(targetKey)) return true;
    const rows = Number(mapping?.last_run?.total_items ?? 0) || 0;
    return rows >= HEAVY_ROW_THRESHOLD;
};

export const cliCommandFor = (mapping) =>
    `php artisan migration:run-mapping "${mapping?.name ?? ''}"`;

// Have the post-import steps run? The backend may report it per step
// (`last_run` / `has_run`); otherwise we count what this session watched
// finish.
export const postStepsDone = (migrations = [], ranKeys = []) => {
    const list = migrations || [];
    if (!list.length) return false;
    const ran = new Set(ranKeys || []);

    return list.every(migration =>
        migration?.has_run === true
        || migration?.last_run?.status === 'completed'
        || ran.has(migration?.key)
    );
};

// Sort the post-import steps by the order the backend gives them.
export const sortByOrder = (migrations = []) =>
    (migrations || []).slice().sort((a, b) => {
        const left = Number(a?.order ?? Number.MAX_SAFE_INTEGER);
        const right = Number(b?.order ?? Number.MAX_SAFE_INTEGER);
        return left - right;
    });

// The five guide steps with their live state. `done` marks a finished
// step, `current` the one to work on next and `blocked` a step that
// cannot start yet.
export const buildGuideSteps = ({
    sourceCount = 0,
    sourceTested = false,
    mappingCount = 0,
    importedMappingCount = 0,
    importsRun = false,
    postStepCount = 0,
    postDone = false,
    legacyUserCount = 0,
    unassignedLegacyUsers = 0,
} = {}) => {
    const steps = [
        {
            key: 'source',
            tab: 'sources',
            icon: 'mdi-database-outline',
            done: sourceCount > 0,
            stat: { count: sourceCount, tested: !!sourceTested },
        },
        {
            key: 'mappings',
            tab: 'mappings',
            icon: 'mdi-swap-horizontal',
            done: mappingCount > 0,
            stat: { count: mappingCount },
        },
        {
            key: 'imports',
            tab: 'mappings',
            icon: 'mdi-play-box-multiple-outline',
            done: mappingCount > 0 && importedMappingCount >= mappingCount,
            blocked: mappingCount === 0,
            stat: { done: importedMappingCount, total: mappingCount },
        },
        {
            key: 'post',
            tab: 'runs',
            icon: 'mdi-auto-fix',
            done: importsRun && postDone,
            blocked: !importsRun,
            stat: { count: postStepCount },
        },
        {
            key: 'legacy',
            tab: 'legacyUsers',
            icon: 'mdi-account-convert',
            done: legacyUserCount > 0 && unassignedLegacyUsers === 0,
            blocked: legacyUserCount === 0,
            stat: { open: unassignedLegacyUsers, total: legacyUserCount },
        },
    ];

    const next = steps.findIndex(step => !step.done);

    return steps.map((step, index) => ({
        ...step,
        number: index + 1,
        blocked: !!step.blocked,
        current: index === next,
    }));
};
