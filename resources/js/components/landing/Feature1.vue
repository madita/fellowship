<template>
    <v-sheet>
        <v-container class="py-4 py-lg-8">
            <v-row>
                <v-col cols="12" lg="6">
                    <div class="text-uppercase font-weight-bold body-2 text-primary mb-2 mt-0 mt-xl-10">Work with us</div>
                    <h2 class="text-h3">{{ content.title }}</h2>
                    <div class="text-h6 text-lg-h5 mt-5">
                        {{ content.description }}
                    </div>
                </v-col>
                <v-col cols="12" lg="6">
                    <v-img :src="feature1" class="elevation-6" max-height="480" alt="Startup Feature"></v-img>
                </v-col>
            </v-row>
        </v-container>
    </v-sheet>
</template>

<script setup>
import { computed } from 'vue';
import feature1Img from '@/assets/images/feature1.jpg';

const props = defineProps({
  content: {
    type: Object,
    required: false,
    default: () => ({
      title: 'Get your startup ready for business',
      description: 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Natus impedit error labore doloremque fugit! Dolor fugit molestiae vero quos quisquam nobis, eos debitis magni omnis ea incidunt amet voluptate dignissimos!',
      image: null
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const feature1 = computed(() => props.content.image || feature1Img);
</script>
