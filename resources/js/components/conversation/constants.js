// Conversation component constants

export const VALIDATION_RULES = {
    required: (value) => !!value?.trim() || 'This field is required',
    minLength: (min) => (value) => (value?.trim()?.length || 0) >= min || `Must be at least ${min} character(s)`,
    maxLength: (max) => (value) => (value?.length || 0) <= max || `Must be less than ${max} characters`,
    recipientRequired: (value) => {
        if (!value || value.length === 0) {
            return 'At least one recipient is required'
        }
        return true
    }
}

export const DEBOUNCE_DELAYS = {
    USER_SEARCH: 500,
    DEFAULT: 300
}

export const MESSAGE_LIMITS = {
    MAX_BODY_LENGTH: 1000,
    MIN_BODY_LENGTH: 1
}

export const SCROLL_BEHAVIOR = {
    MESSAGES_MAX_HEIGHT: '60vh',
    MESSAGES_MAX_HEIGHT_REDUCED: '50vh'
}
