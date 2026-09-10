import axios from 'axios';
import tippy from 'tippy.js';
import { VueRenderer } from '@tiptap/vue-3';
import UserMentionList from './UserMentionList.vue';

const MAX_RESULTS = 8;

/**
 * Suggestion source for UserMention: looks members up through
 * POST /api/users/search as the user types after "@" and shows them in
 * a small popup under the caret.
 */
export default {
    char: '@',
    allowSpaces: false,

    items: async ({ query }) => {
        try {
            const { data } = await axios.post('/api/users/search', { query });
            return (data || []).slice(0, MAX_RESULTS);
        } catch (e) {
            return [];
        }
    },

    render: () => {
        let component;
        let popup;

        return {
            onStart: props => {
                component = new VueRenderer(UserMentionList, { props, editor: props.editor });
                if (!props.clientRect) return;

                popup = tippy('body', {
                    getReferenceClientRect: props.clientRect,
                    appendTo: () => document.body,
                    content: component.element,
                    showOnCreate: true,
                    interactive: true,
                    trigger: 'manual',
                    placement: 'bottom-start',
                    theme: 'mention',
                    arrow: false,
                });
            },

            onUpdate(props) {
                component?.updateProps(props);
                if (!props.clientRect) return;
                popup?.[0]?.setProps({ getReferenceClientRect: props.clientRect });
            },

            onKeyDown(props) {
                if (props.event.key === 'Escape') {
                    popup?.[0]?.hide();
                    return true;
                }
                return component?.ref?.onKeyDown(props) ?? false;
            },

            onExit() {
                popup?.[0]?.destroy();
                component?.destroy();
            },
        };
    },
};
