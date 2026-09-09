<template>
  <div class="pa-2">
    <h1 class="text-h3 font-weight-bold">{{ $t('utility.comingSoonTitle') }}</h1>
    <h2 class="text-h6 text-medium-emphasis mt-4">{{ $t('utility.comingSoonDescription') }}</h2>
    <div class="mt-8">
      <v-text-field :placeholder="$t('utility.emailPlaceholder')" />
      <v-btn color="primary" variant="elevated" size="large" block>{{ $t('utility.notifyMe') }}</v-btn>
    </div>
  </div>
</template>
