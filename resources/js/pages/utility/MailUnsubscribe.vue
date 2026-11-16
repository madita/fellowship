<template>
    <v-container class="py-10" style="max-width: 680px;">
        <loading-state v-if="loading" />

        <!-- An old link, or one that was never ours. Not an error page:
             there is something useful to say, which is where to go. -->
        <v-card v-else-if="!member" rounded="lg" variant="outlined">
            <v-card-text class="text-center py-8">
                <v-icon size="48" color="warning" class="mb-3">mdi-link-variant-off</v-icon>
                <h2 class="text-h6 mb-2">{{ $t('unsubscribe.badLinkTitle') }}</h2>
                <p class="text-body-2 text-medium-emphasis">{{ errorMessage || $t('unsubscribe.badLink') }}</p>
            </v-card-text>
            <v-card-actions class="justify-center pb-6">
                <v-btn color="primary" variant="elevated" to="/users/edit">
                    {{ $t('unsubscribe.goToAccount') }}
                </v-btn>
            </v-card-actions>
        </v-card>

        <v-card v-else rounded="lg">
            <v-card-text class="pa-6">
                <div class="d-flex align-center mb-4">
                    <v-icon size="32" color="primary" class="mr-3">mdi-email-off-outline</v-icon>
                    <div>
                        <h2 class="text-h6">{{ $t('unsubscribe.title') }}</h2>
                        <div class="text-caption text-medium-emphasis">
                            {{ $t('unsubscribe.forAddress', { email: member.email }) }}
                        </div>
                    </div>
                </div>

                <!-- Already done, by this link or another. Said rather
                     than shown as switches that look on but are not. -->
                <v-alert v-if="done" type="success" variant="tonal" density="compact" class="mb-4">
                    {{ done }}
                </v-alert>

                <template v-if="!done">
                    <!-- The quick answer first: the link came from one
                         kind of mail, and stopping that one is usually
                         what somebody actually wants. -->
                    <v-card v-if="member.category" variant="tonal" color="primary" class="mb-4">
                        <v-card-text>
                            <div class="d-flex align-center mb-2">
                                <v-icon class="mr-2">{{ member.category.icon }}</v-icon>
                                <span class="font-weight-medium">{{ member.category.name }}</span>
                            </div>
                            <div class="text-body-2">
                                {{ $t('unsubscribe.categoryIntro', { category: member.category.name }) }}
                            </div>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <v-btn
                                variant="elevated"
                                color="primary"
                                prepend-icon="mdi-email-off-outline"
                                :loading="working === 'category'"
                                :disabled="!!working"
                                @click="unsubscribe(member.category.key)"
                            >
                                {{ $t('unsubscribe.stopThese') }}
                            </v-btn>
                        </v-card-actions>
                    </v-card>

                    <v-divider class="mb-4" />

                    <div class="text-subtitle-2 font-weight-medium mb-1">{{ $t('unsubscribe.pickTitle') }}</div>
                    <p class="text-body-2 text-medium-emphasis mb-3">{{ $t('unsubscribe.pickIntro') }}</p>

                    <v-list class="pa-0">
                        <template v-for="(category, index) in member.categories" :key="category.id">
                            <v-divider v-if="index > 0" />
                            <v-list-item class="px-0 py-2">
                                <template #prepend>
                                    <v-icon :color="category.color" class="mr-3">{{ category.icon }}</v-icon>
                                </template>
                                <v-list-item-title class="font-weight-medium">{{ category.name }}</v-list-item-title>
                                <v-list-item-subtitle class="text-caption" style="white-space: normal;">
                                    {{ category.description }}
                                </v-list-item-subtitle>
                                <template #append>
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
                                        :disabled="!!working"
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
                            :loading="working === 'save'"
                            :disabled="!!working"
                            @click="save"
                        >
                            {{ $t('unsubscribe.saveChoices') }}
                        </v-btn>
                        <v-spacer />
                        <v-btn
                            variant="text"
                            color="error"
                            prepend-icon="mdi-email-off-outline"
                            :loading="working === 'all'"
                            :disabled="!!working"
                            @click="unsubscribe(null)"
                        >
                            {{ $t('unsubscribe.stopEverything') }}
                        </v-btn>
                    </div>
                </template>

                <!-- Undo, for the link followed by accident -->
                <div v-else class="d-flex flex-wrap ga-2">
                    <v-btn
                        variant="tonal"
                        prepend-icon="mdi-undo"
                        :loading="working === 'undo'"
                        :disabled="!!working"
                        @click="resubscribe"
                    >
                        {{ $t('unsubscribe.undo') }}
                    </v-btn>
                    <v-btn variant="text" to="/users/edit">{{ $t('unsubscribe.goToAccount') }}</v-btn>
                </div>
            </v-card-text>
        </v-card>
    </v-container>
</template>

<script>
import axios from 'axios';
import LoadingState from '@/components/common/LoadingState.vue';

/**
 * The page the link at the bottom of a mail leads to.
 *
 * Open to anybody holding the link — no sign-in, because somebody who
 * wants the mail to stop should not have to remember a password first.
 *
 * It offers the narrow answer before the broad one: the link says which
 * kind of mail it came from, and stopping that one kind is usually what
 * was actually meant. Turning everything off is still there, at the far
 * end, in words rather than as the default.
 *
 * Feedback is inline rather than in a dialog — somebody arriving from a
 * mail has no page behind this one, so the result belongs on it.
 */
export default {
    name: 'MailUnsubscribe',
    components: { LoadingState },
    data() {
        return {
            member: null,
            answers: {},
            loading: true,
            errorMessage: '',
            // 'category' | 'all' | 'save' | 'undo' while one is running
            working: null,
            // What happened, once something has
            done: '',
        };
    },
    computed: {
        token() {
            return this.$route.params.token;
        },
        category() {
            return this.$route.query.category || null;
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.loading = true;
            try {
                const { data } = await axios.get(`/api/mail/unsubscribe/${this.token}`, {
                    params: this.category ? { category: this.category } : {},
                });

                this.member = data;
                this.answers = Object.fromEntries(
                    (data.categories || [])
                        .filter((row) => !row.is_mandatory)
                        .map((row) => [row.key, !!row.opted_in])
                );

                if (data.unsubscribed_all) {
                    this.done = this.$t('unsubscribe.alreadyAllOff');
                }
            } catch (error) {
                this.member = null;
                this.errorMessage = error.response?.data?.message || '';
            } finally {
                this.loading = false;
            }
        },

        async unsubscribe(categoryKey) {
            this.working = categoryKey ? 'category' : 'all';
            try {
                const { data } = await axios.post(
                    `/api/mail/unsubscribe/${this.token}`,
                    categoryKey ? { category: categoryKey } : {}
                );

                this.done = data.message;
            } catch (error) {
                this.errorMessage = error.response?.data?.message || '';
                this.$dialog.requestError(error);
            } finally {
                this.working = null;
            }
        },

        async save() {
            this.working = 'save';
            try {
                const { data } = await axios.patch(`/api/mail/unsubscribe/${this.token}`, {
                    preferences: this.answers,
                });

                this.done = data.message;
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.working = null;
            }
        },

        async resubscribe() {
            this.working = 'undo';
            try {
                await axios.post(`/api/mail/unsubscribe/${this.token}/resubscribe`);
                this.done = '';
                await this.load();
            } catch (error) {
                this.$dialog.requestError(error);
            } finally {
                this.working = null;
            }
        },
    },
};
</script>
