<template>
    <div>
        <v-card class="text-center pa-1" elevation="4">
            <v-card-title class="justify-center text-h5 mb-2">{{ $t('forgot.title') }}</v-card-title>
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
                        :disabled="isLoading"
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

            // input rules
            rules: {
                required: (value) => (value && Boolean(value)) || this.$t('validation.required'),
                email: value => {
                    const pattern = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
                    return pattern.test(value) || this.$t('validation.email')
                },
            }
        }
    },
    methods: {
        async forgotPassword(email) {
            if (this.isLoading) return
            this.isLoading = true
            try {
                const response = await axios.post('/password/email', {email: email})
                await this.$dialog.success(response.data?.message || this.$t('forgot.sent'))
            } catch (e) {
                const errors = e.response?.data?.errors
                if (errors?.email !== undefined) {
                    // Validation error: stays next to the field
                    this.error = true
                    this.errorMessages = errors.email[0]
                } else {
                    await this.$dialog.requestError(e)
                }
            } finally {
                this.isLoading = false
            }
        },
        async submit() {
            if (this.isLoading) return
            this.resetErrors();
            const { valid } = await this.$refs.form.validate()
            if (valid) {
                this.forgotPassword(this.email)
            }
        },
        resetErrors() {
            this.error = false
            this.errorMessages = ''
        }
    }
}
</script>
