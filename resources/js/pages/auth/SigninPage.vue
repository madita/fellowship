<template>
    <div>
        <v-card class="text-center pa-1">
            <v-card-title class="justify-center display-1 mb-2">Welcome</v-card-title>
            <v-card-subtitle>Sign in to your account</v-card-subtitle>

            <!-- sign in form -->
            <v-card-text>
                <v-form ref="form" lazy-validation>
                    <v-text-field
                        v-model="credentials.email"
                        :rules="[rules.required]"
                        :error-messages="[]"
                        validate-on="blur"
                        :label="$t('login.username')"
                        name="email"
                        variant="outlined"
                        @keyup.enter="submit"
                    ></v-text-field>

                    <v-text-field
                        v-model="credentials.password"
                        :append-inner-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                        :rules="[rules.required]"
                        :type="showPassword ? 'text' : 'password'"
                        :error-messages="[]"
                        :label="$t('login.password')"
                        name="password"
                        variant="outlined"
                        @keyup.enter="submit"
                        @click:append="showPassword = !showPassword"
                    ></v-text-field>

                    <v-btn
                        :loading="isLoading"
                        :disabled="isSignInDisabled"
                        block
                        size="large"
                        color="primary"
                        @click="submit"
                    >{{ $t('login.button') }}
                    </v-btn>

                    <div class="caption font-weight-bold text-uppercase my-3">{{ $t('login.orsign') }}</div>

                    <!-- external providers list -->
<!--                    <v-btn-->
<!--                        v-for="provider in providers"-->
<!--                        :key="provider.id"-->
<!--                        :loading="provider.isLoading"-->
<!--                        :disabled="isSignInDisabled"-->
<!--                        class="mb-2 primary lighten-2 text-primary text&#45;&#45;darken-3"-->
<!--                        block-->
<!--                        size="large"-->
<!--                        to="/"-->
<!--                    >-->
<!--                        <v-icon small left>mdi-{{ provider.id }}</v-icon>-->
<!--                        {{ provider.label }}-->
<!--                    </v-btn>-->

<!--                    <div v-if="errorProvider" class="error&#45;&#45;text">{{ errorProviderMessages }}</div>-->

                    <div class="mt-5">
                        <router-link to="/auth/forgot-password">
                            {{ $t('login.forgot') }}
                        </router-link>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>

<!--        <div class="text-center mt-6">-->
<!--            {{ $t('login.noaccount') }}-->
<!--            <router-link to="/auth/signup" class="font-weight-bold">-->
<!--                {{ $t('login.create') }}-->
<!--            </router-link>-->
<!--        </div>-->
    </div>
</template>

<!--<script>-->
<!--/*-->
<!--|-&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;-->
<!--| Sign In Page Component-->
<!--|-&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;&#45;-->
<!--|-->
<!--| Sign in template for user authentication into the application-->
<!--|-->
<!--*/-->

<!--// import {mapActions, mapGetters} from 'vuex'-->
<!--import {useAuthStore} from "@/store/authStore.js";-->

<!--export default {-->
<!--    data() {-->
<!--        return {-->
<!--            form: {-->
<!--                email: '',-->
<!--                password: '',-->
<!--            },-->
<!--            // sign in buttons-->
<!--            isLoading: false,-->
<!--            isSignInDisabled: false,-->

<!--            // form-->
<!--            isFormValid: true,-->
<!--            email: '',-->
<!--            password: '',-->

<!--            // form error-->
<!--            error: false,-->
<!--            errorUsernameMessages:"",-->
<!--            errorMessages: {-->
<!--                message: '',-->
<!--                username:'',-->
<!--                email:'',-->
<!--                password:''-->
<!--            },-->

<!--            errorProvider: false,-->
<!--            errorProviderMessages: '',-->

<!--            // show password field-->
<!--            showPassword: false,-->

<!--            providers: [{-->
<!--                id: 'google',-->
<!--                label: 'Google',-->
<!--                isLoading: false-->
<!--            }, {-->
<!--                id: 'facebook',-->
<!--                label: 'Facebook',-->
<!--                isLoading: false-->
<!--            }],-->

<!--            // input rules-->
<!--            rules: {-->
<!--                required: (value) => (value && Boolean(value)) || 'Required',-->
<!--                email: value => {-->
<!--                    const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/-->
<!--                    return pattern.test(value) || 'Invalid e-mail.'-->
<!--                },-->
<!--            }-->
<!--        }-->
<!--    },-->
<!--    methods: {-->
<!--        async signIn(credentials) {-->
<!--            const auth = useAuthStore()-->
<!--            await auth.login(this.form)-->
<!--        },-->
<!--        async submit () {-->
<!--            this.isLoading = true-->
<!--            this.isSignInDisabled = true-->


<!--            await this.signIn(this.form).then(() => {-->
<!--                console.log('test yes')-->
<!--                if(this.isVerified) {-->
<!--                    this.$router.push({name: 'dashboard'})-->
<!--                } else {-->
<!--                    this.$router.push({name: 'auth-verify-email'})-->
<!--                }-->
<!--            })-->
<!--                .catch(error => {-->
<!--                    console.log('test no', error)-->
<!--                    const data = error.response.data;-->

<!--                    this.error = true-->
<!--                    this.errorMessages.message = data.message-->
<!--                    if(data.errors.email !== undefined) {-->
<!--                        this.errorMessages.email = data.errors.email[0]-->
<!--                    }-->
<!--                    if(data.errors.username !== undefined) {-->
<!--                        this.errorMessages.username = data.errors.username[0]-->
<!--                    }-->
<!--                    this.errorUsernameMessages = this.errorMessages.username ?? this.errorMessages.email-->
<!--                    this.isLoading = false-->
<!--                    this.isSignInDisabled = false-->
<!--                });-->


<!--        },-->
<!--        resetErrors() {-->
<!--            this.error = false-->
<!--            this.errorMessages = {-->
<!--                message: '',-->
<!--                email:'',-->
<!--                password:''-->
<!--            }-->

<!--            this.errorProvider = false-->
<!--            this.errorProviderMessages = ''-->
<!--        }-->
<!--    },-->
<!--    computed: {-->
<!--        authenticated() {-->
<!--            const authStore = useAuthStore();-->
<!--            return authStore.authenticated;-->
<!--        },-->
<!--        isVerified() {-->
<!--            const authStore = useAuthStore();-->
<!--            return authStore.isVerified;-->
<!--        },-->
<!--    }-->
<!--}-->
<!--</script>-->


<script setup>
import { reactive, defineAsyncComponent  } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/store/authStore.js'

const router = useRouter()
const auth = useAuthStore()
const isLoading = false;
const isSignInDisabled = false;
const showPassword = false;

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

const errorMessages = {
    message: null,
    email: null,
    username: null,
    password: null,
}

const error = reactive({})
const message = reactive({})

// const rules =reactive({required: (value) => (value && Boolean(value)) || 'Required',
//                email: value => {
//                    const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
//                    return pattern.test(value) || 'Invalid e-mail.'
//                }})

const submit = async () => {
    try {
        await auth.login(credentials)
    } catch (error) {
        console.log(error.message)
    }
    await router.push('/')
}

</script>
