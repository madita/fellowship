import Mention from '@tiptap/extension-mention';

const HashtagMention = Mention.extend({
    name: "hashtag",

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: element => element.getAttribute('term-id'),
                renderHTML: attributes => {
                    if (!attributes.id) {
                        return {}
                    }

                    return {
                        'term-id': attributes.id,
                    }
                },
            },
            title: {
                default: null,
                parseHTMtableL: element => element.getAttribute('data-tag'),
                renderHTML: (attributes) => {
                    if (!attributes.title) {
                        return {};
                    }

                    return {
                        "data-tag": attributes.title
                    };
                }
            },
            color: {
                default: null,
                parseHTML: element => element.getAttribute('data-color'),
                renderHTML: attributes => {
                    return {
                        'data-color': attributes.color,
                        style: `color: ${attributes.color}`,
                    }
                },
            },
            slug: {
                default: null,
                parseHTML: element => element.getAttribute('term-slug'),
                renderHTML: attributes => {
                    if (!attributes.slug) {
                        return {}
                    }

                    return {
                        'term-slug': attributes.slug,
                    }
                },
            },
            alternative: {
                default: null,
                parseHTML: element => element.getAttribute('alternative'),
                renderHTML: attributes => {
                    return {
                        "alternative": attributes.alternative
                    };
                },
            },
        };
    },
    parseHTML() {
        return [
            {
                tag: "a[term-id]"
            }
        ];
    },
    renderHTML({ node }) {
        return [
            "a",
            {
                "style": `font-weight:600;color:${node.attrs.color}`,
                "data-term-id": node.attrs.id,
                "data-tag": node.attrs.title,
                "data-color": node.attrs.color,
                "data-linked-resource-type": "terms",
                "alternative": node.attrs.alternative,
                "href": `/tag/${node.attrs.slug}`
            },
            `#${node.attrs.alternative ?? node.attrs.title}`
        ];
    }

})
export default HashtagMention
