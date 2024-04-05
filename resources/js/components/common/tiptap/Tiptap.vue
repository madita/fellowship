<template>
    <div class="tiptap">
<!--        <ImageModal ref="ytmodal" @onConfirm="addCommand" />-->
        <div v-if="editor && type==='full'">
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
            <button @click="setLink" :class="{ 'is-active': editor.isActive('link') }">
                <v-icon>mdi-link</v-icon>
            </button>
            <button @click="editor.chain().focus().unsetLink().run()" :disabled="!editor.isActive('link')">
                <v-icon>mdi-link-off</v-icon>
            </button>
            <button @click="openModal();">
                <svg width="14" height="14" viewBox="0 0 58 58">
                    <path
                        d="M57 6H1a1 1 0 0 0-1 1v44c0 .6.4 1 1 1h56c.6 0 1-.4 1-1V7c0-.6-.4-1-1-1zm-1 44H2V8h54v42z"
                    />
                    <path
                        d="M16 28.1A5.6 5.6 0 1 0 16 17 5.6 5.6 0 0 0 16 28zm0-9.1a3.6 3.6 0 1 1 0 7.1 3.6 3.6 0 0 1 0-7.1zM7 46c.2 0 .5 0 .7-.2L24 31.4l10.3 10.3a1 1 0 1 0 1.4-1.4l-4.8-4.8 9.2-10 11.2 10.2a1 1 0 0 0 1.4-1.4l-12-11a1 1 0 0 0-1.4 0l-9.8 10.8-4.8-4.8a1 1 0 0 0-1.3 0l-17 15A1 1 0 0 0 7 46z"
                    />
                </svg>
            </button>
        </div>

        <div v-if="editor && type==='full'">
            <button @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM5 14v4h6v-4H5Zm14 0h-6v4h6v-4Zm0-6h-6v4h6V8ZM5 12h6V8H5v4Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().addColumnBefore().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 2 0v1h8V6H5v1a1 1 0 1 1-2 0V5c0-.6.4-1 1-1h15Zm0 9h-4v5h4v-5ZM8 8c.5 0 1 .4 1 .9V11h2a1 1 0 0 1 .1 2H9v2a1 1 0 0 1-2 .1V13H5a1 1 0 0 1-.1-2H7V9c0-.6.4-1 1-1Zm11-2h-4v5h4V6Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().addColumnAfter().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M20 4c.6 0 1 .4 1 1v2a1 1 0 0 1-2 0V6h-8v12h8v-1a1 1 0 0 1 2 0v2c0 .5-.4 1-.9 1H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h15ZM9 13H5v5h4v-5Zm7-5c.5 0 1 .4 1 .9V11h2a1 1 0 0 1 .1 2H17v2a1 1 0 0 1-2 .1V13h-2a1 1 0 0 1-.1-2H15V9c0-.6.4-1 1-1ZM9 6H5v5h4V6Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().deleteColumn().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14Zm0 2H5v3h2.5v2H5v2h2.5v2H5v3h14v-3h-2.5v-2H19v-2h-2.5V9H19V6Zm-4.7 1.8 1.2 1L13 12l2.6 3.3-1.2 1-2.3-3-2.3 3-1.2-1L11 12 8.5 8.7l1.2-1 2.3 3 2.3-3Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().addRowBefore().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M6 4a1 1 0 1 1 0 2H5v6h14V6h-1a1 1 0 0 1 0-2h2c.6 0 1 .4 1 1v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5c0-.6.4-1 1-1h2Zm5 10H5v4h6v-4Zm8 0h-6v4h6v-4ZM12 3c.5 0 1 .4 1 .9V6h2a1 1 0 0 1 0 2h-2v2a1 1 0 0 1-2 .1V8H9a1 1 0 0 1 0-2h2V4c0-.6.4-1 1-1Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().addRowAfter().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M12 13c.5 0 1 .4 1 .9V16h2a1 1 0 0 1 .1 2H13v2a1 1 0 0 1-2 .1V18H9a1 1 0 0 1-.1-2H11v-2c0-.6.4-1 1-1Zm6 7a1 1 0 0 1 0-2h1v-6H5v6h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1V6c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v13c0 .5-.4 1-.9 1H18ZM11 6H5v4h6V6Zm8 0h-6v4h6V6Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().deleteRow().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M12 13c.5 0 1 .4 1 .9V16h2a1 1 0 0 1 .1 2H13v2a1 1 0 0 1-2 .1V18H9a1 1 0 0 1-.1-2H11v-2c0-.6.4-1 1-1Zm6 7a1 1 0 0 1 0-2h1v-6H5v6h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1V6c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v13c0 .5-.4 1-.9 1H18ZM11 6H5v4h6V6Zm8 0h-6v4h6V6Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().deleteTable().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><g fill-rule="nonzero"><path d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM5 6v12h14V6H5Z"></path><path d="m14.4 8.6 1.1 1-2.4 2.4 2.4 2.4-1.1 1.1-2.4-2.4-2.4 2.4-1-1.1 2.3-2.4-2.3-2.4 1-1 2.4 2.3z"></path></g></svg>
            </button>
            <button @click="editor.chain().focus().mergeCells().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM5 15.5V18h3v-2.5H5Zm14-5h-9V18h9v-7.5ZM19 6h-4v2.5h4V6ZM8 6H5v2.5h3V6Zm5 0h-3v2.5h3V6Zm-8 7.5h3v-3H5v3Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().splitCell().run()">
                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM8 15.5H5V18h3v-2.5Zm11-5h-9V18h9v-7.5Zm-2.5 1 1 1-2 2 2 2-1 1-2-2-2 2-1-1 2-2-2-2 1-1 2 2 2-2Zm-8.5-1H5v3h3v-3ZM19 6h-4v2.5h4V6ZM8 6H5v2.5h3V6Zm5 0h-3v2.5h3V6Z"></path></svg>
            </button>
            <button @click="editor.chain().focus().toggleHeaderColumn().run()">
                toggleHeaderColumn
            </button>
            <button @click="editor.chain().focus().toggleHeaderRow().run()">
                toggleHeaderRow
            </button>
            <button @click="editor.chain().focus().toggleHeaderCell().run()">
                toggleHeaderCell
            </button>
            <button @click="editor.chain().focus().mergeOrSplit().run()">
                mergeOrSplit
            </button>
            <button @click="editor.chain().focus().setCellAttribute('colspan', 2).run()">
                setCellAttribute
            </button>
            <button @click="editor.chain().focus().fixTables().run()">
                fixTables
            </button>
            <button @click="editor.chain().focus().goToNextCell().run()">
                goToNextCell
            </button>
            <button @click="editor.chain().focus().goToPreviousCell().run()">
                goToPreviousCell
            </button>
        </div>



        <div v-if="editor">
            <editor-content :editor="editor"/>
        </div>

        <bubble-menu
            :editor="editor"
            :tippy-options="{ duration: 100 }"
            v-if="editor"
        >
            <button @click="editor.chain().focus().toggleBold().run()" :class="{ 'is-active': editor.isActive('bold') }">
                <v-icon>mdi-format-bold</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleItalic().run()" :class="{ 'is-active': editor.isActive('italic') }">
                <v-icon>mdi-format-italic</v-icon>
            </button>
            <button @click="editor.chain().focus().toggleStrike().run()" :class="{ 'is-active': editor.isActive('strike') }">
                <v-icon>mdi-format-strikethrough-variant</v-icon>
            </button>
        </bubble-menu>


<!--        <bubble-menu-->
<!--            :editor="editor"-->
<!--            :tippy-options="{ duration: 100 }"-->
<!--            v-if="editor.isActive('table')"-->
<!--            :-->
<!--        >-->
<!--            <button @click="editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM5 14v4h6v-4H5Zm14 0h-6v4h6v-4Zm0-6h-6v4h6V8ZM5 12h6V8H5v4Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().addColumnBefore().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-2a1 1 0 0 1 2 0v1h8V6H5v1a1 1 0 1 1-2 0V5c0-.6.4-1 1-1h15Zm0 9h-4v5h4v-5ZM8 8c.5 0 1 .4 1 .9V11h2a1 1 0 0 1 .1 2H9v2a1 1 0 0 1-2 .1V13H5a1 1 0 0 1-.1-2H7V9c0-.6.4-1 1-1Zm11-2h-4v5h4V6Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().addColumnAfter().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M20 4c.6 0 1 .4 1 1v2a1 1 0 0 1-2 0V6h-8v12h8v-1a1 1 0 0 1 2 0v2c0 .5-.4 1-.9 1H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h15ZM9 13H5v5h4v-5Zm7-5c.5 0 1 .4 1 .9V11h2a1 1 0 0 1 .1 2H17v2a1 1 0 0 1-2 .1V13h-2a1 1 0 0 1-.1-2H15V9c0-.6.4-1 1-1ZM9 6H5v5h4V6Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().deleteColumn().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14Zm0 2H5v3h2.5v2H5v2h2.5v2H5v3h14v-3h-2.5v-2H19v-2h-2.5V9H19V6Zm-4.7 1.8 1.2 1L13 12l2.6 3.3-1.2 1-2.3-3-2.3 3-1.2-1L11 12 8.5 8.7l1.2-1 2.3 3 2.3-3Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().addRowBefore().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M6 4a1 1 0 1 1 0 2H5v6h14V6h-1a1 1 0 0 1 0-2h2c.6 0 1 .4 1 1v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5c0-.6.4-1 1-1h2Zm5 10H5v4h6v-4Zm8 0h-6v4h6v-4ZM12 3c.5 0 1 .4 1 .9V6h2a1 1 0 0 1 0 2h-2v2a1 1 0 0 1-2 .1V8H9a1 1 0 0 1 0-2h2V4c0-.6.4-1 1-1Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().addRowAfter().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M12 13c.5 0 1 .4 1 .9V16h2a1 1 0 0 1 .1 2H13v2a1 1 0 0 1-2 .1V18H9a1 1 0 0 1-.1-2H11v-2c0-.6.4-1 1-1Zm6 7a1 1 0 0 1 0-2h1v-6H5v6h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1V6c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v13c0 .5-.4 1-.9 1H18ZM11 6H5v4h6V6Zm8 0h-6v4h6V6Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().deleteRow().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M12 13c.5 0 1 .4 1 .9V16h2a1 1 0 0 1 .1 2H13v2a1 1 0 0 1-2 .1V18H9a1 1 0 0 1-.1-2H11v-2c0-.6.4-1 1-1Zm6 7a1 1 0 0 1 0-2h1v-6H5v6h1a1 1 0 0 1 0 2H4a1 1 0 0 1-1-1V6c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v13c0 .5-.4 1-.9 1H18ZM11 6H5v4h6V6Zm8 0h-6v4h6V6Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().deleteTable().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><g fill-rule="nonzero"><path d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM5 6v12h14V6H5Z"></path><path d="m14.4 8.6 1.1 1-2.4 2.4 2.4 2.4-1.1 1.1-2.4-2.4-2.4 2.4-1-1.1 2.3-2.4-2.3-2.4 1-1 2.4 2.3z"></path></g></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().mergeCells().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM5 15.5V18h3v-2.5H5Zm14-5h-9V18h9v-7.5ZM19 6h-4v2.5h4V6ZM8 6H5v2.5h3V6Zm5 0h-3v2.5h3V6Zm-8 7.5h3v-3H5v3Z"></path></svg>-->
<!--            </button>-->
<!--            <button @click="editor.chain().focus().splitCell().run()">-->
<!--                <svg fill="#757575" width="24" height="24" focusable="false"><path fill-rule="nonzero" d="M19 4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6c0-1.1.9-2 2-2h14ZM8 15.5H5V18h3v-2.5Zm11-5h-9V18h9v-7.5Zm-2.5 1 1 1-2 2 2 2-1 1-2-2-2 2-1-1 2-2-2-2 1-1 2 2 2-2Zm-8.5-1H5v3h3v-3ZM19 6h-4v2.5h4V6ZM8 6H5v2.5h3V6Zm5 0h-3v2.5h3V6Z"></path></svg>-->
<!--            </button>-->
<!--        </bubble-menu>-->

        <editor-content :editor="editor"/>

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
import { ref, watch, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { BubbleMenu, useEditor, Editor, EditorContent } from '@tiptap/vue-3';

import CharacterCount from '@tiptap/extension-character-count'

import WikiMention from './mention/WikiMention.js'
import CustomMention from './mention/CustomMention.js'
import HashtagMention from './mention/HashtagMention.js'

import suggestion from './mention/suggestion.js'
import hashtag from './mention/hashtag.js'
import wiki from './mention/wiki.js'

import StarterKit from '@tiptap/starter-kit'
import Document from '@tiptap/extension-document'
import Gapcursor from '@tiptap/extension-gapcursor'
import Paragraph from '@tiptap/extension-paragraph'
import Table from '@tiptap/extension-table'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import TableRow from '@tiptap/extension-table-row'
import Text from '@tiptap/extension-text'
import Link from '@tiptap/extension-link'
import Image from '@tiptap/extension-image'


export default {
    components: {
        StarterKit,
        EditorContent,
        BubbleMenu,
        Table,
        TableCell,
        TableHeader,
        TableRow,
        Text,
        Paragraph,
        Image,
        Link,


        // ... other components
    },

    // props: ['value', 'limit'],
    // emits: ['update:content'],

    props: {
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
            default:null
        }
    },

    emits: ['update:modelValue'],

    setup(props, { emit }) {


        const html = ref("");

        const editor = useEditor({
            content: props.modelValue,
            extensions: [
                StarterKit,
                Table,
                TableCell,
                TableHeader,
                TableRow,
                Image.configure({ inline: true }),
                Link.configure({
                    openOnClick: false,
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
            onUpdate: ({ editor  }) => {

                emit('update:modelValue', editor.getHTML());
                // editor.commands.setContent(editor.getHTML(), false, {preserveWhitespace: "full"});
            },
        });


        watch(() => props.modelValue, (value) => {

            const isSame = editor.value.getHTML() === value
            // const isSame = JSON.stringify(this.editor.getJSON()) === JSON.stringify(value)

            if (isSame) {
                return
            }

            editor.value.commands.setContent(value, false)
            // const isSame = this.editor.getHTML() === value

            // JSON

            // this.editor.commands.setContent(value, false)
        });

        const openModal = (command) => {
            // ... logic remains unchanged
            // this.$refs.ytmodal.showModal(command);
        };

        const addCommand = (data) => {
            if (data.command !== null) {
                data.command(data.data);
            }
        };

        const setContent = () => {
             // editor.setContent(this.content);
        };

        // ... other methods

        onMounted(() => {

            // console.log('tiptap', form)

            // editor.value = new Editor({
            //     // ... the rest of your editor configuration
            //     onUpdate: () => {
            //         emit('input', editor.value.getHTML());
            //     }
            // });

            // html.value = editor.value.getHTML();
        });

        const percentage = computed(() => {
            return Math.round((100 / props.limit) * editor.value.storage.characterCount.characters());
        });

        onBeforeUnmount(() => {
            editor.value.destroy();
        });

        return {
            editor,
            html,
            openModal,
            addCommand,
            // ... other methods
            percentage
        };
    }


}

</script>

<style lang="scss">
/* Basic editor styles */
.ProseMirror {
    > * + * {
        margin-top: 0.75em;
    }

    min-height: 200px;
    max-height: 500px;
    overflow-y: scroll;

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

    table {
        border-collapse: collapse;
        table-layout: fixed;
        width: 100%;
        margin: 0;
        overflow: hidden;

        td,
        th {
            min-width: 1em;
            border: 2px solid #ced4da;
            padding: 3px 5px;
            vertical-align: top;
            box-sizing: border-box;
            position: relative;

            > * {
                margin-bottom: 0;
            }
        }

        th {
            font-weight: bold;
            text-align: left;
            background-color: #f1f3f5;
        }

        .selectedCell:after {
            z-index: 2;
            position: absolute;
            content: "";
            left: 0; right: 0; top: 0; bottom: 0;
            background: rgba(200, 200, 255, 0.4);
            pointer-events: none;
        }

        .column-resize-handle {
            position: absolute;
            right: -2px;
            top: 0;
            bottom: -2px;
            width: 4px;
            background-color: #adf;
            pointer-events: none;
        }

        p {
            margin: 0;
        }
    }



    .tableWrapper {
        overflow-x: auto;
    }

    .resize-cursor {
        cursor: ew-resize;
        cursor: col-resize;
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
