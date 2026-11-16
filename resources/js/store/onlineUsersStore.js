// store/chatStore.js
import { defineStore } from 'pinia';

export const useOnlineUsersStore = defineStore({
    id: 'online-users',
    state: () => ({
        users: []
    }),
    actions: {
        setUsers(users) {
            console.log('setusers', users)
            this.users = users
        },
        addUser(user) {
            if(!this.users.includes(user))
                this.users.push(user);
        },
        removeUser(user) {
            // this.users.push(user);
            this.users = this.users.filter((u) => u.id !== user.id);
        }
    }
});
