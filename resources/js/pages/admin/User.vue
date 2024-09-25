<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/users"></data-table>
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
