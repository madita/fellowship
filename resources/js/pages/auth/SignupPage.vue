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
                        :rules="[rules.required]"
                        :validate-on-blur="false"
                        :error="errorName"
                        :error-messages="errorNameMessage"
                        :label="$t('register.name')"
                        name="name"
                        outlined
                        @keyup.enter="submit"
                        @change="resetErrors"
                    ></v-text-field>

                    <v-text-field
                        v-model="user.email"
                        :rules="[rules.required]"
                        :validate-on-blur="false"
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
                        x-large
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
                        class="mb-2 primary lighten-2 primary--text text--darken-3"
                        block
                        x-large
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
import {mapActions} from 'vuex'

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
                required: (value) => (value && Boolean(value)) || 'Required'
            }
        }
    },
    methods: {
        ...mapActions({
            signIn: 'auth/login'
        }),
        async register() {
            // this.processing = true
            await axios.post('/register', this.user).then(response => {
                this.signIn()
                this.$router.replace({ name: 'dashboard' })
            }).catch(({response: {data}}) => {
                alert(data.message)
            }).finally(() => {
                this.processing = false
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
        signUp(email, password) {

            axios.post('/register', this.form).then(response => {
                console.log('User registered!');
                //this.$router.push('/dashboard')
            }).catch(error => console.log(error));

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
    }
}
</script>
