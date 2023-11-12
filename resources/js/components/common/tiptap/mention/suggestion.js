import { VueRenderer } from '@tiptap/vue-3'

import tippy from 'tippy.js'

import MentionList from './MentionList.vue'
// import MentionPlugin from './MentionPlugin'


export default {
    // pluginKey: MentionPlugin,
    // items: ({ query }) => {
    //     return [
    //         'Lea Thompson', 'Cyndi Lauper', 'Tom Cruise', 'Madonna', 'Jerry Hall', 'Joan Collins', 'Winona Ryder', 'Christina Applegate', 'Alyssa Milano', 'Molly Ringwald', 'Ally Sheedy', 'Debbie Harry', 'Olivia Newton-John', 'Elton John', 'Michael J. Fox', 'Axl Rose', 'Emilio Estevez', 'Ralph Macchio', 'Rob Lowe', 'Jennifer Grey', 'Mickey Rourke', 'John Cusack', 'Matthew Broderick', 'Justine Bateman', 'Lisa Bonet',
    //     ].filter(item => item.toLowerCase().startsWith(query.toLowerCase())).slice(0, 5)
    // },
    items: async function(editor) {
        const response = await axios('/api/datatable/users?page=1&itemsPerPage=100000&pageStart=-1&pageStop=100000000&pageCount=-1&itemsLength=-1')

        return response.data.data.records.data.filter(item => item.username.toLowerCase().startsWith(editor.query.toLowerCase())).slice(0, 5)
    }.bind(this),


    // items: async function(query) {
    //     const response = await this.$axios('users?query=' + query)
    //     var values = []
    //     response.data.forEach(user => {
    //         values.push({id: userr.user_id, name: userr.user.firstname})
    //     })
    //
    //     return values
    // }.bind(this),

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
