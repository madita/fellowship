<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/terms" :title="$t('admin.terms.title')" icon="mdi-tag-multiple" />
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
