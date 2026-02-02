<template>
    <div>
        <v-card class="text-center pa-1" elevation="4">
            <v-card-title class="justify-center display-1 mb-2">Welcome</v-card-title>
            <v-card-subtitle>Sign in to your account</v-card-subtitle>

            <!-- sign in form -->
            <v-card-text>
                <v-form ref="form" lazy-validation>
                    <v-text-field
                        v-model="credentials.email"
                        :rules="[rules.required]"
                        :error-messages="errorMessages.email ? [errorMessages.email] : []"
                        validate-on="blur"
                        :error="!!errorMessages.email"
                        :label="$t('login.username')"
                        name="email"
                        @keyup.enter="submit"
                    ></v-text-field>

                    <v-text-field
                        v-model="credentials.password"
                        :append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                        :rules="[rules.required]"
                        :type="showPassword ? 'text' : 'password'"
                        :error-messages="errorMessages.password ? [errorMessages.password] : []"
                        :error="!!errorMessages.password"
                        :label="$t('login.password')"
                        name="password"
                        @keyup.enter="submit"
                        @click:append="showPassword = !showPassword"
                    ></v-text-field>

                    <!-- Display general error message -->
                    <v-alert
                        v-if="errorMessages.message && credentials.email && credentials.password"
                        type="error"
                        class="mb-3"
                        density="compact"
                    >
                        {{ errorMessages.message }}
                    </v-alert>

                    <v-btn
                        :loading="isLoading"
                        :disabled="isSignInDisabled"
                        block
                        size="large"
                        color="primary"
                        @click="submit"
                    >{{ $t('login.button') }}
                    </v-btn>

                    <div v-if="enabledProviders.length > 0" class="caption font-weight-bold text-uppercase my-3">{{ $t('login.orsign') }}</div>

                    <!-- Social Login Buttons -->
                    <v-row v-if="enabledProviders.length > 0" dense class="mb-3">
                        <v-col v-if="enabledProviders.includes('google')" cols="6">
                            <v-btn
                                block
                                variant="outlined"
                                color="red"
                                :href="`/auth/google`"
                                prepend-icon="mdi-google"
                            >
                                Google
                            </v-btn>
                        </v-col>
                        <v-col v-if="enabledProviders.includes('discord')" cols="6">
                            <v-btn
                                block
                                variant="outlined"
                                color="indigo"
                                :href="`/auth/discord`"
                                prepend-icon="mdi-discord"
                            >
                                Discord
                            </v-btn>
                        </v-col>
                        <v-col v-if="enabledProviders.includes('github')" cols="6">
                            <v-btn
                                block
                                variant="outlined"
                                color="grey-darken-3"
                                :href="`/auth/github`"
                                prepend-icon="mdi-github"
                            >
                                GitHub
                            </v-btn>
                        </v-col>
                        <v-col v-if="enabledProviders.includes('facebook')" cols="6">
                            <v-btn
                                block
                                variant="outlined"
                                color="blue"
                                :href="`/auth/facebook`"
                                prepend-icon="mdi-facebook"
                            >
                                Facebook
                            </v-btn>
                        </v-col>
                    </v-row>

                    <div class="mt-5">
                        <router-link to="/auth/forgot-password">
                            {{ $t('login.forgot') }}
                        </router-link>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/authStore.js'
import axios from 'axios'

const router = useRouter()
const auth = useAuthStore()

// Reactive state variables
const isLoading = ref(false)
const isSignInDisabled = ref(false)
const showPassword = ref(false)
const enabledProviders = ref([])

const credentials = reactive({
    email: null,
    password: null,
})

const rules = {
    required: (value) => (value && Boolean(value)) || 'Required',
    email: value => {
        const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
        return pattern.test(value) || 'Invalid e-mail.'
    }
}

const errorMessages = reactive({
    message: null,
    email: null,
    username: null,
    password: null,
})

// Function to reset errors
const resetErrors = () => {
    errorMessages.message = null
    errorMessages.email = null
    errorMessages.username = null
    errorMessages.password = null
}

const submit = async () => {
    // Reset errors before attempting login
    resetErrors()

    isLoading.value = true
    isSignInDisabled.value = true

    try {
        await auth.login(credentials)

        // Check if user is verified and redirect accordingly
        if (auth.isVerified) {
            await router.push({ name: 'dashboard' })
        } else {
            await router.push({ name: 'auth-verify-email' })
        }
    } catch (error) {
        //console.log('Login error:', error)

        // Handle error response
        if (error.response && error.response.data) {
            const data = error.response.data

            // Set general error message
            errorMessages.message = data.message || 'An error occurred during login'

            // Set specific field errors
            if (data.errors) {
                if (data.errors.email) {
                    errorMessages.email = Array.isArray(data.errors.email)
                        ? data.errors.email[0]
                        : data.errors.email
                }
                if (data.errors.username) {
                    errorMessages.username = Array.isArray(data.errors.username)
                        ? data.errors.username[0]
                        : data.errors.username
                }
                if (data.errors.password) {
                    errorMessages.password = Array.isArray(data.errors.password)
                        ? data.errors.password[0]
                        : data.errors.password
                }
            }
        } else {
            // Handle network or other errors
            errorMessages.message = 'Network error. Please try again.'
        }
    } finally {
        isLoading.value = false
        isSignInDisabled.value = false
    }
}

// Fetch enabled OAuth providers on mount
onMounted(async () => {
    try {
        const response = await axios.get('/api/settings/oauth-providers')
        enabledProviders.value = response.data.providers || []
    } catch (error) {
        console.error('Failed to fetch OAuth providers:', error)
    }
})
</script>

<style scoped>
.v-card {
    min-width: 400px;
}

/* Left-align error messages */
:deep(.v-messages__message) {
    text-align: left !important;
}
</style>
