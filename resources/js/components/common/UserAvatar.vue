<template>
    <v-avatar :color="background" size="45">
        <v-img v-if="user.avatar" :src="user.avatar"></v-img>
        <span v-else :style="styleObject" :class="user.initials.length === 1 ? 'text-h4' : 'text-h5'">{{ user.initials }}</span>
    </v-avatar>
</template>

<script>
/*
|---------------------------------------------------------------------
| Copy Label Component
|---------------------------------------------------------------------
|
| Copy to clipboard with the plugin clipboard `this.$clipboard`
|
*/

export default {
    props: {
        user: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            backgroundColour: "",
            styleObject: {
                color: this.fontColour,
            }
        }
    },
    beforeDestroy() {

    },
    computed: {
        background() {
            if(this.user != null)
            return this.user.colour || this.$helpers.randomBackgroundColor(this.user.username.length, null)
        },

        fontColour() {
            return this.$helpers.lightenColor(this.backgroundColour, null)
        },
    },
    methods: {
        mounted() {
            console.log('colors', this.$helpers.backgroundColors)
        }
    }
}
</script>

