<template>
  <v-container class="pt-0 pb-4">
    <v-row>
      <v-col
        v-for="(item, i) in stats"
        :key="i"
        cols="12"
        sm="6"
        lg="3"
      >
        <div class="text-center">
          <div class="text-h2 text-number font-weight-light">{{ item.value }}</div>
          <v-responsive max-width="300" class="mx-auto">
            <div class="font-weight-regular my-2">{{ item.description || 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit.' }}</div>
            <div class="text-h6 text-lg-h5">{{ item.title }}</div>
          </v-responsive>
        </div>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  content: {
    type: Object,
    required: false,
    default: () => ({
      stats: [{
        title: 'Projects',
        value: '4,253',
        description: 'Lorem ipsum dolor sit amet'
      }, {
        title: 'API Requests',
        value: '1,283,787',
        description: 'Lorem ipsum dolor sit amet'
      }, {
        title: 'Subscribers',
        value: '1,348',
        description: 'Lorem ipsum dolor sit amet'
      }, {
        title: 'Businesses',
        value: '331,234',
        description: 'Lorem ipsum dolor sit amet'
      }]
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const stats = computed(() => props.content?.stats || []);
</script>
