import { VueNodeViewRenderer } from "@tiptap/vue-3";
import TipTapImageComponent from "./TipTapImageResize.vue";
import Image from "@tiptap/extension-image";

const ImageResize = Image.extend({
    name: "ImageResize",
    addAttributes() {
        return {
            ...this.parent?.(),
            src: {
                default: "",
                renderHTML: ({ src }) => ({ src })
            },
            width: {
                default: 300,
                renderHTML: ({ width }) => ({ width })
            },

            height: {
                default: 200,
                renderHTML: ({ height }) => ({ height })
            },

            isDraggable: {
                default: true,
                renderHTML: (attributes) => {
                    return {};
                }
            }
        };
    },
    addNodeView() {
        return VueNodeViewRenderer(TipTapImageComponent);
    }
});

export default ImageResize;
export { ImageResize };
