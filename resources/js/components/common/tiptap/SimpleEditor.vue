<template>
    <div
        class="simple-editor"
        :class="{ 'simple-editor--focused': focused, 'simple-editor--disabled': disabled }"
        @click="focus"
    >
        <div class="simple-editor__body" :style="{ minHeight }">
            <div v-if="isEmpty && placeholder" class="simple-editor__placeholder text-body-1">
                {{ placeholder }}
            </div>
            <editor-content :editor="editor" />
        </div>

        <div v-if="toolbar && editor" class="simple-editor__toolbar d-flex align-center ga-1 px-1">
            <v-btn
                v-for="action in actions"
                :key="action.name"
                icon
                size="x-small"
                variant="text"
                :color="editor.isActive(action.name) ? 'primary' : undefined"
                :title="$t(action.label)"
                :disabled="disabled"
                @click.stop="action.run()"
            >
                <v-icon size="18">{{ action.icon }}</v-icon>
            </v-btn>
            <span class="text-caption text-medium-emphasis ml-1">{{ $t('editor.mentionHint') }}</span>
            <v-spacer />
            <span
                v-if="limit"
                class="text-caption"
                :class="characters >= limit ? 'text-error' : 'text-medium-emphasis'"
            >
                {{ characters }} / {{ limit }}
            </span>
        </div>
    </div>
</template>

<script>
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import CharacterCount from '@tiptap/extension-character-count';
import UserMention from './mention/UserMention.js';
import userSuggestion from './mention/userSuggestion.js';

/**
 * Small rich-text box for posts and comments: bold, italic, strike,
 * lists and @username mentions of members (suggestions from
 * /api/users/search). v-model is HTML; an empty editor yields ''.
 * Ctrl/Cmd+Enter emits `submit`.
 */
export default {
    name: 'SimpleEditor',
    components: { EditorContent },
    props: {
        modelValue: { type: String, default: '' },
        placeholder: { type: String, default: '' },
        // Character limit shown in the toolbar; null for none
        limit: { type: Number, default: 5000 },
        disabled: { type: Boolean, default: false },
        toolbar: { type: Boolean, default: true },
        minHeight: { type: String, default: '48px' },
        autofocus: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'focus', 'blur', 'submit'],
    data() {
        return {
            editor: null,
            focused: false,
            isEmpty: true,
            characters: 0,
        };
    },
    computed: {
        actions() {
            const chain = () => this.editor.chain().focus();
            return [
                { name: 'bold', icon: 'mdi-format-bold', label: 'editor.bold', run: () => chain().toggleBold().run() },
                { name: 'italic', icon: 'mdi-format-italic', label: 'editor.italic', run: () => chain().toggleItalic().run() },
                { name: 'strike', icon: 'mdi-format-strikethrough', label: 'editor.strikeThrough', run: () => chain().toggleStrike().run() },
                { name: 'bulletList', icon: 'mdi-format-list-bulleted', label: 'editor.bulletList', run: () => chain().toggleBulletList().run() },
            ];
        },
    },
    watch: {
        modelValue(value) {
            if (!this.editor) return;
            const current = this.editor.isEmpty ? '' : this.editor.getHTML();
            if ((value || '') !== current) {
                this.editor.commands.setContent(value || '', false);
                this.sync();
            }
        },
        disabled(value) {
            this.editor?.setEditable(!value);
        },
    },
    mounted() {
        this.editor = new Editor({
            content: this.modelValue,
            editable: !this.disabled,
            autofocus: this.autofocus ? 'end' : false,
            extensions: [
                StarterKit.configure({
                    heading: false,
                    codeBlock: false,
                    blockquote: false,
                    horizontalRule: false,
                }),
                CharacterCount.configure({ limit: this.limit || null }),
                UserMention.configure({
                    HTMLAttributes: { class: 'mention' },
                    suggestion: userSuggestion,
                }),
            ],
            editorProps: {
                attributes: { class: 'simple-editor__content' },
                handleKeyDown: (view, event) => {
                    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                        this.$emit('submit');
                        return true;
                    }
                    return false;
                },
            },
            onUpdate: () => {
                this.sync();
                this.$emit('update:modelValue', this.editor.isEmpty ? '' : this.editor.getHTML());
            },
            onFocus: () => {
                this.focused = true;
                this.$emit('focus');
            },
            onBlur: () => {
                this.focused = false;
                this.$emit('blur');
            },
        });
        this.sync();
    },
    beforeUnmount() {
        this.editor?.destroy();
    },
    methods: {
        sync() {
            this.isEmpty = this.editor.isEmpty;
            this.characters = this.editor.storage.characterCount.characters();
        },
        focus() {
            if (!this.disabled) this.editor?.commands.focus('end');
        },
        clear() {
            this.editor?.commands.clearContent(true);
        },
    },
};
</script>

<style scoped>
.simple-editor {
    border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    border-radius: 8px;
    background: rgb(var(--v-theme-surface));
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    cursor: text;
}

.simple-editor--focused {
    border-color: rgb(var(--v-theme-primary));
    box-shadow: 0 0 0 1px rgb(var(--v-theme-primary));
}

.simple-editor--disabled {
    opacity: 0.6;
    cursor: default;
}

.simple-editor__body {
    position: relative;
    padding: 10px 12px;
}

.simple-editor__placeholder {
    position: absolute;
    top: 10px;
    left: 12px;
    right: 12px;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    pointer-events: none;
}

.simple-editor__content:focus {
    outline: none;
}

.simple-editor :deep(.simple-editor__content p) {
    margin: 0 0 6px;
}

.simple-editor :deep(.simple-editor__content p:last-child) {
    margin-bottom: 0;
}

.simple-editor :deep(.simple-editor__content ul) {
    padding-left: 20px;
    margin: 4px 0;
}

.simple-editor__toolbar {
    border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    min-height: 36px;
}
</style>
