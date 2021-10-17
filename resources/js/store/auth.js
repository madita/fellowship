import axios from 'axios'

export default {
    namespaced: true,
    state: {
        authenticated: false,

        user: {
            email_verified_at: ""
        }
    },

    getters: {
        isLogged: state => !!state.user,

        isVerified: state => !!state.user && !!state.user.email_verified_at,

        authenticated(state) {
            return state.authenticated
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
                commit('SET_USER', response.data)
                // commit('setUserData', response.data)
            }).catch(() => {
                commit('SET_AUTHENTICATED', false)
                commit('SET_USER', null)
                localStorage.removeItem('user')
                location.reload()
            })
        }
    }

}
