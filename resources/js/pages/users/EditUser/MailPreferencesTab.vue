<template>
    <v-card flat>
        <v-card-text>
            <h3 class="text-h6 mb-2">{{ $t('mailPreferences.title') }}</h3>
            <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('mailPreferences.intro') }}</p>

            <loading-state v-if="loading && categories.length === 0" />

            <template v-else>
                <!-- A blanket unsubscribe beats every switch below, so it
                     is said before them rather than left to be inferred
                     from switches that look on but are not. -->
                <v-alert
                    v-if="unsubscribedAll"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                >
                    <div class="d-flex flex-wrap align-center ga-2">
                        <span>{{ $t('mailPreferences.allOffNotice') }}</span>
                        <v-spacer />
                        <v-btn
                            size="small"
                            variant="tonal"
                            prepend-icon="mdi-email-check-outline"
                            :loading="restoring"
                            :disabled="busy"
                            @click="resubscribe"
                        >
                            {{ $t('mailPreferences.turnBackOn') }}
                        </v-btn>
                    </div>
                </v-alert>

                <v-list class="pa-0">
                    <template v-for="(category, index) in categories" :key="category.id">
                        <v-divider v-if="index > 0" />
                        <v-list-item class="px-0 py-2">
                            <template #prepend>
                                <v-icon :color="category.color" class="mr-3">{{ category.icon }}</v-icon>
                            </template>

                            <v-list-item-title class="font-weight-medium">
                                {{ category.name }}
                            </v-list-item-title>
                            <v-list-item-subtitle class="text-caption" style="white-space: normal;">
                                {{ category.description }}
                            </v-list-item-subtitle>

                            <template #append>
                                <!-- The mail that gets somebody back into
                                     their account. Shown as a fixed row
                                     rather than hidden, so nobody wonders
                                     where it went. -->
                                <v-chip
                                    v-if="category.is_mandatory"
                                    size="small"
                                    variant="tonal"
                                    color="blue-grey"
                                    :title="$t('mailPreferences.alwaysOnHint')"
                                >
                                    {{ $t('mailPreferences.alwaysOn') }}
                                </v-chip>
                                <v-switch
                                    v-else
                                    v-model="answers[category.key]"
                                    color="primary"
                                    density="compact"
                                    hide-details
                                    inset
                                    :disabled="busy"
                                />
                            </template>
                        </v-list-item>
                    </template>
                </v-list>

                <div class="d-flex flex-wrap align-center ga-2 mt-5">
                    <v-btn
                        color="primary"
                        variant="elevated"
                        prepend-icon="mdi-content-save-outline"
                        :loading="saving"
                        :disabled="busy || !changed"
                        @click="save"
                    >
                        {{ $t('common.save') }}
                    </v-btn>

                    <v-btn
                        v-if="changed"
                        variant="text"
                        :disabled="busy"
                        @click="reset"
                    >
                        {{ $t('common.cancel') }}
                    </v-btn>

                    <v-spacer />

                    <v-btn
                        v-if="!unsubscribedAll"
                        variant="text"
                        color="error"
                        prepend-icon="mdi-email-off-outline"
                        :loading="turningOff"
                        :disabled="busy"
                        @click="unsubscribeAll"
                    >
                        {{ $t('mailPreferences.turnEverythingOff') }}
                    </v-btn>
                </div>
            </template>
        </v-card-text>
    </v-card>
</template>

<script>
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';

/**
 * Which kinds of mail may reach this member.
 *
 * The same answers the unsubscribe link at the bottom of a mail writes —
 * one set of preferences reachable two ways, rather than a signed-in
 * version and a link version that can drift apart.
 *
 * Turning everything off is deliberately the quiet option at the far end
 * rather than a switch among the others: it beats all of them, and it is
 * not the thing somebody who came here to leave the newsletter wants.
 */
export default {
    name: 'MailPreferencesTab',
    components: { LoadingState },
    data() {
        return {
            categories: [],
            // category key → yes or no, as the switches stand now
            answers: {},
            // The same, as it was when loaded — what "changed" compares to
            saved: {},
            unsubscribedAll: false,
            loading: false,
            saving: false,
            turningOff: false,
            restoring: false,
        };
    },
    computed: {
        busy() {
            return this.saving || this.turningOff || this.restoring || this.loading;
        },
        changed() {
            return Object.keys(this.answers).some((key) => this.answers[key] !== this.saved[key]);
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/account/mail-preferences');
                this.apply(data);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.categories = data.categories || [];
            this.unsubscribedAll = !!data.unsubscribed_all;

            const answers = {};

            for (const category of this.categories) {
                if (category.is_mandatory) continue;
                // While everything is off, the switches show what would
                // come back rather than what is arriving — which is why
                // the banner above says so
                answers[category.key] = !!category.opted_in;
            }

            this.answers = answers;
            this.saved = { ...answers };
        },

        reset() {
            this.answers = { ...this.saved };
        },

        async save() {
            this.saving = true;
            try {
                const { data } = await axios.patch('/api/account/mail-preferences', { preferences: this.answers });
                this.apply(data);
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.saving = false;
            }
        },

        async unsubscribeAll() {
            const ok = await this.$dialog.confirm({
                title: this.$t('mailPreferences.confirmAllOffTitle'),
                content: this.$t('mailPreferences.confirmAllOff'),
                confirmationText: this.$t('mailPreferences.turnEverythingOff'),
                color: 'error',
                destructive: true,
            });

            if (!ok) return;

            this.turningOff = true;
            try {
                const { data } = await axios.post('/api/account/mail-preferences/unsubscribe-all');
                this.apply(data);
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.turningOff = false;
            }
        },

        async resubscribe() {
            this.restoring = true;
            try {
                const { data } = await axios.post('/api/account/mail-preferences/resubscribe');
                this.apply(data);
                this.$dialog.success(data.message);
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.restoring = false;
            }
        },
    },
};
</script>
