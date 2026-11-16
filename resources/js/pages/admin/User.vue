<template>
  <div class="flex-grow-1">
      <data-table endpoint="/datatable/users" :title="$t('admin.users.title')" icon="mdi-account-group">
          <!-- Beside the edit pencil: what this member is allowed. The
               roles screen says what a role may do; this says who has
               it, which is the question an admin actually arrives with. -->
          <template #row-actions="{ item, busy }">
              <v-btn
                  icon="mdi-shield-account-outline"
                  variant="text"
                  size="small"
                  :disabled="busy"
                  :title="$t('access.manage')"
                  :aria-label="$t('access.manage')"
                  @click="openAccess(item)"
              />
          </template>
      </data-table>

      <user-access-dialog v-model="accessOpen" :user="editing" />
  </div>
</template>

<script>
import DataTable from '../../components/common/DataTable/DataTable.vue'
import UserAccessDialog from '../../components/admin/users/UserAccessDialog.vue'

export default {
    components: {
        DataTable,
        UserAccessDialog
    },
    data() {
        return {
            accessOpen: false,
            editing: null
        }
    },
    methods: {
        openAccess(user) {
            this.editing = user
            this.accessOpen = true
        }
    }
}
</script>
