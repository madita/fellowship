<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/posts" :title="$t('admin.posts.title')" icon="mdi-post-outline" />
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
