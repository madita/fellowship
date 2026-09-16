<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/users" :title="$t('admin.users.title')" icon="mdi-account-group" />
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
