import Mention from '@tiptap/extension-mention';

const WikiMention = Mention.extend({
    name: "wiki",
    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: element => element.getAttribute('page-id'),
                renderHTML: attributes => {
                    if (!attributes.id) {
                        return {}
                    }

                    return {
                        'page-id': attributes.id,
                    }
                },
            },
            slug: {
                default: null,
                parseHTML: element => element.getAttribute('data-slug'),
                renderHTML: (attributes) => {
                    if (!attributes.slug) {
                        return {};
                    }

                    return {
                        "data-slug": attributes.slug
                    };
                }
            },
            title: {
                default: null,
                parseHTML: element => element.getAttribute('data-title'),
                renderHTML: attributes => {
                    if (!attributes.title) {
                        return {}
                    }

                    return {
                        'data-title': attributes.title,
                    }
                },
            },
        };
    },
    parseHTML() {
        return [
            {
                tag: "a[wiki-id]"
            }
        ];
    },
    renderHTML({ node }) {

        return [
            "a",
            {
                "style": "font-weight:600;",
                "wiki-id": node.attrs.id,
                "data-title": node.attrs.title,
                "data-slug": node.attrs.slug,
                "data-linked-resource-type": "wikiable",
                "href": `/wiki/${node.attrs.slug}`
            },

            `${node.attrs.title}`
        ];
    }

})
export default WikiMention
