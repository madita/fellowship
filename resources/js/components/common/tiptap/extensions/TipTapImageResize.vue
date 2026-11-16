<template>
    <node-view-wrapper
        as="span"
        @click="setActive"
        class="tiptap-custom-image-container"
    >
        <vue-draggable-resizable
            :w="width"
            :h="height"
            @resize-end="onResize"
            :draggable="false"
            :lock-aspect-ratio="true"
            v-model:active="isActive"
        >
            <div
                :style="`background-image:url('${src}');background-size:cover;background-repeat:no-repeat;position:absolute;top:0;left:0;right:0;bottom:0;`"
            ></div>
        </vue-draggable-resizable>
    </node-view-wrapper>
</template>
<script>
import { NodeViewWrapper, nodeViewProps } from "@tiptap/vue-3";
import VueDraggableResizable from "vue3-draggable-resizable";
import "vue3-draggable-resizable/dist/Vue3DraggableResizable.css";
import { ref } from "vue";

export default {
    props: nodeViewProps,
    components: {
        "vue-draggable-resizable": VueDraggableResizable,
        NodeViewWrapper,
    },
    setup() {
        const isActive = ref(false);

        return { isActive };
    },
    computed: {
        src: {
            get() {
                return this.node.attrs.src;
            },
            set(src) {
                this.updateAttributes({ src });
            },
        },
        width: {
            get() {
                return parseInt(this.node.attrs.width);
            },
            set(width) {
                this.updateAttributes({
                    width: width,
                });
            },
        },
        height: {
            get() {
                return parseInt(this.node.attrs.height);
            },
            set(height) {
                this.updateAttributes({
                    height: height,
                });
            },
        },
    },
    methods: {
        onResize({ w, h }) {
            this.width = w;
            this.height = h;
        },
        setActive() {
            this.isActive = true;
        },
    },
};
</script>
<style scoped>
.tiptap-custom-image-container > div {
    position: relative;
    left: 0 !important;
    top: 0 !important;
}
</style>
