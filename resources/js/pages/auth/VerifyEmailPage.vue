<template>
    <div>
        <!-- Persistent state banner: the account is already verified -->
        <v-alert v-if="isVerified" type="warning">
            {{ $t('auth.alreadyVerified') }}
        </v-alert>
        <v-card class="pa-2" elevation="4">
            <v-card-title class="justify-center text-h5 mb-2">{{ $t('auth.verifyEmailTitle') }}</v-card-title>
            <div class="mb-6 text-overline text-medium-emphasis">{{ $t('auth.verifyEmailHint') }}</div>

            <v-btn
                :loading="isLoading"
                :disabled="disabled"
                block
                variant="flat"
                size="large"
                color="primary"
                @click="submit"
            >{{ $t('auth.resendEmail') }} {{ seconds }}
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
import {useAuthStore} from "@/store/authStore.js";

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
        }
    },
    beforeUnmount() {
        clearInterval(this.resendInterval)
    },
    mounted() {
       this.disabled = this.isVerified
    },
    methods: {
        async resend() {
            if (this.isLoading) return
            this.isLoading = true
            try {
                await axios.post('/email/resend')
                await this.$dialog.success(this.$t('auth.verificationSent'))
            } catch (e) {
                await this.$dialog.requestError(e)
            } finally {
                this.isLoading = false
            }
        },
        submit() {
            if (this.isLoading || this.disabled) return
            this.setTimer()
            this.resend()
        },

        setTimer() {
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
        authenticated() {
            const authStore = useAuthStore();
            return authStore.isLoggedIn;
        },
        isVerified() {
            const authStore = useAuthStore();
            return authStore.isVerified;
        },
    }
}
</script>
