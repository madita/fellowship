import { Mark, mergeAttributes } from '@tiptap/core'

export const CommentMark = Mark.create({
  name: 'comment',

  addOptions() {
    return {
      HTMLAttributes: {},
      onCommentClick: null,
    }
  },

  addAttributes() {
    return {
      threadId: {
        default: null,
        parseHTML: (element) => element.getAttribute('data-thread-id'),
        renderHTML: (attributes) => {
          if (!attributes.threadId) return {}
          return { 'data-thread-id': attributes.threadId }
        },
      },
    }
  },

  parseHTML() {
    return [
      {
        tag: 'span[data-thread-id]',
      },
    ]
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'span',
      mergeAttributes(this.options.HTMLAttributes, HTMLAttributes, {
        class: 'comment-highlight',
      }),
      0,
    ]
  },

  addCommands() {
    return {
      setComment:
        (threadId) =>
        ({ commands }) => {
          return commands.setMark(this.name, { threadId })
        },
      unsetComment:
        (threadId) =>
        ({ state, tr, dispatch }) => {
          const { doc } = state
          let found = false

          doc.descendants((node, pos) => {
            if (!node.isText) return
            const mark = node.marks.find(
              (m) => m.type.name === this.name && m.attrs.threadId === threadId
            )
            if (mark) {
              tr.removeMark(pos, pos + node.nodeSize, mark.type.create({ threadId }))
              found = true
            }
          })

          if (found && dispatch) {
            dispatch(tr)
          }
          return found
        },
    }
  },
})

export default CommentMark
