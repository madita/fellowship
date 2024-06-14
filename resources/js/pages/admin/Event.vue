<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/events"></data-table>
  </div>
</template>

<script>
import DataTable from '../../components/common/DataTable.vue'

export default {
    components: {
        DataTable
    }
}
</script>
