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
            name: {
                default: null,
                parseHTML: element => element.getAttribute('data-tag'),
                renderHTML: (attributes) => {
                    if (!attributes.name) {
                        return {};
                    }

                    return {
                        "data-tag": attributes.name
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
                "term-id": node.attrs.id,
                "data-tag": node.attrs.name,
                "data-color": node.attrs.color,
                "data-linked-resource-type": "terms",
                "href": `/tag/${node.attrs.id}`
            },
            `#${node.attrs.name}`
        ];
    }

})
export default HashtagMention
