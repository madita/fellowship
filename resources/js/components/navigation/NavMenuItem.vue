<template>
  <div>
    <v-list-item
      v-if="!menuItem.items && $helpers.applyPermissions(menuItem)"
      :input-value="menuItem.value"
      :to="menuItem.link"
      :exact="menuItem.exact"
      :disabled="menuItem.disabled"
      selected-class="text-primary"
      link
    >


        <template v-slot:prepend>
            <v-icon :small="small" :class="{ 'grey--text': menuItem.disabled }">
                {{ menuItem.icon || 'mdi-circle-medium' }}
            </v-icon>
        </template>

        <v-list-item-title>
          {{ menuItem.key ? $t(menuItem.key) : menuItem.text }}
        </v-list-item-title>

    </v-list-item>
    <v-list-group
      v-else-if="menuItem.items && $helpers.applyPermissions(menuItem)"
      :value="menuItem.regex ? menuItem.regex.test($route.path) : false"
      :disabled="menuItem.disabled"
      :sub-group="subgroup"
      :to="menuItem.link"
      link
    >
        <template v-slot:prepend>
            <v-icon v-if="!subgroup" :small="small">{{ menuItem.icon || 'mdi-circle-medium' }}</v-icon>
        </template>
      <template v-slot:activator>
          <v-list-item-title>
            {{ menuItem.key ? $t(menuItem.key) : menuItem.text }}
          </v-list-item-title>
      </template>

      <slot></slot>

    </v-list-group>
  </div>
</template>

<script>
/*
|---------------------------------------------------------------------
| Navigation Menu Item Component
|---------------------------------------------------------------------
|
| Navigation items for the NavMenu component
|
*/
export default {
  props: {
    menuItem: {
      type: Object,
      default: () => {}
    },
    subgroup: {
      type: Boolean,
      default: false
    },
    small: {
      type: Boolean,
      default: false
    }
  }
}
</script>
