<template>
  <div class="flex-grow-1">
      <v-container>
          <v-select
              v-model="type"
              :items="types"
              class="ma-2"
              label="View Mode"
              variant="outlined"
              dense
              hide-details
          ></v-select>
          <v-sheet v-if="type=='list'">
              <v-list lines="two">
                  <v-list-item
                      v-for="event in events"
                      :key="event.id"
                      :title="event.title"
                  >
                      <template v-slot:subtitle="{ subtitle }">
                          <span class="text-primary">{{event.start}} {{event.end}}</span> &mdash;
                          <div v-html="event.description"></div>
                      </template>

                  </v-list-item>
              </v-list>

          </v-sheet>
          <v-row v-else>
              <v-col cols="2">

                  <v-list lines="two">
                      <v-list-item
                          v-for="event in filteredEvents"
                          :key="event.id"
                          :title="event.title"
                      >
                          <template v-slot:subtitle="{ subtitle }">
                              <span class="text-primary">{{event.start}} {{event.end}}</span> &mdash;
                              <div v-html="event.description"></div>
                          </template>

                      </v-list-item>
                  </v-list>
              </v-col>
              <v-col>
                  <v-sheet>
                      <v-calendar
                          ref="calendar"
                          v-model:modelValue="value"
                          :view-mode="type"
                          :events="events"
                          @update:modelValue="updateDate"
                      ></v-calendar>
                  </v-sheet>
              </v-col>
          </v-row>

      </v-container>

  </div>
</template>

<script setup>
//import Calendar from "../common/Calendar.vue";
//import { useDate } from 'vuetify'


import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { VCalendar } from 'vuetify/labs/VCalendar'
import {getDate, format, formatDistanceToNow} from "date-fns";

// Sample API call to fetch events (update the URL as per your backend)
// const fetchEvents = () => axios.get('/api/events').then((res) => res.data.data.events);

const fetchEvents = async () => {
    try {
        const response = await axios.get('/api/events');
        const events = response.data.data.events.map(event => ({
            ...event,
            start: new Date(event.start),
            end: new Date(event.end),
        }));
        return events; // Returns the processed events array.
    } catch (error) {
        console.error("Error fetching events:", error);
        return []; // Return an empty array in case of an error.
    }
};



const updateDate = (date) => {
    // console.log('date', date);
    getDateRange(date[0])
};

const getDateRange = () => {

    let date = value.value[0];
    fromDate.value = new Date(date.getTime());
    toDate.value = new Date(date.getTime());

    if(type.value === 'month') {
        fromDate.value = new Date(date.getFullYear(), date.getMonth(), 1);
        toDate.value = new Date(date.getFullYear(), date.getMonth() + 1, 0,23,59,59);
    }

    if(type.value == 'week') {
        fromDate.value.setDate(date.getDate() -  date.getDay());
        toDate.value.setDate(date.getDate() + (6 - date.getDay()));
    }

    if(type.value == 'day') {
        fromDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        toDate.value = new Date(date.getFullYear(), date.getMonth(), date.getDate(), 23,59,59);
    }
    // Handle the scenario when the user clicks on the "more" button to see more events
};

const filteredEvents = computed( () => {
    // let type = filterText.value

    if(loading.value) return events.value

    getDateRange(value.value)

    return events.value.filter((item) => {

        return item.start.getTime() >= fromDate.value.getTime() &&
            item.end.getTime() <= toDate.value.getTime();
    });
})

const showEvent = (event) => {
    console.log('test',event)
}


const loading = ref(false);
const events = ref([]);
const value = ref([new Date()]);
const type = ref('month');
const types = ref(['month', 'week', 'day', 'list']);
const fromDate = ref();
const toDate = ref();

onMounted(async () => {
    loading.value = true;
    events.value = await fetchEvents();
    loading.value = false;
});
</script>

<style scoped>
.flex-grow-1 {
    flex-grow: 1;
}
</style>
