const lightTheme = {
    dark: false,
    colors: {
        background: '#ffffff',
        surface: '#f2f5f8',
        // primary: '#0096c7',
        primary: '#115571',
        secondary: '#a0b9c8',
        accent: '#048ba8',
        error: '#ef476f',
        info: '#2196F3',
        success: '#06d6a0',
        warning: '#ffd166'
    },
}


const darkTheme = {
    dark: true,
    colors: {
        background: '#05090c',
        surface: '#111b27',
        primary: '#0096c7',
        secondary: '#829099',
        accent: '#82B1FF',
        error: '#FF5252',
        info: '#2196F3',
        success: '#4CAF50',
        warning: '#FFC107'
    },
}
export default {
  // global theme for the app
  globalTheme: 'light', // light | dark

  // side menu theme, use global theme or custom
  menuTheme: 'dark', // global | light | dark

  // toolbar theme, use global theme or custom
  toolbarTheme: 'global', // global | light | dark

  // show toolbar detached from top
  isToolbarDetached: false,

  // wrap pages content with a max-width
  isContentBoxed: false,

  // application is right to left
  isRTL: false,

  // dark theme colors
  dark: {
    background: '#05090c',
    surface: '#111b27',
    primary: '#0096c7',
    secondary: '#829099',
    accent: '#82B1FF',
    error: '#FF5252',
    info: '#2196F3',
    success: '#4CAF50',
    warning: '#FFC107'
  },

  // light theme colors
    lightTheme: {
        dark: false,
        colors: {
            background: '#ffffff',
            surface: '#f2f5f8',
            // primary: '#0096c7',
            primary: '#115571',
            secondary: '#a0b9c8',
            accent: '#048ba8',
            error: '#ef476f',
            info: '#2196F3',
            success: '#06d6a0',
            warning: '#ffd166'
        },
    },



}
