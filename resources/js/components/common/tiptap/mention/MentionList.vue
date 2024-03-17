<template>
    <div class="items">
        <template v-if="items.length">
<!--            <button-->
<!--                class="item"-->
<!--                :class="{ 'is-selected': index === selectedIndex }"-->
<!--                v-for="(item, index) in items"-->
<!--                :key="index"-->
<!--            >-->
<!--                <template  @click="selectItem(index)" v-if="item.title">{{  item.title }}({{ item.slug}})</template>-->
<!--                <template  @click="selectItem(index)" v-else>{{ item.username ?? item.title ?? item }}</template>-->
<!--                <input v-if="index === selectedIndex" type="text" v-model="alternative" @keydown.enter="setAlternative(item)"/>-->

<!--            </button>-->

            <div  class="item"
                   :class="{ 'is-selected': index === selectedIndex }"
                   v-for="(item, index) in items"
                   :key="index"
                 >
                <span @click="selectItem(index)" v-if="item.title">{{  item.title }}({{ item.slug}})</span>
                <span @click="selectItem(index)" v-else>{{ item.username ?? item.title ?? item }}</span>
                <input  :ref="setInputRef" v-if="index === selectedIndex" type="text" v-model="alternative" @keydown.enter="setAlternative(item)"/>



            </div>
        </template>
        <div class="item" v-else>
            No result
        </div>
    </div>
</template>

<script>
import { ref } from 'vue';

export default {
    props: {
        items: {
            type: Array,
            required: true,
        },

        command: {
            type: Function,
            required: true,
        },
    },

    setup(props, { emit }) {
        const alternative = ref('');
        const selectedIndex = ref(0);
        const inputsRef = ref([]);

        const setInputRef = (el) => {
            if (el) {
                inputsRef.value.push(el);
            }
        };

        const focusInput = (index) => {
            // Clear the old refs
            inputsRef.value = inputsRef.value.filter((input) => document.body.contains(input));
            if (inputsRef.value[index]) {
                inputsRef.value[index].focus();
            }
        };

        // ... other setup code

        return {
            alternative,
            selectedIndex,
            setInputRef,
            focusInput,
            // ... other exposed properties and methods
        };
    },
    watch: {
        items() {
            this.selectedIndex = 0
        },
    },

    methods: {
        onKeyDown({ event }) {
            console.log('event',event)
            if (event.key === 'ArrowUp') {
                this.upHandler()
                return true
            }

            if (event.key === 'ArrowDown') {
                this.downHandler()
                return true
            }

            if (event.key === 'Tab') {
                this.enterHandler()
                return true
            }

            if (event.key === 'Enter') {
                this.enterHandler()
                return true
            }

            if (event.code === 'Space') {
                this.focusInput()
                return true
            }

            return false
        },

        upHandler() {
            this.selectedIndex = ((this.selectedIndex + this.items.length) - 1) % this.items.length
        },

        downHandler() {
            this.selectedIndex = (this.selectedIndex + 1) % this.items.length
        },

        enterHandler() {
            this.selectItem(this.selectedIndex)
        },

        focusInput() {
            this.alternativeInput.focus();
        },

        setAlternative(item) {
            console.log('setalternative',item)
            if (item.username) {
                this.command({ id: item.id, name: item.name, username: item.username, slug: item.slug, alternative:this.alternative })
            } else {
                this.command({ id: item.id, title: item.title, slug: item.slug, alternative:this.alternative })
            }
        },

        selectItem(index) {
            const item = this.items[index]

            //selected user
            if (item.username) {
                this.command({ id: item.id, name: item.name, username: item.username })
            }
            //selected hashtag
            else if (item.name) {
                this.command({ id: item.id, name: item.name, color: item.color, slug: item.slug })
            }
            // selected page
            else if (item.title) {
                this.command({ id: item.id, title: item.title, slug: item.slug })
            }
            else if (item) {
                this.command({ id: item })
            }
        },
    },
}
</script>

<style lang="scss">
.items {
    padding: 0.2rem;
    position: relative;
    border-radius: 0.5rem;
    background: #FFF;
    color: rgba(0, 0, 0, 0.8);
    overflow: hidden;
    font-size: 0.9rem;
;
}

.item {
    display: block;
    margin: 0;
    width: 100%;
    text-align: left;
    background: transparent;
    border-radius: 0.4rem;
    border: 1px solid transparent;
    padding: 0.2rem 0.4rem;

    &.is-selected {
        border-color: #000;
    }
}
</style>
