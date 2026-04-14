<template>
  <v-sheet class="py-16">
    <v-container>
      <v-row justify="center">
        <v-col cols="12" md="8" lg="6">
          <div class="text-center mb-8">
            <h2 class="text-h3 text-md-h2 font-weight-bold mb-4">
              {{ content.title }}
            </h2>
            <p v-if="content.subtitle" class="text-h6 text-grey">
              {{ content.subtitle }}
            </p>
          </div>

          <v-card elevation="4" class="pa-6">
            <v-form ref="contactForm" @submit.prevent="handleSubmit">
              <v-text-field
                v-model="formData.name"
                :label="content.nameLabel || $t('contactForm.name')"
                :required="content.nameRequired"
                variant="outlined"
                class="mb-4"
              ></v-text-field>

              <v-text-field
                v-model="formData.email"
                :label="content.emailLabel || $t('contactForm.email')"
                :required="content.emailRequired"
                type="email"
                variant="outlined"
                class="mb-4"
              ></v-text-field>

              <v-text-field
                v-if="content.showPhoneField"
                v-model="formData.phone"
                :label="content.phoneLabel || $t('contactForm.phone')"
                :required="content.phoneRequired"
                variant="outlined"
                class="mb-4"
              ></v-text-field>

              <v-text-field
                v-if="content.showSubjectField"
                v-model="formData.subject"
                :label="content.subjectLabel || $t('contactForm.subject')"
                :required="content.subjectRequired"
                variant="outlined"
                class="mb-4"
              ></v-text-field>

              <v-textarea
                v-model="formData.message"
                :label="content.messageLabel || $t('contactForm.message')"
                :required="content.messageRequired"
                rows="5"
                variant="outlined"
                class="mb-4"
              ></v-textarea>

              <div class="text-center">
                <v-btn
                  type="submit"
                  color="primary"
                  size="large"
                  :loading="isSubmitting"
                >
                  {{ content.submitButtonText || $t('contactForm.sendMessage') }}
                </v-btn>
              </div>
            </v-form>

            <v-alert
              v-if="submitMessage"
              :type="submitSuccess ? 'success' : 'error'"
              variant="tonal"
              class="mt-4"
            >
              {{ submitMessage }}
            </v-alert>
          </v-card>
        </v-col>
      </v-row>
    </v-container>
  </v-sheet>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Contact Us',
      subtitle: '',
      nameLabel: 'Name',
      nameRequired: true,
      emailLabel: 'Email',
      emailRequired: true,
      showPhoneField: false,
      phoneLabel: 'Phone',
      phoneRequired: false,
      showSubjectField: true,
      subjectLabel: 'Subject',
      subjectRequired: false,
      messageLabel: 'Message',
      messageRequired: true,
      submitButtonText: 'Send Message',
      successMessage: 'Thank you for your message! We will get back to you soon.',
      errorMessage: 'There was an error sending your message. Please try again.'
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const formData = ref({
  name: '',
  email: '',
  phone: '',
  subject: '',
  message: ''
});

const isSubmitting = ref(false);
const submitMessage = ref('');
const submitSuccess = ref(false);

async function handleSubmit() {
  isSubmitting.value = true;
  submitMessage.value = '';

  try {
    // TODO: Replace with actual API endpoint
    // await axios.post('/api/contact', formData.value);

    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1000));

    submitSuccess.value = true;
    submitMessage.value = props.content.successMessage || t('contactForm.successMessage');

    // Reset form
    formData.value = {
      name: '',
      email: '',
      phone: '',
      subject: '',
      message: ''
    };
  } catch (error) {
    submitSuccess.value = false;
    submitMessage.value = props.content.errorMessage || t('contactForm.errorMessage');
  } finally {
    isSubmitting.value = false;
  }
}
</script>
