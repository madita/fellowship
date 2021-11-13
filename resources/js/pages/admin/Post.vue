<template>
  <div class="flex-grow-1">
      <data-table endpoint="/api/datatable/posts"></data-table>
  </div>
</template>

<script>
import DataTable from '../../components/common/DataTable'

export default {
    components: {
        DataTable
    }
}
</script>
