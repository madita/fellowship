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

      if (targetTheme === 'dark') {
        document.documentElement.classList.add('v-theme--dark')
        document.documentElement.classList.remove('v-theme--light')
      } else {
        document.documentElement.classList.add('v-theme--light')
        document.documentElement.classList.remove('v-theme--dark')
      }
    }
  }

  mediaQuery.addEventListener('change', handleChange)

  return () => mediaQuery.removeEventListener('change', handleChange)
}
