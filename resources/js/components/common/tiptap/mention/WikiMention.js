import Mention from '@tiptap/extension-mention';

const WikiMention = Mention.extend({
    name: "wiki",
    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: element => element.getAttribute('data-id'),
                renderHTML: attributes => {
                    if (!attributes.id) {
                        return {}
                    }

                    return {
                        'data-id': attributes.id,
                    }
                },
            },
            slug: {
                default: null,
                parseHTML: (element) => {
                    return {
                        slug: element.getAttribute("data-slug")
                    };
                },
                renderHTML: (attributes) => {
                    if (!attributes.slug) {
                        return {};
                    }

                    return {
                        "data-slug": attributes.slug
                    };
                }
            },
            name: {
                default: null,
                parseHTML: (element) => {
                    return {
                        name: element.getAttribute("data-name")
                    };
                },
                renderHTML: (attributes) => {
                    if (!attributes.name) {
                        return {};
                    }

                    return {
                        "data-name": attributes.name
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
                tag: "span[data-title]"
            }
        ];
    },
    renderHTML(props) {
        const { node } = props;
        return [
            "a",
            {
                "style": "font-weight:600;",
                "pagekey": node.attrs.id,
                "data-title": node.attrs.title,
                "data-linked-resource-type": "wikiinfo",
                "href": `/p/${node.attrs.slug}`
            },

            `${node.attrs.title}`
        ];
    }

})
export default WikiMention
