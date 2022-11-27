<template>
    <div class="items">
        <template v-if="items.length">
            <button
                class="item"
                :class="{ 'is-selected': index === selectedIndex }"
                v-for="(item, index) in items"
                :key="index"
            >
                <template  @click="selectItem(index)" v-if="item.title">{{  item.title }}({{ item.slug}})</template>
                <template  @click="selectItem(index)" v-else>{{ item.username ?? item.title ?? item }}</template>
                <input type="text" v-model="alternative" @keydown.enter="setAlternative(item)">

            </button>
        </template>
        <div class="item" v-else>
            No result
        </div>
    </div>
</template>

<script>
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

    data() {
        return {
            alternative:"",
            selectedIndex: 0,
        }
    },

    watch: {
        items() {
            this.selectedIndex = 0
        },
    },

    methods: {
        onKeyDown({ event }) {
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

        setAlternative(item) {
            this.command({ id: item.id, title: item.title, slug: item.slug, alternative:this.alternative })
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
