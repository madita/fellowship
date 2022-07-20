<template>
    <div class="tiptap">
        <div v-if="editor">
            <button @click="editor.chain().focus().toggleBold().run()" :class="{ 'is-active': editor.isActive('bold') }">
                <v-icon>mdi-format-bold</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleItalic().run()" :class="{ 'is-active': editor.isActive('italic') }">
                <v-icon>mdi-format-italic</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleStrike().run()" :class="{ 'is-active': editor.isActive('strike') }">
                <v-icon>mdi-format-strikethrough-variant</v-icon>
            </button>
<!--            <button @click="editor.chain().focus().unsetAllMarks().run()">-->
<!--                clear marks-->
<!--            </button>-->
            <button @click="editor.chain().focus().clearNodes().run()">
                <v-icon>mdi-format-clear</v-icon>
            </button>
            <button @click="editor.chain().focus().setParagraph().run()" :class="{ 'is-active': editor.isActive('paragraph') }">
                <v-icon>mdi-format-pilcrow</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleHeading({ level: 1 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 1 }) }">
                <v-icon>mdi-format-header-1</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleHeading({ level: 2 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 2 }) }">
                <v-icon>mdi-format-header-2</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleHeading({ level: 3 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 3 }) }">
                <v-icon>mdi-format-header-3</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleHeading({ level: 4 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 4 }) }">
                <v-icon>mdi-format-header-4</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleHeading({ level: 5 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 5 }) }">
                <v-icon>mdi-format-header-5</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleHeading({ level: 6 }).run()" :class="{ 'is-active': editor.isActive('heading', { level: 6 }) }">
                <v-icon>mdi-format-header-6</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleBulletList().run()" :class="{ 'is-active': editor.isActive('bulletList') }">
                <v-icon>mdi-format-list-bulleted</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleOrderedList().run()" :class="{ 'is-active': editor.isActive('orderedList') }">
                <v-icon>mdi-format-list-numbered</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleBlockquote().run()" :class="{ 'is-active': editor.isActive('blockquote') }">
                <v-icon>mdi-format-quote-close</v-icon>
            </button>
            <button @click="editor.chain().focus().setHorizontalRule().run()">
                <v-icon>mdi-minus</v-icon>
            </button>
            <button @click="editor.chain().focus().setHardBreak().run()">
                <v-icon>mdi-format-page-break</v-icon>
            </button>
            <button @click="editor.chain().focus().undo().run()">
                <v-icon>mdi-undo</v-icon>
            </button>
            <button @click="editor.chain().focus().redo().run()">
                <v-icon>mdi-redo</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleCode().run()" :class="{ 'is-active': editor.isActive('code') }">
                <v-icon>mdi-code-tags</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleCodeBlock().run()" :class="{ 'is-active': editor.isActive('codeBlock') }">
                <v-icon>mdi-file-code-outline</v-icon>
            </button>
        </div>
        <editor-content :editor="editor" v-model="form.body"/>

        <div v-if="editor && limit" :class="{'character-count': true, 'character-count--warning': editor.storage.characterCount.characters() === limit}">
            <svg
                height="20"
                width="20"
                viewBox="0 0 20 20"
                class="character-count__graph"
            >
                <circle
                    r="10"
                    cx="10"
                    cy="10"
                    fill="#e9ecef"
                />
                <circle
                    r="5"
                    cx="10"
                    cy="10"
                    fill="transparent"
                    stroke="currentColor"
                    stroke-width="10"
                    :stroke-dasharray="`calc(${percentage} * 31.4 / 100) 31.4`"
                    transform="rotate(-90) translate(-20)"
                />
                <circle
                    r="6"
                    cx="10"
                    cy="10"
                    fill="white"
                />
            </svg>

            <div class="character-count__text">{{ editor.storage.characterCount.characters() }}/{{ limit }} characters</div>
        </div>
    </div>

</template>

<script>
import CharacterCount from '@tiptap/extension-character-count'
import StarterKit from '@tiptap/starter-kit'
import WikiMention from './mention/WikiMention'
import CustomMention from './mention/CustomMention'
import HashtagMention from './mention/HashtagMention'
import { Editor, EditorContent } from '@tiptap/vue-2'

import suggestion from './mention/suggestion'
import hashtag from './mention/hashtag'
import wiki from './mention/wiki'


export default {
    components: {
        StarterKit,
        EditorContent,
    },

    props: ['value', 'limit'],

    data() {
        return {
            editor: null,
            form: {
                body: this.value,
                images: [],
                imageNames: []
            },
        }
    },

    watch: {
        value(value) {
            const isSame = this.editor.getHTML() === value

            if (isSame) {
                return
            }

            this.editor.commands.setContent(value)

        }
    },

    mounted() {
        this.editor = new Editor({
            extensions: [
                StarterKit,
                CharacterCount.configure({
                    limit: this.limit,
                }),
                CustomMention.extend({
                    name: "mention",
                }).configure({
                    HTMLAttributes: {
                        class: 'mention',
                    },
                    suggestion,
                }),
                HashtagMention.extend({
                    name: "hashtag",
                }).configure({
                    HTMLAttributes: {
                        class: 'hashtag',
                    },
                    suggestion:hashtag,
                }),

                WikiMention.configure({
                    HTMLAttributes: {
                        class: 'wiki',
                    },
                    suggestion:wiki,
                }),
            ],
            content: this.value,
            onUpdate: () => {
                this.$emit('input', this.editor.getHTML())
            }
        })
    },

    computed: {
        percentage() {
            return Math.round((100 / this.limit) * this.editor.storage.characterCount.characters())
        },
    },

    beforeUnmount() {
        this.editor.destroy()
    },
}
</script>

<style lang="scss">
/* Basic editor styles */
.ProseMirror {
    > * + * {
        margin-top: 0.75em;
    }

    /*min-height: 100px;
    max-height: 100px;
    overflow: scroll;*/

    ul,
    ol {
        padding: 0 1rem;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        line-height: 1.1;
    }

    code {
        background-color: rgba(#616161, 0.1);
        color: #616161;
    }

    pre {
        background: #0D0D0D;
        color: #FFF;
        font-family: 'JetBrainsMono', monospace;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;

        code {
            color: inherit;
            padding: 0;
            background: none;
            font-size: 0.8rem;
        }
    }

    img {
        max-width: 100%;
        height: auto;
    }

    blockquote {
        padding-left: 1rem;
        border-left: 2px solid rgba(#0D0D0D, 0.1);
    }

    hr {
        border: none;
        border-top: 2px solid rgba(#0D0D0D, 0.1);
        margin: 2rem 0;
    }
}

.tiptap .is-active {
    background: grey;
    color: #fff;
}

.tiptap button {
    font-size: inherit;
    font-family: inherit;
    color: #000;
    margin: 0.1rem;
    border: 1px solid black;
    border-radius: 0.3rem;
    padding: 0.1rem 0.4rem;
    background: white;
}

.mention {
    border: 1px solid #000;
    border-radius: 0.4rem;
    padding: 0.1rem 0.3rem;
    box-decoration-break: clone;
}

.hashtag {
    border: 1px dashed #000;
    border-radius: 0.4rem;
    padding: 0.1rem 0.3rem;
    box-decoration-break: clone;
}
.wiki {
    border: 1px solid blue;
    border-radius: 0.4rem;
    padding: 0.1rem 0.3rem;
    box-decoration-break: clone;
}



.character-count {
    margin-top: 1rem;
    display: flex;
    align-items: center;
    color: #68CEF8;

    &--warning {
        color: #FB5151;
    }

    &__graph {
        margin-right: 0.5rem;
    }

    &__text {
        color: #868e96;
    }
}
</style>
