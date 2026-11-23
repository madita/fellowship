<template>
  <div class="flex-grow-1">
      <data-table
          endpoint="/datatable/taxonomies"
          :title="$t('admin.taxonomies.rawTableTitle')"
          :subtitle="$t('admin.taxonomies.rawTableSubtitle')"
          icon="mdi-table"
          :back-to="{ name: 'admin-taxonomie' }"
      />
  </div>
</template>

<script>
import DataTable from '../../components/common/DataTable/DataTable.vue'

/**
 * Every column of the taxonomies table, including the ones the manager at
 * admin-taxonomie does not show (lead, content, meta description, the
 * feature flags in `properties`). The manager is the screen to reach for;
 * this is the escape hatch behind it.
 */
export default {
    components: {
        DataTable
    }
}
</script>
