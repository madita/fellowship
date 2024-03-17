<template>
    <v-container class="py-4" id="feature1">
        <v-row>
            <v-col v-for="(item, i) in features" :key="i" cols="12" md="6">
                <div class="d-flex">
                    <div class="mr-2">
                        <v-sheet variant="outlined" class="pa-2 elevation-1 rounded-te">
                            <v-icon>{{ item.icon }}</v-icon>
                        </v-sheet>
                    </div>
                    <div>
                        <div class="text-h5">{{ item.title }}</div>
                        <div class="text-h6 font-weight-light mt-1">{{ item.description }}</div>
                    </div>
                </div>
            </v-col>
        </v-row>
    </v-container>
</template>

<script>
import { ref } from 'vue';

export default {
    setup() {
        const features = ref([
            {
                icon: 'mdi-account-check-outline',
                title: 'Account Verification',
                description: 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit quam aliquam. Autem assumenda'
            },
            {
                icon: 'mdi-lifebuoy',
                title: 'Dedicated Support',
                description: 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit quam aliquam. Autem assumenda'
            },
            {
                icon: 'mdi-email-open-multiple-outline',
                title: 'Email Integration',
                description: 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit quam aliquam. Autem assumenda'
            },
            {
                icon: 'mdi-clock-outline',
                title: 'Save Time',
                description: 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse expedita fugit quam aliquam. Autem assumenda'
            }
        ]);

        return {
            features
        }
    }
}
</script>
