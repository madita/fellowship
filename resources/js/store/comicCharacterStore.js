import { defineStore } from 'pinia';
import axios from 'axios';
import { debug } from '@/utils/debug.js';
import { PRESETS, normaliseSpec, registerCharacters } from '@/utils/comicCharacter.js';

const log = debug.module('ComicCharacters');

/**
 * The comic chat characters members can pick from: the ones that ship with
 * the site plus whatever an admin has built in the character creator.
 *
 * Until the list has been loaded — and if it cannot be — the built-in ones
 * stand in, so a character always draws as something.
 */
export const useComicCharacterStore = defineStore({
    id: 'comicCharacters',

    state: () => ({
        characters: [],
        loaded: false,
        loading: null,
    }),

    getters: {
        /**
         * Every character that can be picked, the built-in ones first.
         */
        available: (state) => {
            if (!state.loaded) {
                return Object.entries(PRESETS).map(([key, spec]) => ({
                    key,
                    name: spec.name,
                    spec: normaliseSpec(spec),
                }));
            }

            return state.characters;
        },

        specFor: (state) => (key) => {
            const found = state.characters.find(character => character.key === key);

            return found ? found.spec : (PRESETS[key] || null);
        },
    },

    actions: {
        async load({ force = false } = {}) {
            if (this.loaded && !force) return this.characters;
            if (this.loading) return this.loading;

            this.loading = axios.get('/api/irc/comic-characters')
                .then(({ data }) => {
                    this.characters = (data.data || []).map(character => ({
                        ...character,
                        spec: normaliseSpec(character.spec),
                    }));
                    // The renderer draws from the registry, not the store
                    registerCharacters(this.characters);
                    this.loaded = true;

                    return this.characters;
                })
                .catch((error) => {
                    // The built-in characters stand in, so chat still draws
                    log.warn('Could not load the comic characters:', error);

                    return this.characters;
                })
                .finally(() => {
                    this.loading = null;
                });

            return this.loading;
        },
    },
});

export default useComicCharacterStore;
