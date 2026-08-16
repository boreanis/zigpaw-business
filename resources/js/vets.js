const themeKey = 'zigpaw-clinical-theme'
const storedTheme = window.localStorage.getItem(themeKey)

if (storedTheme === 'light' || storedTheme === 'dark') {
    document.documentElement.dataset.theme = storedTheme
}

const resolvedTheme = () => {
    if (document.documentElement.dataset.theme) {
        return document.documentElement.dataset.theme
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

const updateThemeLabels = () => {
    const nextTheme = resolvedTheme() === 'dark' ? 'light' : 'dark'

    document.querySelectorAll('#theme-toggle').forEach((button) => {
        button.setAttribute('aria-label', `Use ${nextTheme} appearance`)
        button.setAttribute('title', `Use ${nextTheme} appearance`)
    })
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('#theme-toggle')
    if (!button) {
        return
    }

    const nextTheme = resolvedTheme() === 'dark' ? 'light' : 'dark'
    document.documentElement.dataset.theme = nextTheme
    window.localStorage.setItem(themeKey, nextTheme)
    updateThemeLabels()
})

document.addEventListener('DOMContentLoaded', updateThemeLabels)
document.addEventListener('livewire:navigated', updateThemeLabels)
