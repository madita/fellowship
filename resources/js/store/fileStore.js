import { defineStore } from "pinia";

export const useFileStore = defineStore("fileStore", {
    state: () => ({
        files: [],
    }),
    actions: {
        addFiles(newFiles) {
            this.files = [...this.files, ...newFiles];
        },
        removeFile(index) {
            this.files.splice(index, 1);
        },
        clearFiles() {
            this.files = [];
        },
    },
});
