<template>
  <div class="flex-grow-1">
      <data-table
          endpoint="/datatable/terms"
          :title="$t('admin.terms.title')"
          :subtitle="$t('admin.terms.subtitle')"
          icon="mdi-tag-multiple"
          :back-to="{ name: 'admin-settings-category', params: { category: 'community' } }"
      />
  </div>
</template>

<script>
import DataTable from '../../components/common/DataTable/DataTable.vue'

/**
 * The words the site names things with. A term is only a word — what it is
 * used *as* is a taxonomy — so the "Used as" column writes out each use
 * with the branch it sits in ("wiki: Lore › Characters"), and the drawer
 * lists every use of the word next to the vocabulary and parent fields
 * that file it into one. Removing a use stays with the taxonomy manager,
 * where what is filed under it can be seen.
 */
export default {
    components: {
        DataTable
    }
}
</script>
