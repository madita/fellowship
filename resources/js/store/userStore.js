import { defineStore } from 'pinia'
import { useApi } from '@/api/useAPI.js'
// import axios from "axios";

const api = useApi()

export const useUserStore = defineStore({
    id: 'user',

    state: () => JSON.parse(localStorage.getItem('USER_INFO')) ?? {
        user:null,
        roles:null,
        permissions:null,
    },
    // getters: {
    //     console.log()
    //     user: (state) => state.user,
    //     roles: (state) => state.role,
    //     permissions: (state) => state.permissions,
    // },

    actions: {
        updateState(payload) {
            console.log('userpayload', payload)
            console.log('userthis.$state', this.$state)
            let newUserState = { ...this.$state, ...payload }
            console.log('nuserewUserState', newUserState)
            localStorage.removeItem('USER_INFO')
            localStorage.setItem('USER_INFO', JSON.stringify(newUserState))
            this.$reset()
        },

        async getToken() {
            await api.get('/sanctum/csrf-cookie')
        },

        async storeInfo() {
            // try {
            //     const data = await axios.get('api/user')
            //     this.user = data.user
            //     // this.roles = data.roles
            //     // this.permissions = data.permissions
            //     // console.log('user',this.user)
            //
            // }
            // catch (error) {
            //     //alert(error)
            //     console.log('error userstore', error)
            // }
            let { data: userInfo } = await api.get('/user')
            // let { data:  userInfo } = await axios.get('/api/user')
            console.log('userInfo',userInfo)
            this.updateState(userInfo)
            // this.user = userInfo.user;
            // this.roles = userInfo.roles;
            localStorage.setItem('USER_INFO', JSON.stringify(userInfo))
            this.$reset()
        },

        // async resetInfo() {
        //     localStorage.removeItem('USER_INFO')
        //     this.$reset()
        // },
    },
})
