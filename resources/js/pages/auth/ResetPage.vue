<template>
    <v-card class="pa-2" elevation="4">
        <v-card-title class="justify-center text-h5 mb-2">{{ $t('auth.setNewPassword') }}</v-card-title>
        <div class="text-overline text-medium-emphasis">{{ status }}</div>

        <v-form ref="form" v-model="isFormValid" lazy-validation @submit.prevent="submit">
            <v-text-field
                v-model="newPassword"
                :append-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                :rules="[rules.required]"
                :type="showPassword ? 'text' : 'password'"
                :error="errorNewPassword"
                :error-messages="errorNewPasswordMessage"
                name="password"
                :label="$t('auth.newPassword')"
                variant="outlined"
                class="mt-4"
                :disabled="isLoading"
                @change="resetErrors"
                @keyup.enter="submit"
                @click:append="showPassword = !showPassword"
            ></v-text-field>

            <v-text-field
                v-model="passwordConfirmation"
                :rules="[rules.required]"
                :type="'password'"
                :error="errorNewPassword"
                :error-messages="errorNewPasswordMessage"
                :label="$t('register.password')"
                name="password_confirmation"
                variant="outlined"
                :disabled="isLoading"
                @change="resetErrors"
                @keyup.enter="submit"
            ></v-text-field>
        </v-form>
        <v-btn
            :loading="isLoading"
            block
            variant="flat"
            size="large"
            color="primary"
            @click="submit"
        >{{ $t('auth.setNewPasswordAndSignIn') }}
        </v-btn>
    </v-card>
</template>

<script>
/*
|---------------------------------------------------------------------
| Reset Page Component
|---------------------------------------------------------------------
|
| Page Form to insert new password and proceed to sign in
|
*/
import axios from "axios";

export default {
    data() {
        return {
            isLoading: false,

            isFormValid: true,

            token:'',
            email:'',

            newPassword: '',
            passwordConfirmation: '',

            // form error (shown next to the password fields)
            errorNewPassword: false,
            errorNewPasswordMessage: '',

            // show password field
            showPassword: false,

            status: this.$t ? this.$t('auth.resettingPassword') : 'Resetting password',

            // input rules
            rules: {
                required: (value) => (value && Boolean(value)) || this.$t('auth.required')
            }
        }
    },
    methods: {
        async confirmPasswordReset() {
            if (this.isLoading) return
            this.isLoading = true

            let data = {
                token: this.token,
                email: this.email,
                password: this.newPassword,
                password_confirmation: this.passwordConfirmation
            }

            try {
                const response = await axios.post('/password/reset', data)
                await this.$dialog.success(response.data?.message || this.$t('auth.passwordResetDone'))
                this.$router.push('/auth/signin')
            } catch (e) {
                const errors = e.response?.data?.errors
                if (errors?.password !== undefined) {
                    // Validation error of the field: stays inline
                    this.errorNewPassword = true
                    this.errorNewPasswordMessage = errors.password[0]
                } else if (errors) {
                    // Invalid token / e-mail: nothing to fix in the form
                    await this.$dialog.error(Object.values(errors).flat().join('\n'))
                } else {
                    await this.$dialog.requestError(e)
                }
            } finally {
                this.isLoading = false
            }
        },
        async submit() {
            if (this.isLoading) return
            this.token = this.$route.params.token
            this.email = this.$route.query.email

            const { valid } = await this.$refs.form.validate()
            if (valid) {
                this.confirmPasswordReset()
            }
        },
        resetErrors() {
            this.errorNewPassword = false
            this.errorNewPasswordMessage = ''
        }
    }
}
</script>
