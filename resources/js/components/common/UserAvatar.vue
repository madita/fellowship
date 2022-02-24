<template>
    <v-avatar color="red" size="45">
        <v-img v-if="user.avatar" :src="user.avatar"></v-img>
        <span class="white--text text-h5">{{user.initials}}</span>
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

    }
  },
  beforeDestroy() {

  },
  methods: {

  }
}
</script>

