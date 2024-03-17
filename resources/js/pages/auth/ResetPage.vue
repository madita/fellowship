<template>
    <v-card class="pa-2">
        <v-alert
            v-for="(message,index) in errorMessages"
            :key="`error-${index}`"
            type="error">
            {{ message[0] }}
        </v-alert>
        <v-card-title class="justify-center display-1 mb-2">Set new password</v-card-title>
        <div class="overline">{{ status }}</div>
<!--        <div class="error&#45;&#45;text mt-2 mb-4">{{ error }}</div>-->

<!--        <a v-if="error" href="/">Back to Sign In</a>-->
        <v-form ref="form" v-model="isFormValid" lazy-validation @submit.prevent="submit">
            <v-text-field
                v-model="newPassword"
                :append-icon="showPassword ? 'mdi-eye' : 'mdi-eye-off'"
                :rules="[rules.required]"
                :type="showPassword ? 'text' : 'password'"
                :error="errorNewPassword"
                :error-messages="errorNewPasswordMessage"
                name="password"
                label="New Password"
                variant="outlined"
                class="mt-4"
                @change="resetErrors"
                @keyup.enter="confirmPasswordReset"
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
        >Set new password and Sign In
        </v-btn>
    </v-card>
</template>

this.$router.push('/auth/verify-email')
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

            showNewPassword: true,
            newPassword: '',
            passwordConfirmation: '',

            // form error
            errorNewPassword: false,
            errorNewPasswordMessage: '',

            // form error
            // errorNewPassword: false,
            errorMessages: {},

            errorEmailMessage: '',
            errorTokenMessage: '',

            // show password field
            showPassword: false,

            status: 'Resetting password',
            error: null,

            // input rules
            rules: {
                required: (value) => (value && Boolean(value)) || 'Required'
            }
        }
    },
    methods: {
        async confirmPasswordReset() {
            this.isLoading = true

            let data = {
                token: this.token,
                email: this.email,
                password: this.newPassword,
                password_confirmation: this.passwordConfirmation
            }

            await axios.post('/password/reset', data).then(response => {
                this.message = response.message
            }).catch(({response: {data}}) => {
                this.errorMessages = data.errors
                if (data.errors.email !== undefined) {
                    this.error = true
                    this.errorEmailMessage = data.errors.email[0]
                }
            }).finally(() => {
                this.isLoading = false
            })
        },
        submit() {
            this.token = this.$route.params.token
            this.email = this.$route.query.email

            if (this.$refs.form.validate()) {
                this.confirmPasswordReset(this.email)
            }
        },
        resetErrors() {
            this.errorNewPassword = false
            this.errorNewPasswordMessage = ''
        }
    }
}
</script>
