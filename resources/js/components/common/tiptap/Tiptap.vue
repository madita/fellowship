<template>
    <div class="tiptap-editor">
        <!-- Toolbar -->
        <div v-if="editor && type === 'full'" class="editor-toolbar">
            <!-- Text Formatting Group -->
            <div class="toolbar-group">
                <v-tooltip text="Bold" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('bold') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleBold().run()"
                        >
                            <v-icon>mdi-format-bold</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Italic" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('italic') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleItalic().run()"
                        >
                            <v-icon>mdi-format-italic</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Strike Through" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('strike') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleStrike().run()"
                        >
                            <v-icon>mdi-format-strikethrough-variant</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Code" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('code') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleCode().run()"
                        >
                            <v-icon>mdi-code-tags</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Clear Formatting" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().clearNodes().run()"
                        >
                            <v-icon>mdi-format-clear</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>
            </div>

            <v-divider vertical class="toolbar-divider" />

            <!-- Headings Dropdown -->
            <div class="toolbar-group">
                <v-menu>
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            variant="text"
                            size="small"
                            append-icon="mdi-chevron-down"
                        >
                            <v-icon class="mr-1">mdi-format-header-pound</v-icon>
                            Heading
                        </v-btn>
                    </template>
                    <v-list density="compact">
                        <v-list-item
                            :class="{ 'is-active': editor.isActive('paragraph') }"
                            @click="editor.chain().focus().setParagraph().run()"
                        >
                            <template v-slot:prepend>
                                <v-icon>mdi-format-pilcrow</v-icon>
                            </template>
                            <v-list-item-title>Paragraph</v-list-item-title>
                        </v-list-item>
                        <v-list-item
                            v-for="level in 6"
                            :key="level"
                            :class="{ 'is-active': editor.isActive('heading', { level }) }"
                            @click="editor.chain().focus().toggleHeading({ level }).run()"
                        >
                            <template v-slot:prepend>
                                <v-icon>{{ `mdi-format-header-${level}` }}</v-icon>
                            </template>
                            <v-list-item-title>Heading {{ level }}</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </div>

            <v-divider vertical class="toolbar-divider" />

            <!-- Lists and Blocks -->
            <div class="toolbar-group">
                <v-tooltip text="Bullet List" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('bulletList') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleBulletList().run()"
                        >
                            <v-icon>mdi-format-list-bulleted</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Numbered List" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('orderedList') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleOrderedList().run()"
                        >
                            <v-icon>mdi-format-list-numbered</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Quote" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('blockquote') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleBlockquote().run()"
                        >
                            <v-icon>mdi-format-quote-close</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Code Block" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('codeBlock') }"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().toggleCodeBlock().run()"
                        >
                            <v-icon>mdi-code-braces</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>
            </div>

            <v-divider vertical class="toolbar-divider" />

            <!-- Links and Media -->
            <div class="toolbar-group">
                <v-tooltip text="Add Link" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :class="{ 'is-active': editor.isActive('link') }"
                            icon
                            size="small"
                            variant="text"
                            @click="setLink"
                        >
                            <v-icon>mdi-link</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Remove Link" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :disabled="!editor.isActive('link')"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().unsetLink().run()"
                        >
                            <v-icon>mdi-link-off</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Add Image" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            icon
                            size="small"
                            variant="text"
                            @click="addImage"
                        >
                            <v-icon>mdi-image</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>
            </div>

            <v-divider vertical class="toolbar-divider" />

            <!-- Table Dropdown -->
            <div class="toolbar-group">
                <v-menu>
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            variant="text"
                            size="small"
                            append-icon="mdi-chevron-down"
                        >
                            <v-icon class="mr-1">mdi-table</v-icon>
                            Table
                        </v-btn>
                    </template>
                    <v-list density="compact" class="table-menu">
                        <!-- Insert Table -->
                        <v-list-item @click="insertTable">
                            <template v-slot:prepend>
                                <v-icon>mdi-table-plus</v-icon>
                            </template>
                            <v-list-item-title>Insert Table</v-list-item-title>
                        </v-list-item>

                        <v-divider v-if="editor.isActive('table')" />

                        <!-- Column Operations -->
                        <template v-if="editor.isActive('table')">
                            <v-list-subheader>Columns</v-list-subheader>
                            <v-list-item @click="editor.chain().focus().addColumnBefore().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-column-plus-before</v-icon>
                                </template>
                                <v-list-item-title>Add Column Before</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="editor.chain().focus().addColumnAfter().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-column-plus-after</v-icon>
                                </template>
                                <v-list-item-title>Add Column After</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="editor.chain().focus().deleteColumn().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-column-remove</v-icon>
                                </template>
                                <v-list-item-title>Delete Column</v-list-item-title>
                            </v-list-item>

                            <v-divider />

                            <!-- Row Operations -->
                            <v-list-subheader>Rows</v-list-subheader>
                            <v-list-item @click="editor.chain().focus().addRowBefore().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-row-plus-before</v-icon>
                                </template>
                                <v-list-item-title>Add Row Before</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="editor.chain().focus().addRowAfter().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-row-plus-after</v-icon>
                                </template>
                                <v-list-item-title>Add Row After</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="editor.chain().focus().deleteRow().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-row-remove</v-icon>
                                </template>
                                <v-list-item-title>Delete Row</v-list-item-title>
                            </v-list-item>

                            <v-divider />

                            <!-- Cell Operations -->
                            <v-list-subheader>Cells</v-list-subheader>
                            <v-list-item @click="editor.chain().focus().mergeCells().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-merge-cells</v-icon>
                                </template>
                                <v-list-item-title>Merge Cells</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="editor.chain().focus().splitCell().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-split-cell</v-icon>
                                </template>
                                <v-list-item-title>Split Cell</v-list-item-title>
                            </v-list-item>
                            <v-list-item @click="editor.chain().focus().toggleHeaderRow().run()">
                                <template v-slot:prepend>
                                    <v-icon>mdi-table-headers-eye</v-icon>
                                </template>
                                <v-list-item-title>Toggle Header Row</v-list-item-title>
                            </v-list-item>

                            <v-divider />

                            <!-- Delete Table -->
                            <v-list-item @click="editor.chain().focus().deleteTable().run()" class="text-error">
                                <template v-slot:prepend>
                                    <v-icon color="error">mdi-table-remove</v-icon>
                                </template>
                                <v-list-item-title>Delete Table</v-list-item-title>
                            </v-list-item>
                        </template>
                    </v-list>
                </v-menu>
            </div>

            <v-divider vertical class="toolbar-divider" />

            <!-- Utility Actions -->
            <div class="toolbar-group">
                <v-tooltip text="Horizontal Rule" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().setHorizontalRule().run()"
                        >
                            <v-icon>mdi-minus</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Line Break" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().setHardBreak().run()"
                        >
                            <v-icon>mdi-keyboard-return</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>
            </div>

            <v-spacer />

            <!-- Undo/Redo -->
            <div class="toolbar-group">
                <v-tooltip text="Undo" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :disabled="!editor.can().undo()"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().undo().run()"
                        >
                            <v-icon>mdi-undo</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>

                <v-tooltip text="Redo" location="bottom">
                    <template v-slot:activator="{ props }">
                        <v-btn
                            v-bind="props"
                            :disabled="!editor.can().redo()"
                            icon
                            size="small"
                            variant="text"
                            @click="editor.chain().focus().redo().run()"
                        >
                            <v-icon>mdi-redo</v-icon>
                        </v-btn>
                    </template>
                </v-tooltip>
            </div>
        </div>

        <!-- Editor Content -->
        <div class="editor-content-wrapper">
            <editor-content :editor="editor" />
        </div>

        <!-- Bubble Menu -->
        <bubble-menu
            :editor="editor"
            :tippy-options="{ duration: 100, placement: 'top' }"
            v-if="editor"
            class="bubble-menu"
        >
            <v-card class="bubble-menu-content" elevation="8">
                <div class="d-flex pa-1">
                    <v-btn
                        :class="{ 'is-active': editor.isActive('bold') }"
                        icon
                        size="small"
                        variant="text"
                        @click="editor.chain().focus().toggleBold().run()"
                    >
                        <v-icon size="16">mdi-format-bold</v-icon>
                    </v-btn>
                    <v-btn
                        :class="{ 'is-active': editor.isActive('italic') }"
                        icon
                        size="small"
                        variant="text"
                        @click="editor.chain().focus().toggleItalic().run()"
                    >
                        <v-icon size="16">mdi-format-italic</v-icon>
                    </v-btn>
                    <v-btn
                        :class="{ 'is-active': editor.isActive('strike') }"
                        icon
                        size="small"
                        variant="text"
                        @click="editor.chain().focus().toggleStrike().run()"
                    >
                        <v-icon size="16">mdi-format-strikethrough-variant</v-icon>
                    </v-btn>
                    <v-btn
                        :class="{ 'is-active': editor.isActive('link') }"
                        icon
                        size="small"
                        variant="text"
                        @click="setLink"
                    >
                        <v-icon size="16">mdi-link</v-icon>
                    </v-btn>
                </div>
            </v-card>
        </bubble-menu>

        <!-- Character Count -->
        <div
            v-if="editor && limit"
            :class="[
        'character-count',
        { 'character-count--warning': editor.storage.characterCount.characters() >= limit * 0.9 },
        { 'character-count--error': editor.storage.characterCount.characters() >= limit }
      ]"
        >
            <div class="d-flex align-center">
                <v-progress-circular
                    :model-value="percentage"
                    :color="percentage >= 90 ? 'error' : percentage >= 75 ? 'warning' : 'primary'"
                    size="20"
                    width="2"
                    class="mr-2"
                />
                <span class="text-caption">
          {{ editor.storage.characterCount.characters() }}/{{ limit }} characters
        </span>
            </div>
        </div>

        <!-- Link Dialog -->
        <v-dialog v-model="showLinkDialog" max-width="400">
            <v-card>
                <v-card-title>Add Link</v-card-title>
                <v-card-text>
                    <v-text-field
                        v-model="linkUrl"
                        label="URL"
                        placeholder="https://example.com"
                        variant="outlined"
                        density="compact"
                        autofocus
                        @keyup.enter="confirmLink"
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn @click="showLinkDialog = false">Cancel</v-btn>
                    <v-btn color="primary" @click="confirmLink">Add Link</v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Media Picker Modal -->
        <MediaPickerModal
            v-model="showMediaPicker"
            @select="onMediaSelect"
        />
    </div>
</template>

<script setup>
import { ref, watch, computed, onBeforeUnmount } from 'vue';
import { BubbleMenu, useEditor, EditorContent } from '@tiptap/vue-3';
import CharacterCount from '@tiptap/extension-character-count';
import StarterKit from '@tiptap/starter-kit';
import Table from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';

// Import your custom mention extensions
import WikiMention from './mention/WikiMention.js'
import CustomMention from './mention/CustomMention.js'
import HashtagMention from './mention/HashtagMention.js'
import suggestion from './mention/suggestion.js'
import hashtag from './mention/hashtag.js'
import wiki from './mention/wiki.js'
import MediaPickerModal from './MediaPickerModal.vue'

const props = defineProps({
    type: {
        type: String,
        default: 'full',
    },
    modelValue: {
        type: String,
        default: '',
    },
    limit: {
        type: Number,
        default: null
    }
});

const emit = defineEmits(['update:modelValue']);

const showLinkDialog = ref(false);
const showMediaPicker = ref(false);
const linkUrl = ref('');

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableHeader,
        TableCell,
        Image.configure({
            inline: false,
            allowBase64: true
        }),
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'editor-link',
            },
        }),
        CharacterCount.configure({
            limit: props.limit,
        }),
        CustomMention.extend({
            name: "mention",
        }).configure({
            HTMLAttributes: {
                class: 'mention',
            },
            suggestion,
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose prose-sm sm:prose lg:prose-lg xl:prose-2xl mx-auto focus:outline-none',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

const percentage = computed(() => {
    if (!props.limit || !editor.value) return 0;
    return Math.round((100 / props.limit) * editor.value.storage.characterCount.characters());
});

const setLink = () => {
    const previousUrl = editor.value.getAttributes('link').href;
    linkUrl.value = previousUrl || '';
    showLinkDialog.value = true;
};

const confirmLink = () => {
    if (linkUrl.value) {
        editor.value
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: linkUrl.value })
            .run();
    } else {
        editor.value.chain().focus().unsetLink().run();
    }
    showLinkDialog.value = false;
    linkUrl.value = '';
};

const addImage = () => {
    showMediaPicker.value = true;
};

const onMediaSelect = (imageData) => {
    if (imageData.src) {
        editor.value
            .chain()
            .focus()
            .setImage({
                src: imageData.src,
                alt: imageData.alt || null
            })
            .run();
    }
};

const insertTable = () => {
    editor.value
        .chain()
        .focus()
        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
        .run();
};

const focusEditor = () => {
    // editor.commands.focus()
    editor.value
        .chain()
        .focus()
    // editor.value?.commands.focus()
}

watch(() => props.modelValue, (value) => {
    if (!editor.value) return;

    const isSame = editor.value.getHTML() === value;
    if (isSame) return;

    editor.value.commands.setContent(value, false);
});

onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy();
    }
});

defineExpose({ focusEditor });
</script>

<style scoped>
.tiptap-editor {
    border: 1px solid rgb(var(--v-border-color));
    border-radius: 8px;
    background: rgb(var(--v-theme-surface));
    overflow: hidden;
}

.editor-toolbar {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: rgb(var(--v-theme-surface-variant));
    border-bottom: 1px solid rgb(var(--v-border-color));
    flex-wrap: wrap;
    gap: 4px;
}

.toolbar-group {
    display: flex;
    align-items: center;
    gap: 2px;
}

.toolbar-divider {
    height: 24px;
    margin: 0 8px;
    opacity: 0.3;
}

.editor-content-wrapper {
    min-height: 200px;
    max-height: 500px;
    overflow-y: auto;
}

.bubble-menu-content {
    border-radius: 8px !important;
}

.character-count {
    padding: 8px 12px;
    background: rgb(var(--v-theme-surface-variant));
    border-top: 1px solid rgb(var(--v-border-color));
    display: flex;
    justify-content: flex-end;
}

.character-count--warning {
    background: rgba(var(--v-theme-warning), 0.1);
}

.character-count--error {
    background: rgba(var(--v-theme-error), 0.1);
}

.v-btn.is-active {
    background: rgb(var(--v-theme-primary)) !important;
    color: rgb(var(--v-theme-on-primary)) !important;
}

.table-menu {
    max-width: 250px;
}

/* Responsive toolbar */
@media (max-width: 768px) {
    .editor-toolbar {
        flex-wrap: wrap;
        padding: 6px 8px;
    }

    .toolbar-divider {
        display: none;
    }

    .toolbar-group {
        margin-bottom: 4px;
    }
}
</style>

<style>
/* ProseMirror Editor Styles */
.ProseMirror {
    padding: 16px;
    outline: none;
    min-height: 200px;
    max-height: 500px;
    overflow-y: auto;
    line-height: 1.6;
    color: rgb(var(--v-theme-on-surface));
    caret-color: rgb(var(--v-theme-primary));
}

.ProseMirror:focus {
    outline: none;
    border: none;
}

.ProseMirror > * + * {
    margin-top: 0.75em;
}

.ProseMirror h1,
.ProseMirror h2,
.ProseMirror h3,
.ProseMirror h4,
.ProseMirror h5,
.ProseMirror h6 {
    line-height: 1.1;
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    font-weight: 600;
}

.ProseMirror h1 {
    font-size: 2rem;
}

.ProseMirror h2 {
    font-size: 1.75rem;
}

.ProseMirror h3 {
    font-size: 1.5rem;
}

.ProseMirror h4 {
    font-size: 1.25rem;
}

.ProseMirror h5 {
    font-size: 1.125rem;
}

.ProseMirror h6 {
    font-size: 1rem;
}

.ProseMirror ul,
.ProseMirror ol {
    padding: 0 1rem;
    margin: 1rem 0;
}

.ProseMirror li {
    margin: 0.25rem 0;
}

.ProseMirror code {
    background-color: rgba(var(--v-theme-surface-variant), 0.8);
    color: rgb(var(--v-theme-primary));
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.ProseMirror pre {
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-on-surface));
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    padding: 1rem;
    border-radius: 8px;
    overflow-x: auto;
    margin: 1rem 0;
}

.ProseMirror pre code {
    color: inherit;
    padding: 0;
    background: none;
    font-size: 0.875rem;
}

.ProseMirror img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 1rem 0;
}

.ProseMirror blockquote {
    padding-left: 1rem;
    border-left: 4px solid rgb(var(--v-theme-primary));
    margin: 1rem 0;
    font-style: italic;
    background: rgba(var(--v-theme-primary), 0.05);
    padding: 1rem;
    border-radius: 0 8px 8px 0;
}

.ProseMirror hr {
    border: none;
    border-top: 2px solid rgba(var(--v-border-color), 0.5);
    margin: 2rem 0;
}

/* Table Styles */
.ProseMirror table {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin: 1rem 0;
    overflow: hidden;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.ProseMirror td,
.ProseMirror th {
    min-width: 1em;
    border: 1px solid rgb(var(--v-border-color));
    padding: 8px 12px;
    vertical-align: top;
    box-sizing: border-box;
    position: relative;
    background: rgb(var(--v-theme-surface));
}

.ProseMirror th {
    font-weight: 600;
    text-align: left;
    background: rgb(var(--v-theme-surface-variant));
    color: rgb(var(--v-theme-on-surface-variant));
}

.ProseMirror .selectedCell:after {
    z-index: 2;
    position: absolute;
    content: "";
    left: 0;
    right: 0;
    top: 0;
    bottom: 0;
    background: rgba(var(--v-theme-primary), 0.2);
    pointer-events: none;
}

.ProseMirror .column-resize-handle {
    position: absolute;
    right: -2px;
    top: 0;
    bottom: -2px;
    width: 4px;
    background-color: rgb(var(--v-theme-primary));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
}

.ProseMirror table:hover .column-resize-handle {
    opacity: 0.5;
}

.ProseMirror .tableWrapper {
    overflow-x: auto;
    margin: 1rem 0;
}

.ProseMirror .resize-cursor {
    cursor: ew-resize;
    cursor: col-resize;
}

.ProseMirror p {
    margin: 0.5rem 0;
}

.ProseMirror p:first-child {
    margin-top: 0;
}

.ProseMirror p:last-child {
    margin-bottom: 0;
}

/* Link Styles */
.ProseMirror .editor-link {
    color: rgb(var(--v-theme-primary));
    text-decoration: underline;
    text-decoration-color: rgba(var(--v-theme-primary), 0.4);
    text-underline-offset: 2px;
    transition: all 0.2s;
}

.ProseMirror .editor-link:hover {
    text-decoration-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.1);
    padding: 0 2px;
    border-radius: 4px;
}

/* Mention Styles */
.ProseMirror .mention {
    background: rgba(var(--v-theme-primary), 0.1);
    border: 1px solid rgba(var(--v-theme-primary), 0.3);
    border-radius: 6px;
    padding: 0.1rem 0.4rem;
    color: rgb(var(--v-theme-primary));
    font-weight: 500;
    text-decoration: none;
    box-decoration-break: clone;
    transition: all 0.2s;
}

.ProseMirror .mention:hover {
    background: rgba(var(--v-theme-primary), 0.2);
    border-color: rgb(var(--v-theme-primary));
}

.ProseMirror .hashtag {
    background: rgba(var(--v-theme-secondary), 0.1);
    border: 1px dashed rgba(var(--v-theme-secondary), 0.5);
    border-radius: 6px;
    padding: 0.1rem 0.4rem;
    color: rgb(var(--v-theme-secondary));
    font-weight: 500;
    box-decoration-break: clone;
    transition: all 0.2s;
}

.ProseMirror .hashtag:hover {
    background: rgba(var(--v-theme-secondary), 0.2);
    border-color: rgb(var(--v-theme-secondary));
}

.ProseMirror .wiki {
    background: rgba(var(--v-theme-info), 0.1);
    border: 1px solid rgba(var(--v-theme-info), 0.3);
    border-radius: 6px;
    padding: 0.1rem 0.4rem;
    color: rgb(var(--v-theme-info));
    font-weight: 500;
    box-decoration-break: clone;
    transition: all 0.2s;
}

.ProseMirror .wiki:hover {
    background: rgba(var(--v-theme-info), 0.2);
    border-color: rgb(var(--v-theme-info));
}

/* Placeholder */
.ProseMirror p.is-editor-empty:first-child::before {
    content: attr(data-placeholder);
    float: left;
    color: rgba(var(--v-theme-on-surface), 0.4);
    pointer-events: none;
    height: 0;
}

/* Selection */
.ProseMirror ::selection {
    background: rgba(var(--v-theme-primary), 0.2);
}

/* Focus styles */
.ProseMirror:focus-visible {
    outline: 2px solid rgb(var(--v-theme-primary));
    outline-offset: -2px;
    border-radius: 4px;
}

/* Scroll styling */
.ProseMirror::-webkit-scrollbar {
    width: 6px;
}

.ProseMirror::-webkit-scrollbar-track {
    background: transparent;
}

.ProseMirror::-webkit-scrollbar-thumb {
    background: rgba(var(--v-theme-on-surface), 0.2);
    border-radius: 3px;
}

.ProseMirror::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--v-theme-on-surface), 0.3);
}

/* Animation for content changes */
.ProseMirror * {
    transition: margin 0.2s ease, padding 0.2s ease;
}

/* Print styles */
@media print {
    .ProseMirror {
        max-height: none;
        overflow: visible;
    }

    .ProseMirror table {
        box-shadow: none;
        border: 1px solid #000;
    }

    .ProseMirror .mention,
    .ProseMirror .hashtag,
    .ProseMirror .wiki {
        color: #000 !important;
        background: transparent !important;
        border-color: #000 !important;
    }
}

/* Dark theme adjustments */
@media (prefers-color-scheme: dark) {
    .ProseMirror {
        caret-color: rgb(var(--v-theme-primary));
    }

    .ProseMirror table {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }
}

/* Mobile responsive */
@media (max-width: 768px) {
    .ProseMirror {
        padding: 12px;
        font-size: 16px; /* Prevent zoom on iOS */
    }

    .ProseMirror table {
        font-size: 14px;
    }

    .ProseMirror td,
    .ProseMirror th {
        padding: 6px 8px;
    }
}

</style>
