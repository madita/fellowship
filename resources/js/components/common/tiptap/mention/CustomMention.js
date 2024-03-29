import Mention from '@tiptap/extension-mention';

const CustomMention = Mention.extend({
    name: "mention",

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: element => element.getAttribute('user-id'),
                renderHTML: attributes => {
                    if (!attributes.id) {
                        return {}
                    }

                    return {
                        'user-id': attributes.id,
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
            username: {
                default: null,
                parseHTML: element => element.getAttribute('data-username'),
                renderHTML: attributes => {
                    if (!attributes.username) {
                        return {}
                    }

                    return {
                        'data-username': attributes.username,
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
                tag: "a[user-id]"
            }
        ];
    },
    renderHTML(props) {
        const { node } = props;
        return [
            "a",
            {
                "style": "font-weight:600;",
                "user-id": node.attrs.id,
                "data-username": node.attrs.username,
                "data-linked-resource-type": "user",
                "href": `/user/${node.attrs.username}`,
                "alternative": node.attrs.alternative,
            },

                `@${node.attrs.alternative ?? node.attrs.username}`
        ];
    }

})
export default CustomMention
