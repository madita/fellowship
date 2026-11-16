import { describe, it, expect } from 'vitest';
import {
    FEATURE_ACTIONS,
    feature,
    umbrellaFor,
    satisfies,
    satisfiesAny,
} from '@/configs/featurePermissions.js';

// The rule that holding a feature whole answers for each part of it.
// The API applies it in one place (App\Support\FeaturePermissions::
// satisfies) and the browser has to apply the same one: a screen the
// router refuses that the API would have allowed hides work somebody was
// given, and a screen it opens that the API refuses throws their work
// away.

describe('feature()', () => {
    it('names the feature and every action under it', () => {
        expect(feature('podcast')).toEqual([
            'manage-podcast',
            'view-podcast',
            'create-podcast',
            'edit-podcast',
            'delete-podcast',
            'approve-podcast',
        ]);
    });

    it('narrows to the actions it is given', () => {
        expect(feature('page', 'create', 'edit')).toEqual([
            'manage-page',
            'create-page',
            'edit-page',
        ]);
    });

    it('gives an unsplit feature just itself', () => {
        expect(feature('setting')).toEqual(['manage-setting']);
    });
});

describe('umbrellaFor()', () => {
    it('finds the feature an action belongs to', () => {
        expect(umbrellaFor('create-podcast')).toBe('manage-podcast');
        expect(umbrellaFor('approve-wiki')).toBe('manage-wiki');
    });

    it('has none for a permission held whole', () => {
        expect(umbrellaFor('manage-setting')).toBeNull();
        expect(umbrellaFor('award-achievements')).toBeNull();
    });

    it('does not invent one for an action a feature is not split by', () => {
        // The gallery has no queue behind it, so approve-gallery is not
        // a permission and must not be answered for by manage-gallery
        expect(FEATURE_ACTIONS.gallery).not.toContain('approve');
        expect(umbrellaFor('approve-gallery')).toBeNull();
    });
});

describe('satisfies()', () => {
    it('is true for a permission actually held', () => {
        expect(satisfies('create-podcast', ['create-podcast'])).toBe(true);
    });

    it('is true when the feature is held whole', () => {
        expect(satisfies('create-podcast', ['manage-podcast'])).toBe(true);
        expect(satisfies('approve-wiki', ['manage-wiki'])).toBe(true);
    });

    it('does not run the other way', () => {
        // Being trusted to write a show is not being trusted with the
        // podcast, and certainly not with approving one
        expect(satisfies('manage-podcast', ['create-podcast'])).toBe(false);
        expect(satisfies('approve-podcast', ['create-podcast'])).toBe(false);
    });

    it('does not reach across features', () => {
        // The thing the whole set was split up to undo: manage-page
        // standing in for the wiki
        expect(satisfies('manage-wiki', ['manage-page'])).toBe(false);
        expect(satisfies('create-wiki', ['manage-page'])).toBe(false);
    });

    it('holds nothing for somebody with nothing', () => {
        expect(satisfies('create-podcast', [])).toBe(false);
        expect(satisfies('create-podcast')).toBe(false);
    });
});

describe('satisfiesAny()', () => {
    it('takes one name or a list of them', () => {
        expect(satisfiesAny('create-page', ['manage-page'])).toBe(true);
        expect(satisfiesAny(['manage-setting', 'manage-translation'], ['manage-translation'])).toBe(true);
        expect(satisfiesAny(['manage-setting', 'manage-translation'], ['manage-forum'])).toBe(false);
    });

    it('answers a whole feature list for any part held', () => {
        // What a menu entry does: offered to whoever holds any of it
        expect(satisfiesAny(feature('podcast'), ['approve-podcast'])).toBe(true);
        expect(satisfiesAny(feature('podcast'), ['manage-wiki'])).toBe(false);
    });
});
