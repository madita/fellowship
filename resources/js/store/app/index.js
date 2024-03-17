import { defineStore } from 'pinia';

import actions from './actions'
import mutations from './mutations'
import configs from '../../configs';

const {
    product,
    time,
    theme: { globalTheme, menuTheme, toolbarTheme, isToolbarDetached, isContentBoxed, isRTL }
} = configs;

export const useAppStore = defineStore({
    id: 'appStore',

    // defining state
    state: () => ({
        product,

        time,

        // themes and layout configurations
        globalTheme,
        menuTheme,
        toolbarTheme,
        isToolbarDetached,
        isContentBoxed,
        isRTL,

        // App.vue main toast
        toast: {
            show: false,
            color: 'black',
            message: '',
            timeout: 3000
        }
    }),

    // Getters (if you have any)

    // Converting actions and mutations
    actions: {
        ...actions,   // I'm spreading the existing actions here. Depending on their content, you might need to adapt them.

        // Convert mutations to methods directly manipulating state
        ...Object.keys(mutations).reduce((acc, mutationName) => {
            acc[mutationName] = function(payload) {
                mutations[mutationName](this, payload);
            };
            return acc;
        }, {})
    }
});
