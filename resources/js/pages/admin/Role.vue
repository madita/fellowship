<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/roles" :title="$t('admin.roles.title')" icon="mdi-shield-account" :back-to="{ name: 'admin-settings-category', params: { category: 'access' } }" />
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
