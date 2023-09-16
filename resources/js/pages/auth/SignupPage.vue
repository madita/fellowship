<template>
    <div>
        <v-card class="text-center pa-1">
            <v-card-title class="justify-center display-1 mb-2">{{ $t('register.title') }}</v-card-title>
            <v-card-subtitle>Let's build amazing products</v-card-subtitle>

            <!-- sign up form -->
            <v-card-text>
                <v-form ref="form" v-model="isFormValid" lazy-validation>
                    <v-text-field
                        v-model="user.name"
                        validate-on="blur"
                        :error="errorName"
                        :error-messages="errorNameMessage"
                        :label="$t('register.name')"
                        name="name"
                        outlined
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
                        outlined
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
                        outlined
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
                        outlined
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
                        outlined
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

                    <div class="caption font-weight-bold text-uppercase my-3">{{ $t('register.orsign') }}</div>

                    <!-- external providers list -->
                    <v-btn
                        v-for="provider in providers"
                        :key="provider.id"
                        :loading="provider.isLoading"
                        :disabled="isSignUpDisabled"
                        class="mb-2 primary lighten-2 text-primary text--darken-3"
                        block
                        size="large"
                        @click="signInProvider(provider)"
                    >
                        <v-icon small left>mdi-{{ provider.id }}</v-icon>
                        {{ provider.label }}
                    </v-btn>

                    <div v-if="errorProvider" class="error--text">{{ errorProviderMessages }}</div>

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

<script>
/*
|---------------------------------------------------------------------
| Sign Up Page Component
|---------------------------------------------------------------------
|
| Template for user sign up with external providers buttons
|
*/
import {mapActions, mapGetters} from 'vuex'

export default {
    data() {
        return {
            // sign up buttons
            isLoading: false,
            isSignUpDisabled: false,

            // form
            isFormValid: true,
            email: '',
            password: '',
            name: '',

            user: {
                name: "",
                email: "",
                password: "",
                password_confirmation: ""
            },

            // form error
            errorName: false,
            errorEmail: false,
            errorPassword: false,
            errorNameMessage: '',
            errorEmailMessage: '',
            errorPasswordMessage: '',

            errorProvider: false,
            errorProviderMessages: '',

            // show password field
            showPassword: false,

            // external providers
            providers: [{
                id: 'google',
                label: 'Google',
                isLoading: false
            }, {
                id: 'facebook',
                label: 'Facebook',
                isLoading: false
            }],

            // input rules
            rules: {
                required: (value) => (value && Boolean(value)) || 'Required',
                email: value => {
                    const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                    return pattern.test(value) || 'Invalid e-mail.'
                },
            }
        }
    },
    methods: {
        ...mapActions({
            signIn: 'auth/signIn'
        }),
        async register() {
            this.processing = true
            await axios.post('/register', this.user).then(() => {
                this.signIn().then(() => {
                    if(this.isVerified) {
                        this.$router.replace({name: 'dashboard'})
                    } else {
                        this.$router.replace({name: 'auth-verify-email'})
                    }
                    // this.$router.replace({name: 'dashboard'})
                }).catch(error => {
                    console.log("WTF???" , error)
                });
            }).catch(({response: {data}}) => {
                if (data.errors.name !== undefined) {
                    this.errorName = true
                    this.errorNameMessage = data.errors.name[0]
                }

                if (data.errors.email !== undefined) {
                    this.errorEmail = true
                    this.errorEmailMessage = data.errors.email[0]
                }

                if (data.errors.password !== undefined) {
                    this.errorPassword = true
                    this.errorPasswordMessage = data.errors.password[0]
                }
            }).finally(() => {
                this.isLoading = false
                this.isSignUpDisabled = false
            })
        },
        submit() {
            // if (this.$refs.form.validate()) {
            this.isLoading = true
            this.isSignUpDisabled = true
            this.register()
            // this.signUp(this.email, this.password)
            // }
        },
        signInProvider(provider) {
        },
        resetErrors() {
            this.errorName = false
            this.errorEmail = false
            this.errorPassword = false
            this.errorNameMessage = ''
            this.errorEmailMessage = ''
            this.errorPasswordMessage = ''

            this.errorProvider = false
            this.errorProviderMessages = ''
        }
    },
    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            isVerified: 'auth/isVerified',
        })
    }
}
</script>
