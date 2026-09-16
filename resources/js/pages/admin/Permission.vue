<template>
  <div class="flex-grow-1">
      <page-header :title="$t('admin.permissions.title')" icon="mdi-shield-key-outline" :back-to="{ name: 'admin-settings-category', params: { category: 'access' } }">
          <template #actions>
              <v-btn
                  color="primary"
                  variant="elevated"
                  prepend-icon="mdi-content-save-outline"
                  :loading="saving"
                  :disabled="saving || loading"
                  @click="save"
              >
                  {{ $t('common.save') }}
              </v-btn>
          </template>
      </page-header>

      <v-container fluid>
          <v-card>
              <loading-state v-if="loading && !permissions.length" />

              <empty-state
                  v-else-if="!loading && !permissions.length"
                  icon="mdi-shield-off-outline"
                  :title="$t('admin.permissions.noPermissions')"
                  compact
              />

              <v-table v-else>
                  <thead>
                  <tr>
                      <th></th>
                      <th class="text-left"
                          v-for="role in roles"
                          :key="role.name">
                          {{ role.display_name }}
                      </th>
                  </tr>
                  </thead>
                  <tbody>
                  <tr
                      v-for="(item, index) in permissions"
                      :key="item.name"
                  >
                      <td>{{ item.display_name }}</td>
                      <td class="text-left"
                          v-for="role in roles"
                          :key="role.name+index">
                          <v-checkbox
                              v-model="selected"
                              :value="{role: role.id, permission: item.id}"
                              :disabled="saving"
                              hide-details
                              density="compact"
                          ></v-checkbox>
                      </td>
                  </tr>
                  </tbody>
              </v-table>

              <v-divider />

              <v-card-actions>
                  <v-spacer />
                  <v-btn
                      color="primary"
                      variant="flat"
                      :loading="saving"
                      :disabled="saving || loading"
                      @click="save"
                  >
                      {{ $t('common.save') }}
                  </v-btn>
              </v-card-actions>
          </v-card>
      </v-container>
  </div>
</template>

<script>
import PageHeader from '../../components/common/PageHeader.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import LoadingState from '../../components/common/LoadingState.vue'

export default {
    data () {
        return {
            selected: [],
            roles: [],
            permissions: [],
            loading: true,
            saving: false,
        }
    },
    components: {
        PageHeader,
        EmptyState,
        LoadingState
    },
    methods: {
        getRoles () {
            this.loading = true
            return axios.get('/api/datatable/permissions/roles').then((response) => {
                this.roles = response.data.data

                this.roles.forEach(role => {
                        role.permissions.forEach(
                            item => this.selected.push({role: role.id, permission: item.id})
                        )
                    })

                // this.response.updatable.forEach(item =>
                //     this.defaultItem[item] = this.response.column_fields[item]=='checkbox'?0:''
                // )
                // this.editedItem = this.defaultItem
                this.loading = false
            });
        },
        getPermissions () {
            this.loading = true
            return axios.get('/api/datatable/permissions/permissions').then((response) => {
                this.permissions = response.data.data

                // this.response.updatable.forEach(item =>
                //     this.defaultItem[item] = this.response.column_fields[item]=='checkbox'?0:''
                // )
                this.editedItem = this.defaultItem
                this.loading = false
            });
        },
        async save () {
            if (this.saving) return
            this.saving = true
            try {
                await axios.post('/api/datatable/permissions/roles', this.selected)
                this.saving = false
                this.$dialog.success(this.$t('success.saved'))
            } catch (error) {
                this.saving = false
                this.$dialog.requestError(error)
            }
        }
    },
    mounted () {
        this.getRoles()
        this.getPermissions()
    }
}
</script>
