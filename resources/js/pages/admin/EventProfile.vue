<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/event-profiles" :title="$t('admin.eventProfiles.title')" icon="mdi-card-account-details-outline" />
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
