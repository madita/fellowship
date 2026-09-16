<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/pages" :title="$t('admin.pages.title')" icon="mdi-file-document-outline" />
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
