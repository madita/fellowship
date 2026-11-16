import { VueRenderer } from '@tiptap/vue-3'
import tippy from 'tippy.js'
import { PluginKey } from "prosemirror-state";

import MentionList from './MentionList.vue'

export default {
    char: '[[',
    pluginKey: new PluginKey("wiki"),
    // items: ({ query }) => {
    //     return [
    //         'page 1', 'page 2', 'Tom Cruise', 'Madonna', 'Jerry Hall', 'Joan Collins', 'Winona Ryder', 'Christina Applegate', 'Alyssa Milano', 'Molly Ringwald', 'Ally Sheedy', 'Debbie Harry', 'Olivia Newton-John', 'Elton John', 'Michael J. Fox', 'Axl Rose', 'Emilio Estevez', 'Ralph Macchio', 'Rob Lowe', 'Jennifer Grey', 'Mickey Rourke', 'John Cusack', 'Matthew Broderick', 'Justine Bateman', 'Lisa Bonet',
    //     ].filter(item => item.toLowerCase().startsWith(query.toLowerCase())).slice(0, 5)
    // },

    // Pages whose title matches what was typed after "[[" (searched on the server)
    items: async function({ query }) {
        try {
            const response = await axios.get('/api/datatable/pages', { params: { search: query || undefined, per_page: 10 } })
            return (response.data.data.records.data || []).filter(item => item.title).slice(0, 5)
        } catch (e) {
            return []
        }
    },


    render: () => {
        let component
        let popup

        return {
            onStart: props => {
                component = new VueRenderer(MentionList, {
                    // using vue 2:
                    // parent: this,
                    // propsData: props,
                    props,
                    editor: props.editor,
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
