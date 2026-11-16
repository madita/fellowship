<template>
  <v-card class="text-center w-full error-page pa-3">
    <v-img src="/images/illustrations/404-illustration.svg" max-height="400" contain />
    <div class="text-h3 font-weight-bold mt-10">{{ $t('errorPages.notFound.title') }}</div>
    <div class="text-body-1 text-medium-emphasis mt-3 mb-6">{{ $t('errorPages.notFound.description') }}</div>
    <v-text-field :placeholder="$t('errorPages.notFound.searchPlaceholder')" hide-details class="mb-4" />
    <v-btn to="/" block size="large" color="primary" variant="elevated">{{ $t('errorPages.notFound.goBack') }}</v-btn>
  </v-card>
</template>

<style scoped>
.error-page {
  max-width: 500px;
}
</style>
