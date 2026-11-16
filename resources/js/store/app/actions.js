// Directly changing state within actions
const showToast = (store, message) => {
    if (store.toast.show) store.hideToast() // we'll create this action below

    setTimeout(() => {
        store.toast = {
            color: 'black',
            message,
            timeout: 3000,
            show: true
        }
    })
}

const showError = (store, { message = 'Failed!', error }) => {
    if (store.toast.show) store.hideToast()

    setTimeout(() => {
        store.toast = {
            color: 'error',
            message: message + ' ' + error.message,
            timeout: 10000,
            show: true
        }
    })
}

const showSuccess = (store, message) => {
    if (store.toast.show) store.hideToast()

    setTimeout(() => {
        store.toast = {
            color: 'success',
            message,
            timeout: 3000,
            show: true
        }
    })
}

// Extra action used in the above actions
const hideToast = (store) => {
    store.toast.show = false
}

export default {
    showToast,
    showError,
    showSuccess,
    hideToast
}
