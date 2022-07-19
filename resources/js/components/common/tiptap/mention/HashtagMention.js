import Mention from '@tiptap/extension-mention';

const HashtagMention = Mention.extend({
    name: "hashtag",

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
            color: {
                default: null,
                parseHTML: element => element.getAttribute('data-color'),
                renderHTML: attributes => {
                    if (!attributes.color) {
                        return {}
                    }

                    return {
                        'data-color': attributes.color,
                    }
                },
            },
        };
    },
    parseHTML() {
        return [
            {
                tag: "span[data-name]"
            }
        ];
    },
    renderHTML({ node }) {
        // const { node } = props;
        console.log('node',node)

        return [
            "a",
            {
                "style": `font-weight:600;color:${node.attrs.color}`,
                "userkey": node.attrs.id,
                "data-tag": node.attrs.name,
                "data-linked-resource-type": "hashtaginfo",
                "href": `/tag/${node.attrs.id}`
            },

            `#${node.attrs.name}`
        ];
    }

})
export default HashtagMention
