<template>
    <div>
        <v-alert v-if="isVerified" type="warning">
            You are alredy veryfied :)
        </v-alert>
        <v-alert v-if="error" type="error">
            Oops! Something went wrong!
        </v-alert>
        <v-alert v-if="message" type="success">
            {{ message }}
        </v-alert>
        <v-card class="pa-2">
            <h1>Please verify the email</h1>
            <div class="mb-6 overline">Please check your email for the link to verify the email.</div>

            <v-btn
                :loading="isLoading"
                :disabled="disabled"
                block
                depressed
                size="large"
                color="primary"
                @click="submit"
            >Re-send email {{ seconds }}
            </v-btn>
        </v-card>
    </div>

</template>

<script>
/*
|---------------------------------------------------------------------
| Verify Email Page Component
|---------------------------------------------------------------------
|
| Template to wait for the verification on the user email
|
*/

import axios from "axios";
import {mapGetters} from "vuex";

const TIMEOUT = 10

export default {
    data() {
        return {
            isLoading: false,
            disabled: false,
            times: 0,
            resendInterval: null,
            secondsToEnable: TIMEOUT,
            seconds: '',
            error: false,
            message: ''
        }
    },
    beforeDestroy() {
        clearInterval(this.resendInterval)
    },
    mounted() {
       this.disabled = this.isVerified
    },
    methods: {
        async resend() {
            this.isLoading = true
            await axios.post('/email/resend').then(() => {
                this.message = "Email was sent!"
            }).catch(({response: {data}}) => {
                if (data.errors.email !== undefined) {
                    this.error = true
                    this.errorMessages = data.errors.email[0]
                }
            }).finally(() => {
                this.isLoading = false
            })
        },
        submit() {
            this.setTimer()
            this.resend()
        },

        // async resend() {
        //   this.setTimer()
        // },
        setTimer() {
            this.message = "";
            this.disabled = true
            this.times++
            this.secondsToEnable = TIMEOUT * this.times

            this.resendInterval = setInterval(() => {
                if (this.secondsToEnable === 0) {
                    clearInterval(this.resendInterval)
                    this.seconds = ''
                    this.disabled = false
                } else {
                    this.seconds = `( ${this.secondsToEnable} )`
                    this.secondsToEnable--
                }
            }, 1000)
        },
    },
    computed: {
        ...mapGetters({
            authenticated: 'auth/authenticated',
            isVerified: 'auth/isVerified',
        })
    }
}
</script>
