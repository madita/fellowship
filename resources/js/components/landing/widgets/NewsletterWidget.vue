<template>
  <v-sheet class="py-16" :class="content.backgroundColor || 'bg-primary'">
    <v-container>
      <v-row justify="center">
        <v-col cols="12" md="8" lg="6">
          <div class="text-center mb-8">
            <v-icon
              v-if="content.showIcon"
              size="64"
              :color="content.iconColor || 'white'"
              class="mb-4"
            >
              {{ content.icon || 'mdi-email-newsletter' }}
            </v-icon>

            <h2 class="text-h3 text-md-h2 font-weight-bold mb-4" :class="content.textColor || 'text-white'">
              {{ content.title }}
            </h2>

            <p v-if="content.description" class="text-h6" :class="content.textColor || 'text-white'">
              {{ content.description }}
            </p>
          </div>

          <v-card class="pa-4" elevation="8">
            <v-form @submit.prevent="handleSubscribe">
              <div class="d-flex flex-column flex-sm-row gap-2">
                <v-text-field
                  v-model="email"
                  :label="content.emailPlaceholder || 'Enter your email'"
                  :required="true"
                  type="email"
                  variant="outlined"
                  hide-details="auto"
                  class="flex-grow-1"
                ></v-text-field>

                <v-btn
                  type="submit"
                  color="primary"
                  size="large"
                  :loading="isSubscribing"
                  class="subscribe-btn"
                >
                  {{ content.buttonText || 'Subscribe' }}
                </v-btn>
              </div>

              <v-alert
                v-if="subscribeMessage"
                :type="subscribeSuccess ? 'success' : 'error'"
                variant="tonal"
                class="mt-4"
                density="compact"
              >
                {{ subscribeMessage }}
              </v-alert>

              <p v-if="content.privacyText" class="text-caption text-grey mt-3 text-center">
                {{ content.privacyText }}
              </p>
            </v-form>
          </v-card>

          <div v-if="content.benefits && content.benefits.length > 0" class="mt-8">
            <v-row>
              <v-col
                v-for="(benefit, index) in content.benefits"
                :key="index"
                cols="12"
                sm="4"
              >
                <div class="text-center" :class="content.textColor || 'text-white'">
                  <v-icon :color="content.iconColor || 'white'" class="mb-2">
                    {{ benefit.icon || 'mdi-check-circle' }}
                  </v-icon>
                  <p class="text-body-2">{{ benefit.text }}</p>
                </div>
              </v-col>
            </v-row>
          </div>
        </v-col>
      </v-row>
    </v-container>
  </v-sheet>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Subscribe to Our Newsletter',
      description: '',
      emailPlaceholder: 'Enter your email',
      buttonText: 'Subscribe',
      backgroundColor: 'bg-primary',
      textColor: 'text-white',
      iconColor: 'white',
      showIcon: true,
      icon: 'mdi-email-newsletter',
      privacyText: 'We respect your privacy. Unsubscribe at any time.',
      successMessage: 'Thank you for subscribing!',
      errorMessage: 'Subscription failed. Please try again.',
      benefits: []
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const email = ref('');
const isSubscribing = ref(false);
const subscribeMessage = ref('');
const subscribeSuccess = ref(false);

async function handleSubscribe() {
  if (!email.value) return;

  isSubscribing.value = true;
  subscribeMessage.value = '';

  try {
    // TODO: Replace with actual API endpoint
    // await axios.post('/api/newsletter/subscribe', { email: email.value });

    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000));

    subscribeSuccess.value = true;
    subscribeMessage.value = props.content.successMessage;
    email.value = '';
  } catch (error) {
    subscribeSuccess.value = false;
    subscribeMessage.value = props.content.errorMessage;
  } finally {
    isSubscribing.value = false;
  }
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}

.subscribe-btn {
  min-width: 140px;
}

@media (max-width: 600px) {
  .subscribe-btn {
    width: 100%;
  }
}
</style>
