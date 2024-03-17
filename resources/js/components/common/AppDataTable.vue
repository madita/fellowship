<template>
    <div class="data-table">
        <slot name="top"></slot>
        <v-table>
            <thead>
            <tr>
                <th v-for="(header) in headers" :key="header.value">{{ header.text }}</th>
            </tr>
            </thead>
            <tbody>
            <tr v-for="item in items" :key="item.id">
                <td v-for="(header) in getHeaders" :key="header.value">{{ item[header.value] }}</td>
                <td>
                    <slot name="actions" :item="item"></slot>
                </td>
            </tr>
            </tbody>
        </v-table>

        <v-pagination
            v-model="currentPage"
            :length="pageCount"
            total-visible="8"
            next-icon="mdi-chevron-right"
            prev-icon="mdi-chevron-left"
        ></v-pagination>

<!--        <v-pagination-->
<!--            v-model="currentPage"-->
<!--            class="my-4"-->
<!--            :length="totalPages"-->
<!--            @change="test"-->
<!--        ></v-pagination>-->

<!--        <v-pagination-->
<!--            :length="totalPages"-->
<!--            :model-value="currentPage"-->
<!--            @update:modelValue="onPageChange"-->
<!--        ></v-pagination>-->



<!--        <div>-->
<!--            <button @click="prevPage" :disabled="currentPage === 0">Previous</button>-->
<!--            <span>Page {{ currentPage + 1 }} of {{ totalPages }}</span>-->
<!--            <button @click="nextPage" :disabled="currentPage >= totalPages - 1 || totalPages===1">Next</button>-->
<!--        </div>-->
<!--        <template v-if="links.length > 0">-->
<!--            <ul class="v-pagination__list">-->

<!--                <li class="v-pagination__prev" data-test="v-pagination-prev">-->
<!--&lt;!&ndash;                    <button @click="prevPage" type="button"&ndash;&gt;-->
<!--&lt;!&ndash;                            class="v-btn v-btn&#45;&#45;icon v-theme&#45;&#45;light v-btn&#45;&#45;density-default v-btn&#45;&#45;size-default v-btn&#45;&#45;variant-text"&ndash;&gt;-->
<!--&lt;!&ndash;                            :disabled="!links[0].active" aria-label="Previous page" aria-disabled="true"><span class="v-btn__overlay"></span><span&ndash;&gt;-->
<!--&lt;!&ndash;                        class="v-btn__underlay"></span>&lt;!&ndash;&ndash;&gt;<span class="v-btn__content" data-no-activator=""><i&ndash;&gt;-->
<!--&lt;!&ndash;                        class="mdi-chevron-left mdi v-icon notranslate v-theme&#45;&#45;light v-icon&#45;&#45;size-default" aria-hidden="true"></i></span>&lt;!&ndash;&ndash;&gt;&lt;!&ndash;&ndash;&gt;&ndash;&gt;-->
<!--&lt;!&ndash;                    </button>&ndash;&gt;-->

<!--                    <v-btn variant="plain" @click="prevPage" :active="links[links.length - 1].active" icon="mdi-chevron-left">-->

<!--                    </v-btn>-->
<!--                </li>-->

<!--<template v-for="(link,index) in links">-->

<!--    <li  v-if="index!==0 && index!==(links.length-1)" class="v-pagination__item"  data-test="v-pagination-item" >-->
<!--        <v-btn @click="setPage(link)" :active="!link.active" variant="plain" icon>-->
<!--            {{ link.label }}-->
<!--        </v-btn>-->
<!--    </li>-->
<!--</template>-->


<!--                <li class="v-pagination__next" data-test="v-pagination-next">-->
<!--                    <v-btn variant="plain" @click="nextPage" :active="links[links.length - 1].active" icon="mdi-chevron-right">-->

<!--                    </v-btn>-->
<!--&lt;!&ndash;                    <button @click="nextPage" type="button"&ndash;&gt;-->
<!--&lt;!&ndash;                            class="v-btn v-btn&#45;&#45;icon v-theme&#45;&#45;light v-btn&#45;&#45;density-default v-btn&#45;&#45;size-default v-btn&#45;&#45;variant-text"&ndash;&gt;-->
<!--&lt;!&ndash;                            aria-label="Next page" :aria-disabled="links[links.length - 1].active" :disabled="!links[links.length - 1].active"><span class="v-btn__overlay"></span><span&ndash;&gt;-->
<!--&lt;!&ndash;                        class="v-btn__underlay"></span>&lt;!&ndash;&ndash;&gt;<span class="v-btn__content" data-no-activator=""><i&ndash;&gt;-->
<!--&lt;!&ndash;                        class="mdi-chevron-right mdi v-icon notranslate v-theme&#45;&#45;light v-icon&#45;&#45;size-default" aria-hidden="true"></i></span>&lt;!&ndash;&ndash;&gt;&lt;!&ndash;&ndash;&gt;&ndash;&gt;-->
<!--&lt;!&ndash;                    </button>&ndash;&gt;-->
<!--                </li>-->
<!--            </ul>-->

<!--        </template>-->


    </div>
</template>

<script>
import {ref, computed, watch} from 'vue'

export default {
    emits: ['update:modelValue'],
    props: {
        modelValue: {
            type: Number,
            default: 1,
        },
        total: {
            type: Number,
            required: false
        },
        items: {
            type: Array,
            required: true
        },
        headers: {
            type: Array,
            required: true
        },
        itemsPerPage: {
            type: Number,
            default: 10
        },
        from: {
            type: Number,
            default:1
        },
        to: {
            type: Number,
            default:1
        },
        pageCount: {
            type: Number,
            default:1
        },
        links: {
            type:Array,
            default: []
        }
    },
    setup(props, { emit }) {
        console.log('props',props)
        const currentPage = ref(1);

        const getHeaders = computed(() => {
            return props.headers.filter(header => header.value !== 'actions');
        });

        const totalPages = computed(() => {
            if(props.pageCount > 1) {
                return props.pageCount;
            }
            return Math.ceil(props.items.length / props.itemsPerPage);
        });

        const paginatedData = computed(() => {

            // const start = currentPage.value * props.itemsPerPage;
            const start = currentPage.value - 1;
            console.log('start', start)
            const end = start + props.itemsPerPage;
            return props.items.slice(start, end);
        });

        const nextPage = () => {
            if (currentPage.value < totalPages.value - 1) currentPage.value++;
        }

        const prevPage = () => {
            if (currentPage.value > 0) currentPage.value--;
        }

        const setPage = (link) => {
            if(link.url === null) {
                return
            }


            console.log(link)
            //if (currentPage.value < totalPages.value - 1) currentPage.value++;
        }

        // const onPageChange = (newPage) => {
        //     console.log('newpage', newPage)
        //     currentPage.value = newPage;
        //     //fetchData();
        //     emit('pagination',newPage)
        //     // emit('update:page', newPage); // Emitting the new page to the parent
        // };

        // const handlePageChange =( value) => {
        //     currentPage.value = value;
        //     console.log('test')
        //     // this.retrieveTutorials();
        //     emit('pagination',value)
        // }


        //
        // watch(() => props.modelValue, (value) => {
        // console.log('watch', value)
        //
        // });

        watch(() => currentPage.value, (value) => {
            console.log('currentPage', value)
            emit('update:modelValue', value);

        });


        return {
            paginatedData,
            currentPage,
            totalPages,
            getHeaders,
            setPage,
            nextPage,
            prevPage
        }
    }
}
</script>

<style scoped>
.data-table {
    width: 100%;
}
</style>


<!--const data = { page, itemsPerPage, startIndex, stopIndex, pageCount, itemsLength, nextPage, prevPage, setPage, setItemsPerPage }-->

<!--expect(pagination).toHaveBeenLastCalledWith({-->
<!--itemsLength: 2,-->
<!--itemsPerPage: 10,-->
<!--page: 1,-->
<!--pageCount: 1,-->
<!--pageStart: 0,-->
<!--pageStop: 2,-->
<!--})-->
