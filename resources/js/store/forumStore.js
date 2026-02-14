import { defineStore } from 'pinia'
import axios from 'axios'

export const useForumStore = defineStore('forum', {
    state: () => ({
        forums: [],
        currentForum: null,
        threads: [],
        threadsPagination: {},
        currentThread: null,
        posts: [],
        postsPagination: {},
        threadPermissions: {
            can_reply: false,
            can_edit: false,
            can_delete: false
        },
        isSubscribed: false,
        activities: [],
        activitiesPagination: {},
        loading: false,
        submitting: false,
        error: null,
        searchQuery: '',
        searchResults: { threads: null, posts: null },
        searchLoading: false,
        searchType: 'all'
    }),

    getters: {
        pinnedThreads: (state) => state.threads.filter(t => t.is_pinned),
        regularThreads: (state) => state.threads.filter(t => !t.is_pinned),
        topLevelPosts: (state) => state.posts.filter(p => !p.parent_id),
        isForumLocked: (state) => state.currentForum?.is_locked ?? false,
        totalSearchResults: (state) => {
            return (state.searchResults.threads?.total || 0) + (state.searchResults.posts?.total || 0)
        },
        hasSearchResults: (state) => {
            return (state.searchResults.threads?.data?.length > 0) || (state.searchResults.posts?.data?.length > 0)
        }
    },

    actions: {
        async fetchForums() {
            this.loading = true
            this.error = null
            try {
                const response = await axios.get('/api/forums')
                this.forums = response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to load forums'
                throw error
            } finally {
                this.loading = false
            }
        },

        async fetchForum(slug, page = 1, { filter, sort } = {}) {
            this.loading = true
            this.error = null
            try {
                const params = { page }
                if (filter) params.filter = filter
                if (sort) params.sort = sort

                const response = await axios.get(`/api/forums/${slug}`, { params })
                this.currentForum = response.data.forum
                const threadsResponse = response.data.threads
                this.threads = threadsResponse.data || []
                this.threadsPagination = {
                    current_page: threadsResponse.current_page,
                    last_page: threadsResponse.last_page,
                    per_page: threadsResponse.per_page,
                    total: threadsResponse.total,
                    from: threadsResponse.from,
                    to: threadsResponse.to,
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to load forum'
                throw error
            } finally {
                this.loading = false
            }
        },

        async fetchThread(forumSlug, threadSlug, page = 1) {
            this.loading = true
            this.error = null
            try {
                const response = await axios.get(`/api/forums/${forumSlug}/threads/${threadSlug}`, {
                    params: { page }
                })
                this.currentThread = response.data.thread
                const postsResponse = response.data.posts
                this.posts = postsResponse.data || []
                this.postsPagination = {
                    current_page: postsResponse.current_page,
                    last_page: postsResponse.last_page,
                    per_page: postsResponse.per_page,
                    total: postsResponse.total,
                    from: postsResponse.from,
                    to: postsResponse.to,
                }
                this.threadPermissions = {
                    can_reply: response.data.can_reply ?? false,
                    can_edit: response.data.can_edit ?? false,
                    can_delete: response.data.can_delete ?? false
                }
                this.isSubscribed = response.data.is_subscribed ?? false
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to load thread'
                throw error
            } finally {
                this.loading = false
            }
        },

        async createThread(forumId, { title, body }) {
            this.submitting = true
            this.error = null
            try {
                const response = await axios.post(`/api/forums/${forumId}/threads`, { title, body })
                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to create thread'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async updateThread(threadId, data) {
            this.submitting = true
            this.error = null
            try {
                const response = await axios.patch(`/api/threads/${threadId}`, data)
                if (this.currentThread && this.currentThread.id === threadId) {
                    this.currentThread = response.data
                }
                return response.data
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update thread'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async deleteThread(threadId) {
            this.submitting = true
            this.error = null
            try {
                await axios.delete(`/api/threads/${threadId}`)
                this.threads = this.threads.filter(t => t.id !== threadId)
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to delete thread'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async createPost(threadId, { body, parent_id }) {
            this.submitting = true
            this.error = null
            try {
                const response = await axios.post(`/api/threads/${threadId}/posts`, { body, parent_id })
                const newPost = response.data

                if (parent_id) {
                    // Add as nested reply
                    const addReply = (posts) => {
                        for (const post of posts) {
                            if (post.id === parent_id) {
                                if (!post.replies) post.replies = []
                                post.replies.push(newPost)
                                return true
                            }
                            if (post.replies && addReply(post.replies)) return true
                        }
                        return false
                    }
                    addReply(this.posts)
                } else {
                    this.posts.push(newPost)
                }

                return newPost
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to create post'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async updatePost(postId, { body }) {
            this.submitting = true
            this.error = null
            try {
                const response = await axios.patch(`/api/posts/${postId}`, { body })
                const updated = response.data

                const updateInList = (posts) => {
                    for (let i = 0; i < posts.length; i++) {
                        if (posts[i].id === postId) {
                            posts[i] = { ...posts[i], ...updated }
                            return true
                        }
                        if (posts[i].replies && updateInList(posts[i].replies)) return true
                    }
                    return false
                }
                updateInList(this.posts)

                return updated
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update post'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async deletePost(postId) {
            this.submitting = true
            this.error = null
            try {
                await axios.delete(`/api/posts/${postId}`)

                const removeFromList = (posts) => {
                    for (let i = 0; i < posts.length; i++) {
                        if (posts[i].id === postId) {
                            posts.splice(i, 1)
                            return true
                        }
                        if (posts[i].replies && removeFromList(posts[i].replies)) return true
                    }
                    return false
                }
                removeFromList(this.posts)
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to delete post'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async markAsSolution(postId) {
            this.submitting = true
            this.error = null
            try {
                const response = await axios.post(`/api/posts/${postId}/mark-as-solution`)
                const updatedPost = response.data.post

                // Clear previous solution and set new one
                const updateSolution = (posts) => {
                    for (const post of posts) {
                        if (post.id === updatedPost.id) {
                            post.is_solution = updatedPost.is_solution
                        } else {
                            post.is_solution = false
                        }
                        if (post.replies) updateSolution(post.replies)
                    }
                }
                updateSolution(this.posts)

                return updatedPost
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to mark as solution'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async toggleSubscription(threadId) {
            this.submitting = true
            this.error = null
            try {
                if (this.isSubscribed) {
                    const response = await axios.delete(`/api/threads/${threadId}/subscribe`)
                    this.isSubscribed = response.data.is_subscribed
                } else {
                    const response = await axios.post(`/api/threads/${threadId}/subscribe`)
                    this.isSubscribed = response.data.is_subscribed
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update subscription'
                throw error
            } finally {
                this.submitting = false
            }
        },

        async toggleLike(postId) {
            const findPost = (posts) => {
                for (const post of posts) {
                    if (post.id === postId) return post
                    if (post.replies) {
                        const found = findPost(post.replies)
                        if (found) return found
                    }
                }
                return null
            }

            const post = findPost(this.posts)
            if (!post) return

            try {
                let response
                if (post.is_liked) {
                    response = await axios.delete(`/api/posts/${postId}/like`)
                } else {
                    response = await axios.post(`/api/posts/${postId}/like`)
                }
                post.is_liked = response.data.is_liked
                post.like_count = response.data.like_count
            } catch (error) {
                this.error = error.response?.data?.message || 'Failed to update like'
                throw error
            }
        },

        async fetchActivities(page = 1) {
            try {
                const response = await axios.get('/api/activity', { params: { page, log: 'forum' } })
                this.activities = response.data.data || []
                this.activitiesPagination = {
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    total: response.data.total,
                }
            } catch (error) {
                console.error('Failed to load activities:', error)
            }
        },

        async searchForum(query, { type = 'all', page = 1 } = {}) {
            this.searchLoading = true
            this.searchQuery = query
            this.searchType = type
            this.error = null
            try {
                const response = await axios.get('/api/forums/search', {
                    params: { q: query, type, page }
                })
                this.searchResults = {
                    threads: response.data.threads,
                    posts: response.data.posts
                }
            } catch (error) {
                this.error = error.response?.data?.message || 'Search failed'
                throw error
            } finally {
                this.searchLoading = false
            }
        },

        clearSearch() {
            this.searchQuery = ''
            this.searchResults = { threads: null, posts: null }
            this.searchLoading = false
            this.searchType = 'all'
        }
    }
})
