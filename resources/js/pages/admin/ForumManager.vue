<template>
    <div class="flex-grow-1">
        <v-container>
            <div class="d-flex align-center justify-space-between mb-6">
                <div>
                    <h1 class="text-h4 font-weight-bold">{{ $t('admin.forums.title') }}</h1>
                    <p class="text-body-2 text-medium-emphasis">{{ $t('admin.forums.subtitle') }}</p>
                </div>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-plus"
                    @click="openCreateDialog()"
                >
                    {{ $t('admin.forums.createForum') }}
                </v-btn>
            </div>

            <!-- Alert -->
            <v-alert v-if="alert.show" :type="alert.type" class="mb-4" closable @click:close="alert.show = false">
                {{ alert.message }}
            </v-alert>

            <!-- Loading -->
            <div v-if="loading" class="text-center py-12">
                <v-progress-circular size="48" indeterminate color="primary" />
            </div>

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
                                        <div class="d-flex align-center gap-2">
                                            <span class="font-weight-bold text-body-1">{{ forum.name }}</span>
                                            <v-chip v-if="forum.is_locked" size="x-small" color="warning">
                                                {{ $t('forum.locked') }}
                                            </v-chip>
                                            <v-chip v-if="forum.is_private" size="x-small" color="info">
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
                            <v-col cols="12" md="2" class="text-right">
                                <v-btn icon="mdi-plus" size="small" variant="text" @click="openCreateDialog(forum.id)" :title="$t('admin.forums.addSubForum')" />
                                <v-btn icon="mdi-pencil" size="small" variant="text" @click="openEditDialog(forum)" />
                                <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="confirmDelete(forum)" />
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
                                        <v-chip v-if="child.is_locked" size="x-small" color="warning" class="ml-2">
                                            {{ $t('forum.locked') }}
                                        </v-chip>
                                    </v-list-item-title>
                                    <v-list-item-subtitle v-if="child.description">{{ child.description }}</v-list-item-subtitle>
                                    <template v-slot:append>
                                        <span class="text-caption text-medium-emphasis mr-4">
                                            {{ child.threads_count || 0 }} {{ $t('forum.threads') }}
                                        </span>
                                        <v-btn icon="mdi-pencil" size="x-small" variant="text" @click="openEditDialog(child)" />
                                        <v-btn icon="mdi-delete" size="x-small" variant="text" color="error" @click="confirmDelete(child)" />
                                    </template>
                                </v-list-item>
                            </v-list>
                        </v-card-text>
                    </div>
                </v-card>
            </div>

            <!-- Empty State -->
            <div v-else class="text-center py-12">
                <v-icon size="80" color="disabled" class="mb-4">mdi-forum-outline</v-icon>
                <h3 class="text-h6 mb-2">{{ $t('admin.forums.noForums') }}</h3>
                <p class="text-body-2 text-medium-emphasis mb-4">{{ $t('admin.forums.noForumsDescription') }}</p>
                <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog()">
                    {{ $t('admin.forums.createFirst') }}
                </v-btn>
            </div>
        </v-container>

        <!-- Create/Edit Dialog -->
        <v-dialog v-model="dialog.show" max-width="600" persistent>
            <v-card>
                <v-card-title>
                    {{ dialog.editing ? $t('admin.forums.editForum') : $t('admin.forums.createForum') }}
                </v-card-title>
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
                    <v-btn variant="text" @click="closeDialog">{{ $t('common.cancel') }}</v-btn>
                    <v-btn color="primary" :loading="dialog.saving" @click="saveForum">
                        {{ dialog.editing ? $t('common.save') : $t('common.add') }}
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <!-- Delete Confirm Dialog -->
        <ConfirmDialog
            v-model="deleteDialog.show"
            :content="$t('admin.forums.confirmDelete', { name: deleteDialog.forum?.name })"
            :resolve="onDeleteConfirm"
        />
    </div>
</template>

<script>
import axios from 'axios'
import ConfirmDialog from '@/components/common/ConfirmDialog.vue'

export default {
    name: 'ForumManager',
    components: { ConfirmDialog },
    data() {
        return {
            loading: false,
            forums: [],
            roles: [],
            alert: {
                show: false,
                type: 'success',
                message: ''
            },
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
            },
            deleteDialog: {
                show: false,
                forum: null
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
                this.showAlert('error', error.response?.data?.message || 'Failed to load forums')
            } finally {
                this.loading = false
            }
        },

        async fetchRoles() {
            try {
                const response = await axios.get('/api/datatable/roles')
                const records = response.data?.data?.records || []
                this.roles = records.map(r => r.name)
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
            this.dialog.saving = true
            this.dialog.errors = {}

            try {
                if (this.dialog.editing) {
                    await axios.patch(`/api/forums/${this.dialog.editId}`, this.dialog.form)
                    this.showAlert('success', this.$t('admin.forums.forumUpdated'))
                } else {
                    await axios.post('/api/forums', this.dialog.form)
                    this.showAlert('success', this.$t('admin.forums.forumCreated'))
                }
                this.closeDialog()
                await this.fetchForums()
            } catch (error) {
                if (error.response?.status === 422) {
                    this.dialog.errors = error.response.data.errors || {}
                } else {
                    this.showAlert('error', error.response?.data?.message || 'Failed to save forum')
                }
            } finally {
                this.dialog.saving = false
            }
        },

        confirmDelete(forum) {
            this.deleteDialog.forum = forum
            this.deleteDialog.show = true
        },

        async onDeleteConfirm(confirmed) {
            if (!confirmed || !this.deleteDialog.forum) return

            try {
                await axios.delete(`/api/forums/${this.deleteDialog.forum.id}`)
                this.showAlert('success', this.$t('admin.forums.forumDeleted'))
                await this.fetchForums()
            } catch (error) {
                this.showAlert('error', error.response?.data?.message || 'Failed to delete forum')
            }
        },

        showAlert(type, message) {
            this.alert = { show: true, type, message }
        }
    }
}
</script>

<style scoped>
.gap-2 {
    gap: 8px;
}
</style>
