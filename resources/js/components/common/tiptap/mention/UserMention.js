import Mention from '@tiptap/extension-mention';
import { mergeAttributes } from '@tiptap/core';

/**
 * @username mention for the simple editor. Rendered as
 *   <span class="mention" data-user-id="1" data-username="alice">@alice</span>
 * which survives the server-side sanitiser (span[class|data-username|data-user-id])
 * and which MentionService can still read as plain "@alice" text.
 */
const UserMention = Mention.extend({
    name: 'userMention',

    addAttributes() {
        return {
            id: {
                default: null,
                parseHTML: element => element.getAttribute('data-user-id'),
                renderHTML: attributes => (attributes.id ? { 'data-user-id': attributes.id } : {}),
            },
            label: {
                default: null,
                parseHTML: element => element.getAttribute('data-username'),
                renderHTML: attributes => (attributes.label ? { 'data-username': attributes.label } : {}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span[data-username]' }];
    },

    renderHTML({ node, HTMLAttributes }) {
        return [
            'span',
            mergeAttributes({ class: 'mention' }, this.options.HTMLAttributes, HTMLAttributes),
            `@${node.attrs.label ?? node.attrs.id}`,
        ];
    },

    renderText({ node }) {
        return `@${node.attrs.label ?? node.attrs.id}`;
    },
});

export default UserMention;
