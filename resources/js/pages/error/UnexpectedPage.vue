<template>
  <v-card class="text-center w-full error-page pa-3">
    <v-img src="/images/illustrations/500-illustration.svg" max-height="400" contain />
    <div class="text-h3 font-weight-bold mt-10">{{ $t('errorPages.unexpected.title') }}</div>
    <div class="text-body-1 text-medium-emphasis mt-3 mb-10">{{ $t('errorPages.unexpected.description') }}</div>
    <v-text-field :placeholder="$t('errorPages.unexpected.searchPlaceholder')" hide-details class="mb-4" />
    <v-btn to="/" block size="large" color="primary" variant="elevated">{{ $t('errorPages.unexpected.goBack') }}</v-btn>
  </v-card>
</template>

<style scoped>
.error-page {
  max-width: 500px;
}
</style>
