<template>
    <div>
        <v-card class="text-center pa-1">
            <v-card-title class="justify-center display-1 mb-2">Welcome</v-card-title>
            <v-card-subtitle>Sign in to your account</v-card-subtitle>

            <!-- sign in form -->
            <v-card-text>
                <v-form ref="form" v-model="isFormValid" lazy-validation>
                    <v-text-field
                        v-model="form.email"
                        :rules="[rules.required]"
                        :validate-on-blur="false"
                        :error="error"
                        :label="$t('login.email')"
                        name="email"
                        outlined
                        @keyup.enter="submit"
                        @change="resetErrors"
                    ></v-text-field>

                    <v-text-field
                        v-model="form.password"
                        :append-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                        :rules="[rules.required]"
                        :type="showPassword ? 'text' : 'password'"
                        :error="error"
                        :error-messages="errorMessages"
                        :label="$t('login.password')"
                        name="password"
                        outlined
                        @change="resetErrors"
                        @keyup.enter="submit"
                        @click:append="showPassword = !showPassword"
                    ></v-text-field>

                    <v-btn
                        :loading="isLoading"
                        :disabled="isSignInDisabled"
                        block
                        x-large
                        color="primary"
                        @click="submit"
                    >{{ $t('login.button') }}
                    </v-btn>

                    <div class="caption font-weight-bold text-uppercase my-3">{{ $t('login.orsign') }}</div>

                    <!-- external providers list -->
                    <v-btn
                        v-for="provider in providers"
                        :key="provider.id"
                        :loading="provider.isLoading"
                        :disabled="isSignInDisabled"
                        class="mb-2 primary lighten-2 primary--text text--darken-3"
                        block
                        x-large
                        to="/"
                    >
                        <v-icon small left>mdi-{{ provider.id }}</v-icon>
                        {{ provider.label }}
                    </v-btn>

                    <div v-if="errorProvider" class="error--text">{{ errorProviderMessages }}</div>

                    <div class="mt-5">
                        <router-link to="/auth/forgot-password">
                            {{ $t('login.forgot') }}
                        </router-link>
                    </div>
                </v-form>
            </v-card-text>
        </v-card>

        <div class="text-center mt-6">
            {{ $t('login.noaccount') }}
            <router-link to="/auth/signup" class="font-weight-bold">
                {{ $t('login.create') }}
            </router-link>
        </div>
    </div>
</template>

<script>
/*
|---------------------------------------------------------------------
| Sign In Page Component
|---------------------------------------------------------------------
|
| Sign in template for user authentication into the application
|
*/
import axios from 'axios'
import { mapActions } from 'vuex'
export default {
    data() {
        return {
            form: {
                email: '',
                password: '',
            },
            // sign in buttons
            isLoading: false,
            isSignInDisabled: false,

            // form
            isFormValid: true,
            email: '',
            password: '',

            // form error
            error: false,
            errorMessages: '',

            errorProvider: false,
            errorProviderMessages: '',

            // show password field
            showPassword: false,

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
            signIn: 'auth/signIn'
        }),
        async submit () {
            this.isLoading = true
            this.isSignInDisabled = true


            // await this.signIn(this.form).then((response) => {
            //     console.log('response',response)
            //     this.availabilityMessage = response.data.message;
            // }).catch((error) => {
            //     console.log('error33',error)
            //     this.availabilityMessage = false;
            //
            // })

            try {
                const data = await this.signIn(this.form)
                this.$router.replace({ name: 'dashboard' })
            } catch (error) {
                console.log('error',error)
                this.error = true
                this.errorMessages = error.email
                this.isLoading = false
                this.isSignInDisabled = false
            }
        },
        // submit() {
        //     if (this.$refs.form.validate()) {
        //         this.isLoading = true
        //         this.isSignInDisabled = true
        //         this.handleLogin();
        //         //this.signIn(this.email, this.password)
        //     }
        // },
        // // signIn(email, password) {
        // //   this.$router.push('/')
        // // },
        // signInProvider(provider) {
        // },
        // handleLogin() {
        //     axios.get('/sanctum/csrf-cookie').then(response => {
        //         axios.post('/login', this.form).then(response => {
        //             console.log('User signed in!');
        //             this.$router.push('/dashboard')
        //         }).catch(error => console.log(error)); // credentials didn't match
        //     });
        // },
        resetErrors() {
            this.error = false
            this.errorMessages = ''

            this.errorProvider = false
            this.errorProviderMessages = ''
        }
    }
}
</script>
