<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/events" :title="$t('admin.events.title')" icon="mdi-calendar" />
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
