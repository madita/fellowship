<template>
  <div class="flex-grow-1">
      <v-container>
          <calendar v-if="!loading" :data="events"></calendar>
      </v-container>
  </div>
</template>

<script>
import Calendar from "../common/Calendar.vue";
export default {
    components:{
        Calendar
    },
    data: () => ({
        loading: true,
        events: null
    }),
    mounted() {
        this.getEvents();
    },
    methods: {
        getEvents(){
            this.loading = true
            return axios.get(`/api/events`).then((response) => {
                this.events = response.data.data.events

                this.loading = false
            });
        }
    },
}
</script>
