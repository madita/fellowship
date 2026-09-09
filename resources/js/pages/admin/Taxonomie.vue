<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/taxonomies" :title="$t('admin.taxonomies.title')" icon="mdi-file-tree" />
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
