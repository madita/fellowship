/**
 * Apply opacity settings based on theme
 */
function applyOpacityForTheme(isDark) {
  try {
    const cachedSettings = localStorage.getItem('app_settings')
    if (cachedSettings) {
      const settings = JSON.parse(cachedSettings)

      // Get opacity values (0-100) and convert to decimal (0-1)
      const backgroundOpacity = isDark
        ? (settings.background_opacity_dark || 95) / 100
        : (settings.background_opacity_light || 95) / 100

      const surfaceOpacity = isDark
        ? (settings.surface_opacity_dark || 100) / 100
        : (settings.surface_opacity_light || 100) / 100

      // Get or create the opacity style element
      let styleEl = document.getElementById('dynamic-opacity-style')
      if (!styleEl) {
        styleEl = document.createElement('style')
        styleEl.id = 'dynamic-opacity-style'
        document.head.appendChild(styleEl)
      }

      // Apply opacity via CSS custom properties
      styleEl.textContent = `
        :root {
          --app-background-opacity: ${backgroundOpacity};
          --app-surface-opacity: ${surfaceOpacity};
        }

        .v-main {
          background: rgba(var(--v-theme-background), var(--app-background-opacity)) !important;
        }

        .v-card,
        .v-sheet,
        .v-toolbar,
        .v-app-bar {
          background: rgba(var(--v-theme-surface), var(--app-surface-opacity)) !important;
        }

        .v-navigation-drawer,
        .v-dialog .v-overlay__content,
        .v-menu .v-overlay__content {
          background: rgb(var(--v-theme-surface)) !important;
        }
      `
    }
  } catch (e) {
    console.warn('Failed to apply opacity settings:', e)
  }
}

/**
 * Apply background image based on theme
 */
function applyBackgroundForTheme(isDark) {
  try {
    const cachedSettings = localStorage.getItem('app_settings')
    if (cachedSettings) {
      const settings = JSON.parse(cachedSettings)
      const backgroundImage = isDark ? settings.background_dark : settings.background_light
      const backgroundStyle = settings.background_style || 'cover'

      // Get or create the background element
      let bgElement = document.getElementById('app-background')
      if (!bgElement) {
        bgElement = document.createElement('div')
        bgElement.id = 'app-background'
        bgElement.style.position = 'fixed'
        bgElement.style.top = '0'
        bgElement.style.left = '0'
        bgElement.style.width = '100%'
        bgElement.style.height = '100%'
        bgElement.style.zIndex = '-1'
        bgElement.style.pointerEvents = 'none'
        document.body.prepend(bgElement)
      }

      if (backgroundImage && backgroundImage.trim() !== '') {
        const imageUrl = `/storage/${backgroundImage}`
        bgElement.style.backgroundImage = `url('${imageUrl}')`
        bgElement.style.backgroundPosition = 'center'
        bgElement.style.backgroundAttachment = 'fixed'

        if (backgroundStyle === 'repeat') {
          bgElement.style.backgroundSize = 'auto'
          bgElement.style.backgroundRepeat = 'repeat'
        } else {
          bgElement.style.backgroundSize = 'cover'
          bgElement.style.backgroundRepeat = 'no-repeat'
        }
      } else {
        bgElement.style.backgroundImage = ''
      }

      // Make sure body background is transparent to show the background element
      document.body.style.background = 'transparent'

      // Apply opacity settings
      applyOpacityForTheme(isDark)
    }
  } catch (e) {
    console.warn('Failed to apply background image:', e)
  }
}

/**
 * Initialize theme before Vue app mounts
 * This ensures the correct theme is applied immediately on page load
 */
export function initializeTheme() {
  // Get saved theme from localStorage
  const savedTheme = localStorage.getItem('theme_mode') || 'system'

  let targetTheme = 'light'

  if (savedTheme === 'system') {
    // Check system preference
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    targetTheme = prefersDark ? 'dark' : 'light'
  } else {
    targetTheme = savedTheme
  }

  // Apply theme to document immediately
  if (targetTheme === 'dark') {
    document.documentElement.classList.add('v-theme--dark')
    document.documentElement.classList.remove('v-theme--light')
  } else {
    document.documentElement.classList.add('v-theme--light')
    document.documentElement.classList.remove('v-theme--dark')
  }

  // Apply background image for the theme
  applyBackgroundForTheme(targetTheme === 'dark')

  return targetTheme
}

/**
 * Setup theme change listener for system theme mode
 */
export function setupThemeListener() {
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')

  const handleChange = (e) => {
    const savedTheme = localStorage.getItem('theme_mode')

    // Only apply if user has chosen 'system' mode
    if (savedTheme === 'system' || !savedTheme) {
      const targetTheme = e.matches ? 'dark' : 'light'
      const isDark = targetTheme === 'dark'

      if (isDark) {
        document.documentElement.classList.add('v-theme--dark')
        document.documentElement.classList.remove('v-theme--light')
      } else {
        document.documentElement.classList.add('v-theme--light')
        document.documentElement.classList.remove('v-theme--dark')
      }

      // Apply background image for the new theme
      applyBackgroundForTheme(isDark)
    }
  }

  mediaQuery.addEventListener('change', handleChange)

  return () => mediaQuery.removeEventListener('change', handleChange)
}

/**
 * Setup global theme change observer
 * Watches for theme changes and applies background accordingly
 */
export function setupThemeObserver() {
  // Watch for theme class changes on documentElement
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.attributeName === 'class') {
        const isDark = document.documentElement.classList.contains('v-theme--dark')
        applyBackgroundForTheme(isDark)
      }
    })
  })

  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class']
  })

  return () => observer.disconnect()
}
