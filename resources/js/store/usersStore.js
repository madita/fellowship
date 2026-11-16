// Create this file: store/usersStore.js

import { defineStore } from 'pinia'
import axios from 'axios' // or your API client

export const useUsersStore = defineStore('users', {
    state: () => ({
        allUsers: [],
        isLoading: false,
        searchResults: [],
        searchLoading: false,
    }),

    actions: {
        async fetchUsers() {
            this.isLoading = true;
            try {
                const response = await axios.post('/api/users/search');
                this.allUsers = response.data;
                return response.data;
            } catch (error) {
                console.error('Error fetching users:', error);
                this.allUsers = [];
                throw new Error(error.response?.data?.message || 'Failed to fetch users');
            } finally {
                this.isLoading = false;
            }
        },

        async searchUsers(query) {
            if (!query || query.length < 2) {
                this.searchResults = [];
                return;
            }

            this.searchLoading = true;
            try {
                const response = await axios.post(`/api/users/search?q=${encodeURIComponent(query)}`);
                this.searchResults = response.data;
                return response.data;
            } catch (error) {
                console.error('Error searching users:', error);
                this.searchResults = [];
                throw error;
            } finally {
                this.searchLoading = false;
            }
        },

        clearSearch() {
            this.searchResults = [];
        }
    },

    getters: {
        availableUsers: (state) => state.allUsers,
        isLoadingUsers: (state) => state.isLoading,
        userById: (state) => (id) => state.allUsers.find(user => user.id === id),
        searchedUsers: (state) => state.searchResults,
    }
})

// Also add this method to your existing conversationStore.js:

