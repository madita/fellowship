import axios from 'axios'

export default {
    namespaced: true,
    state: {
        authenticated: false,

        user: {
            email_verified_at: ""
        },

        roles: {},
        permissions: {}
    },

    getters: {
        isLogged: state => !!state.user,

        isVerified: state => !!state.user && !!state.user.email_verified_at,

        authenticated(state) {
            return state.authenticated
        },

        permissions(state) {
            return state.permissions
        },

        roles(state) {
            return state.roles
        },

        user(state) {
            return state.user
        },
    },

    mutations: {
        SET_AUTHENTICATED(state, value) {
            state.authenticated = value
            localStorage.setItem('authenticated', value)
        },

        SET_USER(state, value) {
            state.user = value
            localStorage.setItem('user', JSON.stringify(value))
        },

        SET_ROLES(state, value) {
            state.roles = value

            localStorage.setItem("roles", JSON.stringify(value));
        },

        SET_PERMISSIONS(state, value) {
            state.permissions = value
            localStorage.setItem("permissions", JSON.stringify(value));
        }
    },
    actions: {
        async signIn({dispatch}, credentials) {
            await axios.get('/sanctum/csrf-cookie')
            await axios.post('/login', credentials)

            return dispatch('me')
        },

        async signOut({dispatch}) {
            await axios.post('/logout')

            return dispatch('me')
        },

        me({commit}) {
            return axios.get('/api/user').then((response) => {
                commit('SET_AUTHENTICATED', true)
                commit('SET_USER', response.data.user)
                commit('SET_ROLES', response.data.roles)
                commit('SET_PERMISSIONS', response.data.permissions)

                // commit('setUserData', response.data)
            }).catch(() => {
                commit('SET_AUTHENTICATED', false)
                commit('SET_USER', null)
                commit('SET_ROLES', {})
                commit('SET_PERMISSIONS', {})
                localStorage.removeItem('user')
                localStorage.removeItem('roles')
                localStorage.removeItem('permissions')
                location.reload()
            })
        }
    }

}
