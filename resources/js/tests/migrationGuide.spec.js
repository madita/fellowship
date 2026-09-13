import { describe, it, expect } from 'vitest';
import {
    targetStep,
    mappingStep,
    targetRequires,
    groupMappingsByStep,
    runChip,
    targetImported,
    missingPrerequisites,
    canRunMapping,
    isHeavyMapping,
    cliCommandFor,
    cliCommandsFor,
    postStepsDone,
    sortByOrder,
    buildGuideSteps,
    indexTargets,
    LAST_STEP,
} from '@/utils/migrationGuide.js';

const target = (key, extra = {}) => ({ key, label: key, fields: [], ...extra });
const mapping = (name, targetKey, lastRun = null) => ({
    id: name, name, target: targetKey, last_run: lastRun,
});

describe('targetStep', () => {
    it('uses the step the API sends', () => {
        expect(targetStep(target('forum_posts', { step: 2 }))).toBe(2);
    });

    it('falls back to the documented order when the API sends no step', () => {
        expect(targetStep(target('users'))).toBe(1);
        expect(targetStep(target('forum_threads'))).toBe(3);
        expect(targetStep(target('forum_posts'))).toBe(4);
    });

    it('sorts an unknown or invalid step last instead of dropping it', () => {
        expect(targetStep(target('something_new'))).toBe(LAST_STEP);
        expect(targetStep(target('users', { step: 0 }))).toBe(1);
        expect(targetStep(target('something_new', { step: 'x' }))).toBe(LAST_STEP);
    });

    it('resolves a mapping through the target list', () => {
        const byKey = indexTargets([target('events', { step: 2 })]);
        expect(mappingStep(mapping('Events', 'events'), byKey)).toBe(2);
        // Target missing from the list — fallback by key still applies.
        expect(mappingStep(mapping('Users', 'users'), {})).toBe(1);
    });
});

describe('targetRequires', () => {
    it('prefers the API list', () => {
        expect(targetRequires(target('forum_posts', { requires: ['forum_threads', 'users'] })))
            .toEqual(['forum_threads', 'users']);
    });

    it('falls back to the known dependencies', () => {
        expect(targetRequires(target('forum_posts'))).toEqual(['forum_threads']);
        expect(targetRequires(target('users'))).toEqual([]);
    });
});

describe('groupMappingsByStep', () => {
    const targets = [
        target('users', { step: 1 }),
        target('forum_categories', { step: 2 }),
        target('forum_threads', { step: 3 }),
        target('forum_posts', { step: 4 }),
    ];

    it('groups mappings into their step, in run order', () => {
        const groups = groupMappingsByStep([
            mapping('Posts', 'forum_posts'),
            mapping('Users', 'users'),
            mapping('Threads', 'forum_threads'),
        ], targets);

        expect(groups.map(group => group.step)).toEqual([1, 3, 4]);
        expect(groups[0].mappings.map(m => m.name)).toEqual(['Users']);
    });

    it('keeps mappings of the same target together and sorted by name', () => {
        const groups = groupMappingsByStep([
            mapping('B threads', 'forum_threads'),
            mapping('A categories', 'forum_categories'),
            mapping('A threads', 'forum_threads'),
        ], [target('forum_categories', { step: 3 }), target('forum_threads', { step: 3 })]);

        expect(groups).toHaveLength(1);
        expect(groups[0].mappings.map(m => m.name)).toEqual(['A categories', 'A threads', 'B threads']);
    });

    it('returns nothing for no mappings', () => {
        expect(groupMappingsByStep([], targets)).toEqual([]);
        expect(groupMappingsByStep(undefined, undefined)).toEqual([]);
    });
});

describe('runChip', () => {
    it('reports a mapping that never ran', () => {
        expect(runChip(mapping('Users', 'users')).state).toBe('never');
        expect(runChip(mapping('Users', 'users', null)).key).toBe('lastRunNever');
    });

    it('reports a running import with its progress', () => {
        const chip = runChip(mapping('Posts', 'forum_posts', {
            status: 'running', total_items: 900, processed_items: 120, error_count: 0,
        }));
        expect(chip.state).toBe('running');
        expect(chip.color).toBe('primary');
        expect(chip.params).toEqual({ count: 120, total: 900 });
    });

    it('reports a finished import with the row count and date', () => {
        const chip = runChip(mapping('Users', 'users', {
            status: 'completed', total_items: 42, processed_items: 42, error_count: 0,
            completed_at: '2026-02-03T10:00:00Z',
        }));
        expect(chip.state).toBe('completed');
        expect(chip.color).toBe('success');
        expect(chip.params.count).toBe(42);
        expect(chip.date).toBe('2026-02-03T10:00:00Z');
    });

    it('warns when a finished import had errors', () => {
        const chip = runChip(mapping('Wiki', 'wiki_pages', {
            status: 'completed', processed_items: 10, error_count: 3,
        }));
        expect(chip.state).toBe('completedWithErrors');
        expect(chip.color).toBe('warning');
        expect(chip.params.errors).toBe(3);
    });

    it('reports a failed import', () => {
        const chip = runChip(mapping('Wiki', 'wiki_pages', { status: 'failed', error_count: 1 }));
        expect(chip.state).toBe('failed');
        expect(chip.color).toBe('error');
        expect(chip.key).toBe('lastRunFailed');
    });
});

describe('prerequisites', () => {
    const targets = [
        target('forum_categories', { step: 2 }),
        target('forum_threads', { step: 3, requires: ['forum_categories'] }),
        target('forum_posts', { step: 4, requires: ['forum_threads'] }),
    ];

    it('treats a target as imported once one of its mappings completed', () => {
        const mappings = [mapping('Threads', 'forum_threads', { status: 'completed', processed_items: 5 })];
        expect(targetImported('forum_threads', mappings)).toBe(true);
        expect(targetImported('forum_categories', mappings)).toBe(false);
    });

    it('blocks posts until the threads import finished', () => {
        const mappings = [
            mapping('Threads', 'forum_threads', { status: 'running' }),
            mapping('Posts', 'forum_posts'),
        ];
        const missing = missingPrerequisites(mappings[1], targets, mappings);
        expect(missing.map(item => item.key)).toEqual(['forum_threads']);
        expect(canRunMapping(mappings[1], targets, mappings)).toBe(false);
    });

    it('releases posts once the threads import completed', () => {
        const mappings = [
            mapping('Threads', 'forum_threads', { status: 'completed', processed_items: 12 }),
            mapping('Posts', 'forum_posts'),
        ];
        expect(missingPrerequisites(mappings[1], targets, mappings)).toEqual([]);
        expect(canRunMapping(mappings[1], targets, mappings)).toBe(true);
    });

    it('counts a required target without any mapping as missing', () => {
        const mappings = [mapping('Posts', 'forum_posts')];
        expect(missingPrerequisites(mappings[0], targets, mappings).map(item => item.label))
            .toEqual(['forum_threads']);
    });

    it('never blocks a mapping without dependencies', () => {
        const mappings = [mapping('Categories', 'forum_categories')];
        expect(canRunMapping(mappings[0], targets, mappings)).toBe(true);
    });
});

describe('heavy imports', () => {
    it('flags the known big targets', () => {
        expect(isHeavyMapping(mapping('Images', 'gallery_images'))).toBe(true);
        expect(isHeavyMapping(mapping('Posts', 'forum_posts'))).toBe(true);
        expect(isHeavyMapping(mapping('Users', 'users'))).toBe(false);
    });

    it('respects a heavy flag from the API', () => {
        expect(isHeavyMapping(mapping('Events', 'events'), target('events', { heavy: true }))).toBe(true);
    });

    it('flags anything that already touched a lot of rows', () => {
        expect(isHeavyMapping(mapping('Events', 'events', { status: 'completed', total_items: 5000 }))).toBe(true);
        expect(isHeavyMapping(mapping('Events', 'events', { status: 'completed', total_items: 12 }))).toBe(false);
    });

    it('builds the CLI command with the mapping name quoted', () => {
        expect(cliCommandFor(mapping('Forum Posts', 'forum_posts')))
            .toBe('php artisan migration:run-mapping "Forum Posts"');
    });
});

describe('cliCommandsFor', () => {
    it('gives one command per mapping, in the order listed', () => {
        expect(cliCommandsFor([mapping('Users', 'users'), mapping('Events', 'events')])).toBe(
            'php artisan migration:run-mapping "Users"\nphp artisan migration:run-mapping "Events"'
        );
    });

    it('is empty for nothing, and skips gaps rather than emitting a broken command', () => {
        expect(cliCommandsFor([])).toBe('');
        expect(cliCommandsFor()).toBe('');
        expect(cliCommandsFor([null, mapping('Users', 'users')])).toBe('php artisan migration:run-mapping "Users"');
    });
});

describe('post-import steps', () => {
    it('is not done while a step never ran', () => {
        const steps = [{ key: 'linkGallery' }, { key: 'wikiLinking' }];
        expect(postStepsDone(steps, [])).toBe(false);
        expect(postStepsDone(steps, ['linkGallery'])).toBe(false);
        expect(postStepsDone(steps, ['linkGallery', 'wikiLinking'])).toBe(true);
    });

    it('accepts run state reported by the API', () => {
        expect(postStepsDone([
            { key: 'linkGallery', has_run: true },
            { key: 'wikiLinking', last_run: { status: 'completed' } },
        ], [])).toBe(true);
    });

    it('is not done when there are no steps at all', () => {
        expect(postStepsDone([], ['linkGallery'])).toBe(false);
    });

    it('sorts the steps by the order the API gives', () => {
        const sorted = sortByOrder([
            { key: 'c', order: 3 },
            { key: 'a', order: 1 },
            { key: 'x' },
            { key: 'b', order: 2 },
        ]);
        expect(sorted.map(step => step.key)).toEqual(['a', 'b', 'c', 'x']);
    });
});

describe('buildGuideSteps', () => {
    it('marks the first unfinished step as the current one', () => {
        const steps = buildGuideSteps({ sourceCount: 1 });
        expect(steps.map(step => step.key)).toEqual(['source', 'mappings', 'imports', 'post', 'legacy']);
        expect(steps[0].done).toBe(true);
        expect(steps[1].current).toBe(true);
        expect(steps.filter(step => step.current)).toHaveLength(1);
        expect(steps.map(step => step.number)).toEqual([1, 2, 3, 4, 5]);
    });

    it('starts on the source step when nothing exists yet', () => {
        const steps = buildGuideSteps();
        expect(steps[0].current).toBe(true);
        expect(steps[0].done).toBe(false);
        expect(steps[0].tab).toBe('sources');
    });

    it('finishes the import step only when every mapping ran', () => {
        expect(buildGuideSteps({ mappingCount: 3, importedMappingCount: 2 })[2].done).toBe(false);
        expect(buildGuideSteps({ mappingCount: 3, importedMappingCount: 3 })[2].done).toBe(true);
        // No mappings at all is not "all imported".
        expect(buildGuideSteps({ mappingCount: 0, importedMappingCount: 0 })[2].done).toBe(false);
    });

    it('blocks the post-import step until something was imported', () => {
        expect(buildGuideSteps({ importsRun: false, postStepCount: 3 })[3].blocked).toBe(true);
        expect(buildGuideSteps({ importsRun: true, postStepCount: 3, postDone: true })[3].done).toBe(true);
    });

    it('tracks the legacy users still waiting for an assignment', () => {
        const open = buildGuideSteps({ legacyUserCount: 5, unassignedLegacyUsers: 2 })[4];
        expect(open.done).toBe(false);
        expect(open.stat).toEqual({ open: 2, total: 5 });

        const closed = buildGuideSteps({ legacyUserCount: 5, unassignedLegacyUsers: 0 })[4];
        expect(closed.done).toBe(true);
        // Nothing recorded yet is not a finished step, it is an empty one.
        expect(buildGuideSteps({ legacyUserCount: 0 })[4].done).toBe(false);
        expect(buildGuideSteps({ legacyUserCount: 0 })[4].blocked).toBe(true);
    });

    it('points every step at the tab that handles it', () => {
        expect(buildGuideSteps().map(step => step.tab))
            .toEqual(['sources', 'mappings', 'mappings', 'runs', 'legacyUsers']);
    });
});
