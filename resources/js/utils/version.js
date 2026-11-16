/**
 * The version of the build that is currently running.
 *
 * Laravel puts it on `window.App` from `config('app.version')`, which reads
 * `package.json`. It is read once at module load: the value cannot change
 * without a new document, so there is nothing to react to.
 *
 * `null` when the page was not rendered by the SPA blade view - a unit test
 * mounting a component in isolation, for instance - so callers should treat a
 * missing version as "unknown" rather than assuming a string.
 */
export const appVersion = (typeof window !== 'undefined' && window.App && window.App.version) || null

/**
 * The same value prefixed for display, or an empty string when unknown.
 */
export const appVersionLabel = appVersion ? `v${appVersion}` : ''

export default appVersion
