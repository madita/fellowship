<template>
  <div class="flex-grow-1">
      <v-table>
          <template v-slot:default>
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
                      ></v-checkbox>

                  </td>
              </tr>
              </tbody>
          </template>
      </v-table>
      <v-btn
          color="blue darken-1"
          text
          :loading="saving"
          :disabled="saving"
          @click="save"
      >
          {{ $t('common.save') }}
      </v-btn>
  </div>
</template>

<script>
// import DataTable from '../../components/common/DataTable'

export default {
    data () {
        return {
            selected: [],
            roles: {},
            permissions: {},
            loading: false,
            saving: false,
            breadcrumbs: [{
                text: '',
                disabled: false,
                href: '#'
            }, {
                text: 'List'
            }],
        }
    },
    components: {

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
