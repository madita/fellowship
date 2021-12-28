<template>
  <div class="flex-grow-1">
      <v-simple-table>
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
                      ></v-checkbox>

                  </td>
              </tr>
              </tbody>
          </template>
      </v-simple-table>
      <v-btn
          color="blue darken-1"
          text
          @click="save"
      >
          Save
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
        save () {
            axios.post('/api/datatable/permissions/roles', this.selected).then(() => {
                //this.response.records.data.push(this.editedItem)
                //console.log()


            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        }
    },
    mounted () {
        this.getRoles()
        this.getPermissions()
    }
}
</script>
