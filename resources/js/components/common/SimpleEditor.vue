<template>
    <div>
        <input id="quill" type="hidden" :name="name" :value="value">
        <input v-for="image in form.images" type="hidden" name="images[]" :value="image">
        <input v-for="imageName in form.imageNames" type="hidden" name="imageNames[]" :value="imageName">
        <quill-editor v-model="form.body"
                      class="mb-3"
                      rows="20"
                      :options="editorOption"
                      ref="myQuillEditor"
                      @blur="onEditorBlur($event)"
                      @focus="onEditorFocus($event)"
                      @ready="onEditorReady($event)"
                      @change="onEditorChange($event)">
        </quill-editor>
        <div class="custom-file d-none">
            <input ref="image" @change="imageUpload($event)" type="file" class="custom-file-input" id="imageUpload" aria-describedby="imageUploadAddon">
            <label class="custom-file-label" for="imageUpload">Choose file</label>
        </div>
    </div>

</template>

<script>
import 'quill/dist/quill.core.css'
import 'quill/dist/quill.snow.css'
import 'quill/dist/quill.bubble.css'
import { quillEditor, Quill } from 'vue-quill-editor'
import ImageResize from 'quill-image-resize';
    import axios from 'axios'

// Register ImageResize module
Quill.register('modules/imageResize', ImageResize);

// Set toolbar options
const toolbarOptions = {
        'full':
        [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],

            [{'header': 1}, {'header': 2}],
            [{'list': 'ordered'}, {'list': 'bullet'}],
            [{'script': 'sub'}, {'script': 'super'}],
            [{'indent': '-1'}, {'indent': '+1'}],

            [{'size': ['small', false, 'large', 'huge']}],
            [{'header': [1, 2, 3, 4, 5, 6, false]}],

            [{'color': []}, {'background': []}],
            [{'font': []}],
            [{'align': []}],
            ['link', 'image', 'video'],
            ['clean'],
        ],
        'simple':
        [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', ],
            ['link', 'image', 'video'],
        ],
        'comment':
        [
            ['bold', 'italic'],
            ['blockquote'],
        ]
};

    export default {
        props: ['name', 'value', 'placeholder', 'tools' ],
        components: {
            quillEditor
        },

        data() {
            return {
                form: {
                    body: this.value,
                    images: [],
                    imageNames: []
                },
                editorOption: {
                    modules: {
                        toolbar: {
                            container: toolbarOptions[this.tools],
                            handlers: {
                                'image': function (value) {
                                    if (value) {
                                        document.querySelector('#imageUpload').click();
                                    } else {
                                        this.quill.format('image', false);
                                    }
                                }
                            },
                        },
                        history: {
                            delay: 1000,
                            maxStack: 50,
                            userOnly: false
                        },
                        imageResize: {
                            displayStyles: {
                                backgroundColor: 'black',
                                border: 'none',
                                color: 'white'
                            },
                            modules: [ 'Resize', 'DisplaySize', 'Toolbar' ]
                        }
                    }
                },
            }
        },

        methods: {

            // onEditorUpdate(newVal) {
            //     this.$emit('input', newVal)
            // },
            onEditorChange({ quill, html, text }) {
                // console.log('editor change!', quill, html, text)
                this.$emit('input', html)
            },
            onEditorBlur(editor) {
                // console.log('editor blur!', editor)
            },
            onEditorFocus(editor) {
                // console.log('editor focus!', editor)
            },
            onEditorReady(editor) {
                // console.log('editor ready!', editor)
            },
            submitForm(){
                // axios.post('/article/create',
                //     { body: this.form.body, images: this.form.images })
                //     .then(response => {
                //         console.log("Response", response)
                //     })
                //     .catch(error => {
                //         console.log("Error", error)
                //     })
            },

            imageUpload(e) {

                if (e.target.files.length !== 0) {
                    console.log(e.target.files)

                    let quill = this.editor;
                    let reader = new FileReader();
                    console.log(reader);
                    reader.readAsDataURL(e.target.files[0]);
                    let self = this;
                    reader.onloadend = function() {
                        let base64data = reader.result;
                        self.form.images.push(base64data);
                        self.form.imageNames.push(e.target.files[0].name);

                        // Get cursor location
                        let length = quill.getSelection().index;

                        // Insert image at cursor location
                        quill.insertEmbed(length, 'image', base64data);

                        // Set cursor to the end
                        quill.setSelection(length + 1);
                    }
                    console.log('upload',this.form.images)}
            },

            // handleImageAdded(file, Editor, cursorLocation) {
            //     console.log('file',file);
            //     const CLIENT_ID = '993793b1d8d3e2e'
            //     var formData = new FormData();
            //     formData.append('image', file)
            //     axios({
            //         url: 'https://api.imgffur.com/3/image',
            //         method: 'POST',
            //         headers:{
            //             'Authorization': 'Client-ID ' + CLIENT_ID
            //         },
            //         data: formData
            //     })
            //         .then((result) => {
            //             console.log(result);
            //             let url = result.data.data.link
            //             Editor.insertEmbed(cursorLocation, 'image', url);
            //         })
            //         .catch((err) => {
            //             console.log(err);
            //         })
            // }

        },

        computed: {
            editor(){
                return this.$refs.myQuillEditor.quill
            }
        },
        watch: {
            value(val) {
                this.value = val;
                this.form.body = val;
                this.$emit('input', this.value);
            }
        }
    }
</script>
<style>
.ql-editor{
    min-height:200px;
}
</style>
