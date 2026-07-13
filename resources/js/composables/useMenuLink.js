/**
 * Shared link resolution for menu items rendered from the menu store.
 *
 * A menu item has `{ type, href, ... }`. This centralises the "is it external,
 * does it have a real destination, which tag + props to bind" logic that was
 * previously duplicated across MegaMenu, MegaMenuItem and FooterMenuWidget.
 */
export function useMenuLink() {
    // Explicit external items AND absolute http(s) URLs count as external, so a
    // "custom" item pointing off-site is never handed to <router-link>.
    const isExternal = (item) => {
        const href = item?.href || '';
        return item?.type === 'external' || /^https?:\/\//i.test(href);
    };

    // Container/parent items can have no real destination ("#" or empty).
    const hasLink = (item) => {
        const href = item?.href;
        return !!href && href !== '#';
    };

    const linkTag = (item) => {
        if (!hasLink(item)) return 'div';
        return isExternal(item) ? 'a' : 'router-link';
    };

    const linkProps = (item) => {
        if (!hasLink(item)) return {};
        return isExternal(item)
            ? { href: item.href, target: '_blank', rel: 'noopener noreferrer' }
            : { to: item.href };
    };

    return { isExternal, hasLink, linkTag, linkProps };
}
