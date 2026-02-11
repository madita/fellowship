<template>
  <v-card class="my-2">
    <v-card-title>{{ $t('users.edit.userInformation') }}</v-card-title>
    <v-card-text>
      <v-form>
        <v-row>
          <v-col cols="12" md="6">
            <v-text-field value="First and two on el street" :label="$t('users.edit.addressLine1')"></v-text-field>
            <v-text-field value="" :label="$t('users.edit.addressLine2')"></v-text-field>
            <v-text-field value="1231" :label="$t('users.edit.zipCode')"></v-text-field>
            <v-text-field value="Los Angeles" :label="$t('users.edit.city')"></v-text-field>
            <v-text-field value="California" :label="$t('users.edit.state')"></v-text-field>
            <v-text-field value="United States" :label="$t('users.edit.country')"></v-text-field>
          </v-col>

          <v-col cols="12" md="6">
            <v-text-field value="+8484548112" :label="$t('users.edit.phone')"></v-text-field>
            <v-menu
              ref="menu"
              v-model="menu"
              :close-on-content-click="false"
              transition="scale-transition"
              offset-y
              min-width="290px"
            >
              <template v-slot:activator="{ probs }">
                <v-text-field
                  v-model="date"
                  :label="$t('users.edit.birthdayDate')"
                  readonly
                  v-bind="probs"
                ></v-text-field>
              </template>
              <v-date-picker
                ref="picker"
                v-model="date"
                :max="new Date().toISOString().substr(0, 10)"
                min="1950-01-01"
                @change="save"
              ></v-date-picker>
            </v-menu>
            <v-text-field value="https://" :label="$t('users.edit.website')"></v-text-field>
            <v-radio-group v-model="gender" :label="$t('users.edit.gender')">
              <v-radio :label="$t('users.edit.male')" value="male"></v-radio>
              <v-radio :label="$t('users.edit.female')" value="female"></v-radio>
              <v-radio :label="$t('users.edit.other')" value="other"></v-radio>
            </v-radio-group>
          </v-col>
        </v-row>

        <div class="d-flex">
          <v-btn>{{ $t('users.edit.reset') }}</v-btn>
          <v-spacer></v-spacer>
          <v-btn color="bg-primary">{{ $t('common.save') }}</v-btn>
        </div>
      </v-form>
    </v-card-text>
  </v-card>
</template>

<script>
export default {
  data: () => ({
    date: '1990-10-09',
    menu: false,
    gender: 'male'
  }),
  watch: {
    menu (val) {
      val && setTimeout(() => (this.$refs.picker.activePicker = 'YEAR'))
    }
  },
  methods: {
    save (date) {
      this.$refs.menu.save(date)
    }
  }
}
</script>
