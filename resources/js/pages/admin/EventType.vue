<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/event-types" :title="$t('admin.eventTypes.title')" icon="mdi-shape-outline" />
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
