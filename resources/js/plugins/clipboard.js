// import * as Vue from 'vue'
//
// /**
//  * Copy to clipboard the text passed
//  * @param {String} text string to copy
//  * @param {String} toastText message to appear on the toast notification
//  */
// Vue.prototype.$clipboard = function (text, toastText = 'Copied to clipboard!') {
//   const el = document.createElement('textarea')
//
//   el.value = text
//   document.body.appendChild(el)
//   el.select()
//   document.execCommand('copy')
//   document.body.removeChild(el)
//
//   if (this.$store && this.$store.dispatch) this.$store.dispatch('app/showToast', toastText)
// }
import { getCurrentInstance } from 'vue'

export default function useClipboard() {
    const clipboard = (text, toastText = 'Copied to clipboard!') => {
        const el = document.createElement('textarea')

        el.value = text
        document.body.appendChild(el)
        el.select()
        document.execCommand('copy')
        document.body.removeChild(el)

        const vm = getCurrentInstance()
        if (vm && vm.appContext.config.globalProperties.$store) {
            vm.appContext.config.globalProperties.$store.dispatch('app/showToast', toastText)
        }
    }

    return { clipboard }
}
