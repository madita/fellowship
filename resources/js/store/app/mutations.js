// Assuming you've imported Vuetify as it's used in the mutations
// import Vuetify from '../../plugins/vuetify'

const showToast = (store, toast) => {
    const { color, timeout, message } = toast

    store.toast = {
        message,
        color,
        timeout,
        show: true
    }
}

const hideToast = (store) => {
    store.toast.show = false
}

const resetToast = (store) => {
    store.toast = {
        show: false,
        color: 'black',
        message: '',
        timeout: 3000
    }
}

const setGlobalTheme = (store, theme) => {
    Vuetify.framework.theme.dark = theme === 'dark'
    store.globalTheme = theme
}

const setRTL = (store, isRTL) => {
    Vuetify.framework.rtl = isRTL
    store.isRTL = isRTL
}

const setContentBoxed = (store, isBoxed) => {
    store.isContentBoxed = isBoxed
}

const setMenuTheme = (store, theme) => {
    store.menuTheme = theme
}

const setToolbarTheme = (store, theme) => {
    store.toolbarTheme = theme
}

const setTimeZone = (store, zone) => {
    store.time.zone = zone
}

const setTimeFormat = (store, format) => {
    store.time.format = format
}

const setCurrency = (store, currency) => {
    store.currency = currency
}

const setToolbarDetached = (store, isDetached) => {
    store.isToolbarDetached = isDetached
}

// Export all the actions
export default {
    showToast,
    hideToast,
    resetToast,
    setGlobalTheme,
    setRTL,
    setContentBoxed,
    setMenuTheme,
    setToolbarTheme,
    setTimeZone,
    setTimeFormat,
    setCurrency,
    setToolbarDetached
}
