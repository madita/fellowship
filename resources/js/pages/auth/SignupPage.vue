<template>
    <div>
        <v-card class="text-center pa-1" elevation="4">
            <v-card-title class="justify-center display-1 mb-2">{{ $t('register.title') }}</v-card-title>
            <v-card-subtitle>Let's build amazing products</v-card-subtitle>

            <!-- sign up form -->
            <v-card-text>
                <v-form ref="formRef" v-model="isFormValid" lazy-validation>
                    <v-text-field
                        v-model="user.name"
                        validate-on="blur"
                        :error="errorName"
                        :error-messages="errorNameMessage"
                        :label="$t('register.name')"
                        name="name"
                        variant="outlined"
                        @keyup.enter="submit"
                        @change="resetErrors"
                    ></v-text-field>

                    <v-text-field
                        v-model="user.username"
                        :rules="[rules.required]"
                        validate-on="blur"
                        :error="errorUsername"
                        :error-messages="errorUsernameMessage"
                        :label="$t('register.username')"
                        name="username"
                        variant="outlined"
                        @keyup.enter="submit"
                        @change="resetErrors"
                    ></v-text-field>

                    <v-text-field
                        v-model="user.email"
                        :rules="[rules.required, rules.email]"
                        validate-on="blur"
                        :error="errorEmail"
                        :error-messages="errorEmailMessage"
                        :label="$t('register.email')"
                        name="email"
                        variant="outlined"
                        @keyup.enter="submit"
                        @change="resetErrors"
                    ></v-text-field>

                    <v-text-field
                        v-model="user.password"
                        :append-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                        :rules="[rules.required]"
                        :type="showPassword ? 'text' : 'password'"
                        :error="errorPassword"
                        :error-messages="errorPasswordMessage"
                        :label="$t('register.password')"
                        name="password"
                        variant="outlined"
                        @change="resetErrors"
                        @keyup.enter="submit"
                        @click:append="showPassword = !showPassword"
                    ></v-text-field>

                    <v-text-field
                        v-model="user.password_confirmation"
                        :rules="[rules.required]"
                        :type="'password'"
                        :error="errorPassword"
                        :error-messages="errorPasswordMessage"
                        :label="$t('register.password')"
                        name="password_confirmation"
                        variant="outlined"
                        @change="resetErrors"
                        @keyup.enter="submit"
                    ></v-text-field>

                    <!-- Age Confirmation -->
                    <v-checkbox
                        v-if="ageConfirmationRequired"
                        v-model="ageConfirmed"
                        :error="errorAgeConfirmation"
                        :error-messages="errorAgeConfirmationMessage"
                        density="compact"
                        class="mt-2"
                        @change="resetErrors"
                    >
                        <template v-slot:label>
                            <span class="text-body-2">
                                {{ $t('register.ageConfirmation', { age: ageMinimum }) || `I confirm that I am at least ${ageMinimum} years old` }}
                            </span>
                        </template>
                    </v-checkbox>

                    <v-btn
                        :loading="isLoading"
                        :disabled="isSignUpDisabled"
                        block
                        size="large"
                        color="primary"
                        @click="submit"
                    >{{ $t('register.button') }}
                    </v-btn>

                    <div v-if="enabledProviders.length > 0" class="caption font-weight-bold text-uppercase my-3">{{ $t('register.orsign') }}</div>

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

                    <div v-if="termsUrl || policyUrl" class="mt-5 overline">
                        {{ $t('register.agree') }}
                        <br/>
                        <a v-if="termsUrl" :href="termsUrl" target="_blank" rel="noopener noreferrer">{{ $t('common.tos') }}</a>
                        <template v-if="termsUrl && policyUrl">&</template>
                        <a v-if="policyUrl" :href="policyUrl" target="_blank" rel="noopener noreferrer">{{ $t('common.policy') }}</a>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>

        <div class="text-center mt-6">
            {{ $t('register.account') }}
            <router-link to="/auth/signin" class="font-weight-bold">
                {{ $t('register.signin') }}
            </router-link>
        </div>
    </div>
</template>

<script setup>
/*
|---------------------------------------------------------------------
| Sign Up Page Component
|---------------------------------------------------------------------
|
| Template for user sign up with external providers buttons
|
*/

import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from "@/store/authStore.js"
import { useSettingsStore } from "@/store/settingStore.js"
import axios from 'axios'

// Router
const router = useRouter()

// Auth store
const authStore = useAuthStore()

// Settings store
const settingsStore = useSettingsStore()

// Template refs
const formRef = ref(null)

// Reactive data
const isLoading = ref(false)
const isSignUpDisabled = ref(false)
const isFormValid = ref(true)
const showPassword = ref(false)
const enabledProviders = ref([])

// User data
const user = reactive({
    name: "",
    username: "",
    email: "",
    password: "",
    password_confirmation: "",
    age_confirmed: false
})

// Age confirmation
const ageConfirmed = ref(false)

// Error states
const errorName = ref(false)
const errorUsername = ref(false)
const errorEmail = ref(false)
const errorPassword = ref(false)
const errorNameMessage = ref('')
const errorUsernameMessage = ref('')
const errorEmailMessage = ref('')
const errorPasswordMessage = ref('')
const errorProvider = ref(false)
const errorProviderMessages = ref('')
const errorAgeConfirmation = ref(false)
const errorAgeConfirmationMessage = ref('')

// External providers
const providers = ref([
    {
        id: 'google',
        label: 'Google',
        isLoading: false
    },
    {
        id: 'facebook',
        label: 'Facebook',
        isLoading: false
    }
])

// Input validation rules
const rules = {
    required: (value) => (value && Boolean(value)) || 'Required',
    email: value => {
        const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
        return pattern.test(value) || 'Invalid e-mail.'
    }
}

// Computed properties
const authenticated = computed(() => authStore.isLoggedIn)
const isVerified = computed(() => authStore.isVerified)
const termsUrl = computed(() => settingsStore.termsConditionsUrl)
const policyUrl = computed(() => settingsStore.privacyPolicyUrl)
const ageConfirmationRequired = computed(() => settingsStore.ageConfirmationRequired)
const ageMinimum = computed(() => settingsStore.ageMinimum)

// Methods
const signIn = async (credentials) => {
    await authStore.signIn(credentials)
}

const register = async () => {
    try {
        await axios.post('/register', user)

        await signIn()

        if (isVerified.value) {
            router.replace({ name: 'dashboard' })
        } else {
            router.replace({ name: 'auth-verify-email' })
        }
    } catch (error) {
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors

            if (errors.name) {
                errorName.value = true
                errorNameMessage.value = errors.name[0]
            }

            if (errors.username) {
                errorUsername.value = true
                errorUsernameMessage.value = errors.username[0]
            }

            if (errors.email) {
                errorEmail.value = true
                errorEmailMessage.value = errors.email[0]
            }

            if (errors.password) {
                errorPassword.value = true
                errorPasswordMessage.value = errors.password[0]
            }

            if (errors.age_confirmed) {
                errorAgeConfirmation.value = true
                errorAgeConfirmationMessage.value = errors.age_confirmed[0]
            }
        }

        //console.log("Registration error:", error)
    } finally {
        isLoading.value = false
        isSignUpDisabled.value = false
    }
}

const submit = () => {
    // Validate age confirmation if required
    if (ageConfirmationRequired.value && !ageConfirmed.value) {
        errorAgeConfirmation.value = true
        errorAgeConfirmationMessage.value = 'You must confirm your age to register'
        return
    }

    // Set age_confirmed in user data
    user.age_confirmed = ageConfirmed.value

    isLoading.value = true
    isSignUpDisabled.value = true
    register()
}

const signInProvider = (provider) => {
    // Implement provider sign-in logic
    //console.log('Provider sign-in:', provider)
}

const resetErrors = () => {
    errorName.value = false
    errorUsername.value = false
    errorEmail.value = false
    errorPassword.value = false
    errorNameMessage.value = ''
    errorUsernameMessage.value = ''
    errorEmailMessage.value = ''
    errorPasswordMessage.value = ''
    errorProvider.value = false
    errorProviderMessages.value = ''
    errorAgeConfirmation.value = false
    errorAgeConfirmationMessage.value = ''
}

// Fetch enabled OAuth providers and settings on mount
onMounted(async () => {
    // Fetch settings if not already loaded
    if (!settingsStore.settingsLoaded) {
        await settingsStore.fetchAppSettings()
    }

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
