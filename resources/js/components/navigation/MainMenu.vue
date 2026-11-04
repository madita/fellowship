<template>
  <v-list nav density="compact">
    <div v-for="(item, index) in menu" :key="index">
        <!-- The whole section is permission-gated: items of a section the
           user may not see (e.g. Administration) must not render either.
           A section also opens for somebody holding the permission of one
           item inside it — the delegable work, such as writing the
           newsletter — and they then see only that item. -->
      <template v-if="$helpers.sectionVisible(item)">
        <!-- Separate sections (e.g. Administration) clearly from the main menu -->
        <v-divider v-if="index > 0" class="mt-3 mb-1" />
        <div
          v-if="item.key || item.text"
          class="pa-1 mt-2 text-overline"
          :class="{ 'text-primary': item.role === 'admin' }"
        >
          <v-icon v-if="item.role === 'admin'" size="x-small" class="mr-1">mdi-shield-crown-outline</v-icon>
          {{ item.key ? $t(item.key) : item.text }}
        </div>
        <nav-menu :menu="item.items" />
      </template>
    </div>
  </v-list>
</template>

<script>
import NavMenu from './NavMenu.vue'

export default {
  components: {
    NavMenu
  },
  props: {
    menu: {
      type: Array,
      default: () => []
    }
  }
}
</script>
