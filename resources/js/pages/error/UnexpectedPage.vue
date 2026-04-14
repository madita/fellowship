<template>
  <v-card class="text-center w-full error-page pa-3">
    <v-img src="/images/illustrations/500-illustration.svg" max-height="400" contain />
    <div class="display-2 mt-10">{{ $t('errorPages.unexpected.title') }}</div>
    <div class="mt-3 mb-10">{{ $t('errorPages.unexpected.description') }}</div>
    <v-text-field solo :placeholder="$t('errorPages.unexpected.searchPlaceholder')"></v-text-field>
    <v-btn to="/" block large color="primary">{{ $t('errorPages.unexpected.goBack') }}</v-btn>
  </v-card>
</template>

<style>
.error-page {
  max-width: 500px;
}
</style>
