<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/taxonomies" :title="$t('admin.taxonomies.title')" icon="mdi-file-tree" :back-to="{ name: 'admin-settings-category', params: { category: 'taxonomy' } }" />
  </div>
</template>

<script>
import DataTable from '../../components/common/DataTable/DataTable.vue'

export default {
    components: {
        DataTable
    }
}
</script>
