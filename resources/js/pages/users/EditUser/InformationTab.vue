<template>
    <v-card class="my-2">
        <v-card-title class="text-subtitle-1 font-weight-medium">
            {{ $t('users.edit.userInformation') }}
        </v-card-title>

        <v-card-text>
            <loading-state v-if="loading" compact />

            <v-form v-else ref="formRef" @submit.prevent="save">
                <!-- What others may see. Each of these can be switched off,
                     and the eye says which way it stands. -->
                <div class="text-subtitle-2 font-weight-medium mb-1">
                    {{ $t('users.edit.shownToOthers') }}
                </div>
                <p class="text-caption text-medium-emphasis mb-3">
                    {{ $t('users.edit.shownToOthersHint') }}
                </p>

                <v-textarea
                    v-model="form.bio"
                    :label="$t('users.edit.bio')"
                    rows="3"
                    counter="1000"
                    variant="outlined"
                    density="compact"
                >
                    <template #append>
                        <visibility-toggle v-model="form.visibility.bio" />
                    </template>
                </v-textarea>

                <v-row dense>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.pronouns"
                            :label="$t('users.edit.pronouns')"
                            variant="outlined"
                            density="compact"
                        >
                            <template #append>
                                <visibility-toggle v-model="form.visibility.pronouns" />
                            </template>
                        </v-text-field>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.website"
                            :label="$t('users.edit.website')"
                            placeholder="https://"
                            variant="outlined"
                            density="compact"
                            :rules="websiteRules"
                        >
                            <template #append>
                                <visibility-toggle v-model="form.visibility.website" />
                            </template>
                        </v-text-field>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.city"
                            :label="$t('users.edit.city')"
                            variant="outlined"
                            density="compact"
                        >
                            <template #append>
                                <visibility-toggle v-model="form.visibility.city" />
                            </template>
                        </v-text-field>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.country"
                            :label="$t('users.edit.country')"
                            variant="outlined"
                            density="compact"
                        >
                            <template #append>
                                <visibility-toggle v-model="form.visibility.country" />
                            </template>
                        </v-text-field>
                    </v-col>

                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.birthday"
                            :label="$t('users.edit.birthdayDate')"
                            type="date"
                            variant="outlined"
                            density="compact"
                        >
                            <template #append>
                                <visibility-toggle v-model="form.visibility.birthday" />
                            </template>
                        </v-text-field>
                    </v-col>
                </v-row>

                <v-divider class="my-4" />

                <!-- Kept for the site's own records -->
                <div class="text-subtitle-2 font-weight-medium mb-1">
                    <v-icon icon="mdi-lock-outline" size="small" class="mr-1" />
                    {{ $t('users.edit.privateDetails') }}
                </div>
                <p class="text-caption text-medium-emphasis mb-3">
                    {{ $t('users.edit.privateDetailsHint') }}
                </p>

                <v-row dense>
                    <v-col cols="12" md="6">
                        <v-text-field
                            v-model="form.address_line1"
                            :label="$t('users.edit.addressLine1')"
                            variant="outlined"
                            density="compact"
                        />
                        <v-text-field
                            v-model="form.address_line2"
                            :label="$t('users.edit.addressLine2')"
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>
                    <v-col cols="12" md="6">
                        <v-row dense>
                            <v-col cols="5">
                                <v-text-field
                                    v-model="form.postcode"
                                    :label="$t('users.edit.zipCode')"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                            <v-col cols="7">
                                <v-text-field
                                    v-model="form.state"
                                    :label="$t('users.edit.state')"
                                    variant="outlined"
                                    density="compact"
                                />
                            </v-col>
                        </v-row>
                        <v-text-field
                            v-model="form.phone"
                            :label="$t('users.edit.phone')"
                            variant="outlined"
                            density="compact"
                        />
                    </v-col>
                </v-row>

                <div class="d-flex">
                    <v-btn variant="text" :disabled="saving" @click="load">
                        {{ $t('users.edit.reset') }}
                    </v-btn>
                    <v-spacer />
                    <v-btn color="primary" :loading="saving" @click="save">
                        {{ $t('common.save') }}
                    </v-btn>
                </div>
            </v-form>
        </v-card-text>
    </v-card>
</template>

<script>
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';
import VisibilityToggle from '@/components/common/VisibilityToggle.vue';

function blankForm() {
    return {
        bio: '',
        pronouns: '',
        city: '',
        country: '',
        website: '',
        birthday: '',
        socials: {},
        phone: '',
        address_line1: '',
        address_line2: '',
        postcode: '',
        state: '',
        visibility: {},
    };
}

export default {
    name: 'InformationTab',
    components: { LoadingState, VisibilityToggle },
    data() {
        return {
            loading: true,
            saving: false,
            form: blankForm(),
        };
    },
    computed: {
        websiteRules() {
            return [
                v => !v || /^https?:\/\/\S+$/.test(v) || this.$t('users.edit.websiteInvalid'),
            ];
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/account/information');

                // Nulls come back for anything never filled in; the inputs
                // want strings
                this.form = Object.fromEntries(
                    Object.entries({ ...blankForm(), ...data.data })
                        .map(([key, value]) => [key, value ?? (key === 'visibility' || key === 'socials' ? {} : '')])
                );
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('users.edit.informationLoadFailed'));
            } finally {
                this.loading = false;
            }
        },
        async save() {
            if (this.saving) return;

            const { valid } = await this.$refs.formRef.validate();
            if (!valid) return;

            this.saving = true;
            try {
                const { data } = await axios.patch('/api/account/information', this.form);
                this.form = { ...this.form, ...data.data };
                await this.$dialog.success(this.$t('users.edit.informationSaved'));
            } catch (error) {
                await this.$dialog.requestError(error, this.$t('users.edit.informationSaveFailed'));
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
