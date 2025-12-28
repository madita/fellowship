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

                    <v-btn
                        :loading="isLoading"
                        :disabled="isSignUpDisabled"
                        block
                        size="large"
                        color="primary"
                        @click="submit"
                    >{{ $t('register.button') }}
                    </v-btn>

<!--                    <div class="caption font-weight-bold text-uppercase my-3">{{ $t('register.orsign') }}</div>-->

<!--                    &lt;!&ndash; external providers list &ndash;&gt;-->
<!--                    <v-btn-->
<!--                        v-for="provider in providers"-->
<!--                        :key="provider.id"-->
<!--                        :loading="provider.isLoading"-->
<!--                        :disabled="isSignUpDisabled"-->
<!--                        class="mb-2 primary lighten-2 text-primary text&#45;&#45;darken-3"-->
<!--                        block-->
<!--                        size="large"-->
<!--                        @click="signInProvider(provider)"-->
<!--                    >-->
<!--                        <v-icon small left>mdi-{{ provider.id }}</v-icon>-->
<!--                        {{ provider.label }}-->
<!--                    </v-btn>-->

<!--                    <div v-if="errorProvider" class="error&#45;&#45;text">{{ errorProviderMessages }}</div>-->

                    <div class="mt-5 overline">
                        {{ $t('register.agree') }}
                        <br/>
                        <router-link to="">{{ $t('common.tos') }}</router-link>
                        &
                        <router-link to="">{{ $t('common.policy') }}</router-link>
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

import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from "@/store/authStore.js"
import axios from 'axios'

// Router
const router = useRouter()

// Auth store
const authStore = useAuthStore()

// Template refs
const formRef = ref(null)

// Reactive data
const isLoading = ref(false)
const isSignUpDisabled = ref(false)
const isFormValid = ref(true)
const showPassword = ref(false)

// User data
const user = reactive({
    name: "",
    username: "",
    email: "",
    password: "",
    password_confirmation: ""
})

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
        }

        //console.log("Registration error:", error)
    } finally {
        isLoading.value = false
        isSignUpDisabled.value = false
    }
}

const submit = () => {
    // if (formRef.value.validate()) {
    isLoading.value = true
    isSignUpDisabled.value = true
    register()
    // }
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
}
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
