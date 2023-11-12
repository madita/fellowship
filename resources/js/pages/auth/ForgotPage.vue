<template>
    <div>
        <v-alert v-if="message" type="success">
            {{ message }}
        </v-alert>
        <v-card class="text-center pa-1">
            <v-card-title class="justify-center display-1 mb-2">{{ $t('forgot.title') }}</v-card-title>
            <v-card-subtitle>
                {{ $t('forgot.subtitle') }}
            </v-card-subtitle>

            <!-- reset form -->
            <v-card-text>
                <v-form ref="form" v-model="isFormValid" lazy-validation @submit.prevent="submit">
                    <v-text-field
                        v-model="email"
                        :rules="[rules.required, rules.email]"
                        validate-on="blur"
                        :error="error"
                        :error-messages="errorMessages"
                        :label="$t('forgot.email')"
                        name="email"
                        variant="outlined"
                        @keyup.enter="submit"
                        @change="resetErrors"
                    ></v-text-field>

                    <v-btn
                        :loading="isLoading"
                        block
                        size="large"
                        color="primary"
                        @click="submit"
                    >{{ $t('forgot.button') }}
                    </v-btn>
                </v-form>
            </v-card-text>
        </v-card>

        <div class="text-center mt-6">
            <router-link to="/auth/signin">
                {{ $t('forgot.backtosign') }}
            </router-link>
        </div>

    </div>
</template>

<script>
/*
|---------------------------------------------------------------------
| Forgot Page Component
|---------------------------------------------------------------------
|
| Template to send email to remember/replace password
|
*/
import axios from "axios";

export default {
    data() {
        return {
            // reset button
            isLoading: false,

            // form
            isFormValid: true,
            email: '',

            // form error
            error: false,
            errorMessages: '',
            message: '',

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
        async forgotPassword(email) {
            this.isLoading = true
            await axios.post('/password/email', {email: email}).then(response => {
                this.message = response.data.message
            }).catch(({response: {data}}) => {
                if (data.errors.email !== undefined) {
                    this.error = true
                    this.errorMessages = data.errors.email[0]
                }
            }).finally(() => {
                this.isLoading = false
            })
        },
        submit(e) {
            this.resetErrors();
            if (this.$refs.form.validate()) {
                this.forgotPassword(this.email)
            }
        },
        resetEmail(email, password) {
        },
        resetErrors() {
            this.error = false
            this.errorMessages = ''
        }
    }
}
</script>
