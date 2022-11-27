import { VueRenderer } from '@tiptap/vue-2'
import tippy from 'tippy.js'
import { PluginKey } from "prosemirror-state";

import MentionList from './MentionList.vue'

export default {
    char: '#',
    pluginKey: new PluginKey("hashKey"),
    // items: ({ query }) => {
    //     return [
    //         'Test', 'Cyndi Lauper', 'Tom Cruise', 'Madonna', 'Jerry Hall', 'Joan Collins', 'Winona Ryder', 'Christina Applegate', 'Alyssa Milano', 'Molly Ringwald', 'Ally Sheedy', 'Debbie Harry', 'Olivia Newton-John', 'Elton John', 'Michael J. Fox', 'Axl Rose', 'Emilio Estevez', 'Ralph Macchio', 'Rob Lowe', 'Jennifer Grey', 'Mickey Rourke', 'John Cusack', 'Matthew Broderick', 'Justine Bateman', 'Lisa Bonet',
    //     ].filter(item => item.toLowerCase().startsWith(query.toLowerCase())).slice(0, 5)
    // },

    items: async function(editor) {
        const response = await axios('/api/tag/terms/wiki')

        return response.data.filter(item => item.title.toLowerCase().startsWith(editor.query.toLowerCase())).slice(0, 5)
    }.bind(this),

    render: () => {
        let component
        let popup

        return {
            onStart: props => {
                component = new VueRenderer(MentionList, {
                    // using vue 2:
                    parent: this,
                    propsData: props,
                    // props,
                    // editor: props.editor,
                })

                if (!props.clientRect) {
                    return
                }

                popup = tippy('body', {
                    getReferenceClientRect: props.clientRect,
                    appendTo: () => document.body,
                    content: component.element,
                    showOnCreate: true,
                    interactive: true,
                    trigger: 'manual',
                    placement: 'bottom-start',
                })
            },

            onUpdate(props) {
                component.updateProps(props)

                if (!props.clientRect) {
                    return
                }

                popup[0].setProps({
                    getReferenceClientRect: props.clientRect,
                })
            },

            onKeyDown(props) {
                if (props.event.key === 'Escape') {
                    popup[0].hide()

                    return true
                }

                return component.ref?.onKeyDown(props)
            },

            onExit() {
                popup[0].destroy()
                component.destroy()
            },
        }
    },
}
