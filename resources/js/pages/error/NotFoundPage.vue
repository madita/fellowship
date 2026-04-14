<template>
  <v-card class="text-center w-full error-page pa-3">
    <v-img src="/images/illustrations/404-illustration.svg" max-height="400" contain />
    <div class="display-2 mt-10">{{ $t('errorPages.notFound.title') }}</div>
    <div class="mt-3 mb-6">{{ $t('errorPages.notFound.description') }}</div>
    <v-text-field solo :placeholder="$t('errorPages.notFound.searchPlaceholder')" large></v-text-field>
    <v-btn to="/" block large color="primary">{{ $t('errorPages.notFound.goBack') }}</v-btn>
  </v-card>
</template>

<style>
.error-page {
  max-width: 500px;
}
</style>
