// Which features are split by what is being done to them, mirroring
// App\Support\FeaturePermissions::CRUD. The two lists have to agree: a
// page the router opens that the API then refuses hands somebody a form
// and throws their work away, and a page the router refuses that the API
// would have allowed hides a screen somebody was given.
//
// `approve` only appears where something really waits to be approved -
// the wiki and the podcast, the two models with an approval queue behind
// them (App\Support\Approvals).
export const FEATURE_ACTIONS = {
    page: ['view', 'create', 'edit', 'delete'],
    post: ['view', 'create', 'edit', 'delete'],
    wiki: ['view', 'create', 'edit', 'delete', 'approve'],
    gallery: ['view', 'create', 'edit', 'delete'],
    forum: ['view', 'create', 'edit', 'delete'],
    event: ['view', 'create', 'edit', 'delete'],
    podcast: ['view', 'create', 'edit', 'delete', 'approve'],
};

/**
 * The permission names that open a feature's screens.
 *
 * `feature('podcast')` is manage-podcast and every action under it: any
 * one of them is a reason to be shown the podcast pages. Naming actions
 * narrows it, so a form that only ever writes can ask for
 * `feature('podcast', 'create', 'edit')`.
 */
export const feature = (name, ...actions) => {
    const wanted = actions.length ? actions : FEATURE_ACTIONS[name] || [];

    return [`manage-${name}`, ...wanted.map(action => `${action}-${name}`)];
};

/**
 * The permission standing over an action permission, or null.
 *
 * Only ever an action and the feature it belongs to - never the coarse
 * permission a whole feature was once carved out of, which is why
 * holding manage-page does not reach the wiki.
 */
export const umbrellaFor = (name) => {
    const dash = name.indexOf('-');

    if (dash < 0) {
        return null;
    }

    const action = name.slice(0, dash);
    const subject = name.slice(dash + 1);

    return (FEATURE_ACTIONS[subject] || []).includes(action) ? `manage-${subject}` : null;
};

/**
 * Whether the permissions somebody holds reach the wanted one.
 *
 * The same rule the API applies in one place
 * (App\Support\FeaturePermissions::satisfies, used by the route
 * middleware and the Gate): holding a feature whole answers for every
 * action under it, so nothing has to name both.
 */
export const satisfies = (wanted, held = []) => {
    if (held.includes(wanted)) {
        return true;
    }

    const umbrella = umbrellaFor(wanted);

    return umbrella !== null && held.includes(umbrella);
};

/**
 * Whether any of the wanted permissions is reached. Takes one name or a
 * list of them, because a screen belonging to several features names
 * several.
 */
export const satisfiesAny = (wanted, held = []) => {
    const names = Array.isArray(wanted) ? wanted : [wanted];

    return names.some(name => satisfies(name, held));
};
