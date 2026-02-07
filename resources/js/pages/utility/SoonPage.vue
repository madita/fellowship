<template>
  <div class="pa-2">
    <h1 class="display-2 font-weight-bold">{{ $t('utility.comingSoonTitle') }}</h1>
    <h2 class="title mt-4">{{ $t('utility.comingSoonDescription') }}</h2>
    <div class="mt-8">
      <v-text-field solo :placeholder="$t('utility.emailPlaceholder')"></v-text-field>
      <v-btn color="bg-primary" large block>{{ $t('utility.notifyMe') }}</v-btn>
    </div>
  </div>
</template>
