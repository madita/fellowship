<template>
    <div class="flex-grow-1">
        <page-header
            :title="$t('admin.forums.title')"
            :subtitle="$t('admin.forums.subtitle')"
            icon="mdi-forum"
            :back-to="{ name: 'admin-settings-category', params: { category: 'forum' } }"
            fluid
        >
            <template #actions>
                <v-btn
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-plus"
                    @click="openCreateDialog()"
                >
                    {{ $t('admin.forums.createForum') }}
                </v-btn>
            </template>
        </page-header>

        <v-container fluid>
            <!-- Loading -->
            <loading-state v-if="loading" />

            <!-- Forum Tree -->
            <div v-else-if="forums.length > 0">
                <v-card
                    v-for="forum in forums"
                    :key="forum.id"
                    class="mb-3"
                    variant="outlined"
                >
                    <v-card-text>
                        <v-row align="center">
                            <v-col cols="12" md="5">
                                <div class="d-flex align-center">
                                    <v-icon class="mr-3" color="primary">mdi-forum</v-icon>
                                    <div>
                                        <div class="d-flex align-center ga-2">
                                            <span class="font-weight-bold text-body-1">{{ forum.name }}</span>
                                            <v-chip v-if="forum.is_locked" size="x-small" variant="tonal" color="warning">
                                                {{ $t('forum.locked') }}
                                            </v-chip>
                                            <v-chip v-if="forum.is_private" size="x-small" variant="tonal" color="info">
                                                {{ $t('forum.private') }}
                                            </v-chip>
                                        </div>
                                        <div v-if="forum.description" class="text-caption text-medium-emphasis">
                                            {{ forum.description }}
                                        </div>
                                    </div>
                                </div>
                            </v-col>
                            <v-col cols="4" md="2" class="text-center">
                                <div class="text-body-2 font-weight-medium">{{ forum.threads_count || 0 }}</div>
                                <div class="text-caption text-medium-emphasis">{{ $t('forum.threads') }}</div>
                            </v-col>
                            <v-col cols="4" md="2" class="text-center">
                                <div class="text-body-2 font-weight-medium">{{ forum.posts_count || 0 }}</div>
                                <div class="text-caption text-medium-emphasis">{{ $t('forum.posts') }}</div>
                            </v-col>
                            <v-col cols="4" md="1" class="text-center">
                                <span class="text-body-2 text-medium-emphasis">#{{ forum.position }}</span>
                            </v-col>
                            <v-col cols="12" md="2" class="d-flex justify-end ga-1">
                                <v-btn icon="mdi-plus" size="small" variant="text" :disabled="deletingId !== null" @click="openCreateDialog(forum.id)" :title="$t('admin.forums.addSubForum')" />
                                <v-btn icon="mdi-pencil" size="small" variant="text" :disabled="deletingId !== null" @click="openEditDialog(forum)" />
                                <v-btn icon="mdi-delete" size="small" variant="text" color="error" :loading="deletingId === forum.id" :disabled="deletingId !== null" @click="confirmDelete(forum)" />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <!-- Sub-forums -->
                    <div v-if="forum.children && forum.children.length">
                        <v-divider />
                        <v-card-text class="py-0">
                            <v-list density="compact" class="py-0">
                                <v-list-item
                                    v-for="child in forum.children"
                                    :key="child.id"
                                    class="pl-8"
                                >
                                    <template v-slot:prepend>
                                        <v-icon size="18" class="mr-2">mdi-subdirectory-arrow-right</v-icon>
                                        <v-icon size="18" color="primary" class="mr-2">mdi-forum-outline</v-icon>
                                    </template>
                                    <v-list-item-title>
                                        <span class="font-weight-medium">{{ child.name }}</span>
                                        <v-chip v-if="child.is_locked" size="x-small" variant="tonal" color="warning" class="ml-2">
                                            {{ $t('forum.locked') }}
                                        </v-chip>
                                    </v-list-item-title>
                                    <v-list-item-subtitle v-if="child.description">{{ child.description }}</v-list-item-subtitle>
                                    <template v-slot:append>
                                        <span class="text-caption text-medium-emphasis mr-4">
                                            {{ child.threads_count || 0 }} {{ $t('forum.threads') }}
                                        </span>
                                        <v-btn icon="mdi-pencil" size="x-small" variant="text" :disabled="deletingId !== null" @click="openEditDialog(child)" />
                                        <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" :loading="deletingId === child.id" :disabled="deletingId !== null" @click="confirmDelete(child)" />
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </div>
                </v-card>
            </div>

            <!-- Empty State -->
            <empty-state
                v-else
                icon="mdi-forum-outline"
                :title="$t('admin.forums.noForums')"
                :text="$t('admin.forums.noForumsDescription')"
            >
                <template #actions>
                    <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="openCreateDialog()">
                        {{ $t('admin.forums.createFirst') }}
                    </v-btn>
                </template>
            </empty-state>
        </v-container>

        <!-- Create/Edit Dialog -->
        <v-dialog v-model="dialog.show" max-width="600" persistent>
            <v-card>
                <v-card-title class="text-h6">
                    {{ dialog.editing ? $t('admin.forums.editForum') : $t('admin.forums.createForum') }}
                </v-card-title>
                <v-divider />
                <v-card-text>
                    <v-text-field
                        v-model="dialog.form.name"
                        :label="$t('admin.forums.name')"
                        variant="outlined"
                        density="comfortable"
                        :error-messages="dialog.errors.name"
                        class="mb-3"
                    />
                    <v-textarea
                        v-model="dialog.form.description"
                        :label="$t('admin.forums.description')"
                        variant="outlined"
                        density="comfortable"
                        rows="3"
                        class="mb-3"
                    />
                    <v-select
                        v-model="dialog.form.parent_id"
                        :items="parentOptions"
                        item-title="name"
                        item-value="id"
                        :label="$t('admin.forums.parentForum')"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        class="mb-3"
                    />
                    <v-text-field
                        v-model.number="dialog.form.position"
                        :label="$t('admin.forums.position')"
                        variant="outlined"
                        density="comfortable"
                        type="number"
                        class="mb-3"
                    />
                    <v-row>
                        <v-col cols="6">
                            <v-switch
                                v-model="dialog.form.is_private"
                                :label="$t('admin.forums.isPrivate')"
                                color="info"
                                hide-details
                            />
                        </v-col>
                        <v-col cols="6">
                            <v-switch
                                v-model="dialog.form.is_locked"
                                :label="$t('admin.forums.isLocked')"
                                color="warning"
                                hide-details
                            />
                        </v-col>
                    </v-row>
                    <v-divider class="mt-4 mb-2" />
                    <div class="text-subtitle-2 text-medium-emphasis mb-2">{{ $t('admin.forums.rolePermissions') }}</div>
                    <v-select
                        v-model="dialog.form.allowed_roles"
                        :items="roles"
                        :label="$t('admin.forums.allowedRoles')"
                        :hint="$t('admin.forums.allowedRolesHint')"
                        variant="outlined"
                        density="comfortable"
                        multiple
                        chips
                        closable-chips
                        persistent-hint
                        class="mb-3"
                    />
                    <v-select
                        v-model="dialog.form.post_roles"
                        :items="roles"
                        :label="$t('admin.forums.postRoles')"
                        :hint="$t('admin.forums.postRolesHint')"
                        variant="outlined"
                        density="comfortable"
                        multiple
                        chips
                        closable-chips
                        persistent-hint
                        class="mb-3"
                    />
                    <v-select
                        v-model="dialog.form.moderate_roles"
                        :items="roles"
                        :label="$t('admin.forums.moderateRoles')"
                        :hint="$t('admin.forums.moderateRolesHint')"
                        variant="outlined"
                        density="comfortable"
                        multiple
                        chips
                        closable-chips
                        persistent-hint
                        class="mb-3"
                    />
                    <v-select
                        v-model="dialog.form.delete_roles"
                        :items="roles"
                        :label="$t('admin.forums.deleteRoles')"
                        :hint="$t('admin.forums.deleteRolesHint')"
                        variant="outlined"
                        density="comfortable"
                        multiple
                        chips
                        closable-chips
                        persistent-hint
                    />
                </v-card-text>
                <v-card-actions>
                    <v-spacer />
                    <v-btn variant="text" :disabled="dialog.saving" @click="closeDialog">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" variant="flat" :loading="dialog.saving" :disabled="dialog.saving" @click="saveForum">
                        {{ dialog.editing ? $t('common.save') : $t('common.add') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>
    </div>
</template>

<script>
import axios from 'axios'
import PageHeader from '../../components/common/PageHeader.vue'
import EmptyState from '../../components/common/EmptyState.vue'
import LoadingState from '../../components/common/LoadingState.vue'

export default {
    name: 'ForumManager',
    components: {
        PageHeader,
        EmptyState,
        LoadingState
    },
    data() {
        return {
            loading: false,
            forums: [],
            roles: [],
            deletingId: null,
            dialog: {
                show: false,
                editing: false,
                editId: null,
                saving: false,
                form: {
                    name: '',
                    description: '',
                    parent_id: null,
                    position: 0,
                    is_private: false,
                    is_locked: false,
                    allowed_roles: [],
                    post_roles: [],
                    moderate_roles: [],
                    delete_roles: []
                },
                errors: {}
            }
        }
    },
    computed: {
        parentOptions() {
            // Only top-level forums can be parents (exclude the forum being edited)
            return this.forums
                .filter(f => !this.dialog.editing || f.id !== this.dialog.editId)
                .map(f => ({ id: f.id, name: f.name }))
        }
    },
    mounted() {
        this.fetchForums()
        this.fetchRoles()
    },
    methods: {
        async fetchForums() {
            this.loading = true
            try {
                const response = await axios.get('/api/forums')
                this.forums = response.data
            } catch (error) {
                this.$dialog.requestError(error, this.$t('errors.general'))
            } finally {
                this.loading = false
            }
        },

        async fetchRoles() {
            try {
                const response = await axios.get('/api/datatable/roles', { params: { per_page: 100 } })
                const records = response.data?.data?.records
                // Paginated since the table rework; older responses were a plain array
                this.roles = (Array.isArray(records) ? records : (records?.data || [])).map(r => r.name)
            } catch (error) {
                console.error('Failed to load roles', error)
            }
        },

        openCreateDialog(parentId = null) {
            this.dialog.editing = false
            this.dialog.editId = null
            this.dialog.form = {
                name: '',
                description: '',
                parent_id: parentId,
                position: 0,
                is_private: false,
                is_locked: false,
                allowed_roles: [],
                post_roles: [],
                moderate_roles: [],
                delete_roles: []
            }
            this.dialog.errors = {}
            this.dialog.show = true
        },

        openEditDialog(forum) {
            this.dialog.editing = true
            this.dialog.editId = forum.id
            this.dialog.form = {
                name: forum.name,
                description: forum.description || '',
                parent_id: forum.parent_id,
                position: forum.position,
                is_private: forum.is_private,
                is_locked: forum.is_locked,
                allowed_roles: forum.allowed_roles || [],
                post_roles: forum.post_roles || [],
                moderate_roles: forum.moderate_roles || [],
                delete_roles: forum.delete_roles || []
            }
            this.dialog.errors = {}
            this.dialog.show = true
        },

        closeDialog() {
            this.dialog.show = false
        },

        async saveForum() {
            if (this.dialog.saving) return
            this.dialog.saving = true
            this.dialog.errors = {}

            let successMessage
            try {
                if (this.dialog.editing) {
                    await axios.patch(`/api/forums/${this.dialog.editId}`, this.dialog.form)
                    successMessage = this.$t('admin.forums.forumUpdated')
                } else {
                    await axios.post('/api/forums', this.dialog.form)
                    successMessage = this.$t('admin.forums.forumCreated')
                }
                this.closeDialog()
            } catch (error) {
                this.dialog.saving = false
                if (error.response?.status === 422) {
                    this.dialog.errors = error.response.data.errors || {}
                } else {
                    this.$dialog.requestError(error, this.$t('errors.general'))
                }
                return
            } finally {
                this.dialog.saving = false
            }

            this.$dialog.success(successMessage)
            await this.fetchForums()
        },

        async confirmDelete(forum) {
            if (this.deletingId !== null) return
            const ok = await this.$dialog.confirmDelete(this.$t('admin.forums.confirmDelete', { name: forum.name }))
            if (!ok) return

            this.deletingId = forum.id
            try {
                await axios.delete(`/api/forums/${forum.id}`)
            } catch (error) {
                this.$dialog.requestError(error, this.$t('errors.general'))
                return
            } finally {
                this.deletingId = null
            }

            this.$dialog.success(this.$t('admin.forums.forumDeleted'))
            await this.fetchForums()
        }
    }
}
</script>
