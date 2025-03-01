<template>
    <!-- Bind modelValue to the dialog's v-model -->
    <VDialog v-model="internalModelValue" width="auto">

        <v-card
            min-width="400"
            prepend-icon="mdi-calendar"
            title="Event Details"
        >






            <!--                <v-table height="300px">-->
            <!--                    <thead>-->
            <!--                    <tr>-->
            <!--                        <th class="text-left">-->
            <!--                            Name-->
            <!--                        </th>-->
            <!--                        <th class="text-left">-->
            <!--                            Games-->
            <!--                        </th>-->
            <!--                    </tr>-->
            <!--                    </thead>-->
            <!--                    <tbody>-->
            <!--                    <tr-->
            <!--                        v-for="guest in eventGuests.going"-->
            <!--                        :key="guest.id"-->
            <!--                    >-->
            <!--                        <td><UserAvatar-->
            <!--                            :user="guest.user[0]"/> {{guest.user[0].username}}</td>-->
            <!--                        <td><v-chip  v-for="game in guest.games" :key="game.id">{{game.title}}</v-chip></td>-->
            <!--                    </tr>-->
            <!--                    </tbody>-->
            <!--                </v-table>-->

<!--            <v-text-field-->
<!--                v-model="search"-->
<!--                label="Search"-->
<!--                prepend-inner-icon="mdi-magnify"-->
<!--                variant="outlined"-->
<!--                hide-details-->
<!--                single-line-->
<!--            ></v-text-field>-->

            <v-combobox
                v-model="filterData['games']"
                :items="taxonomieItems['games']"

                item-title="title"
                item-value="id"
                label="Test"
                chips
                clearable
                multiple
                @focus ="getTerms('games', 'games')"
            ></v-combobox>

            <v-data-table
                v-model:expanded="expanded"
                :items="filteredRecords"
                :headers="headers"
                :search="search"
                expand-mode="single"
                show-expand
                item-value="id"
            >

                <!-- Custom column filters -->
                <template v-slot:top>
                    <v-row class="pa-4">
<!--                        <v-col cols="4">-->
<!--                            <v-select-->
<!--                                v-model="selectedStatus"-->
<!--                                :items="statusOptions"-->
<!--                                label="Filter by Status"-->
<!--                                variant="outlined"-->
<!--                                clearable-->
<!--                                dense-->
<!--                            />-->
<!--                        </v-col>-->


                        <!--                        <v-col cols="4">-->
                        <!--                            <v-select-->
                        <!--                                v-model="selectedRole"-->
                        <!--                                :items="roleOptions"-->
                        <!--                                label="Filter by Role"-->
                        <!--                                variant="outlined"-->
                        <!--                                clearable-->
                        <!--                                dense-->
                        <!--                            />-->
                        <!--                        </v-col>-->
                    </v-row>
                </template>

                <template v-slot:item.user="{ value }">
                    <UserAvatar
                        :user="value[0]"/> {{value[0].username}}
                </template>
                <template v-slot:item.days="{ value }">
                    <v-chip  v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail}}</v-chip>
                </template>
                <template v-slot:item.games="{ value }">
                    <v-chip  v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.breakfast="{ value }">
                    <v-chip  v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.drinks="{ value }">
                    <v-chip  v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.allergies="{ value }">
                    <v-chip  v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.meals="{ value }">
                    <v-chip  v-for="itemDetail in value" :key="itemDetail.id">{{itemDetail.title}}</v-chip>
                </template>
                <template v-slot:item.remarks="{ value }">
                    <template v-if="value.length > 0"><v-icon>mdi-checkmark</v-icon></template>
                </template>
                <template v-slot:expanded-row="{ columns, item }">
                    <tr>
                        <td :colspan="columns.length">
                           {{ item.remarks }}
                        </td>
                    </tr>
                </template>
            </v-data-table>


            <template v-slot:actions>
                <v-btn
                    class="ms-auto"
                    text="Close"
                    @click="internalModelValue = false"
                ></v-btn>
            </template>
        </v-card>
    </VDialog>
</template>

<script setup>
import {ref, computed, watch} from 'vue';
import axios from "axios";
// import taxonomie from "@/pages/admin/Taxonomie.vue";
import UserAvatar from "@/components/common/UserAvatar.vue";

// const selectedDays = ref();
// const profile = ref();
// const fields = ref();
const search = ref();
const expanded = ref();
const taxonomieItems = ref([]);
// const formData = ref([]);
const filterData = ref([]);


// Define the modelValue prop
const props = defineProps({
    modelValue: Boolean, // Highlight: Bind modelValue to control dialog visibility
    eventGuests: Object
});

// const localEvent = ref(null);
const localGuests = ref(props.eventGuests);


const emit = defineEmits(['update:modelValue']); // Highlight: Emit update for modelValue
const textField = ref('');

// Create an internal computed property for modelValue
const internalModelValue = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value), // Highlight: Emit changes
});

// function confirm() {
//     // props.resolve(true);
//     internalModelValue.value = false;
//     profileSubmit(localAnswer.value);
//
//
// }

// function cancel() {
//
//     internalModelValue.value = false;
// }

const filteredRecords = computed(() => {
    let data = localGuests.value

    console.log('data',data);

    const filteredData = data.map(({ type, ...rest }) => rest);

    console.log('filteredData',filteredData);

    // data = data.filter((row) => {
    //     return Object.keys(row).some((key) => {
    //         return String(row[key]).toLowerCase().indexOf(state.quickSearchQuery.toLowerCase()) > -1
    //     })
    // })

    /*if (state.sort.key) {
        data = _.orderBy(data, (i) => {
            let value = i[state.sort.key]

            if (!isNaN(parseFloat(value)) && isFinite(value)) {
                return parseFloat(value)
            }

            return String(i[state.sort.key]).toLowerCase()
        }, state.sort.order)
    }*/

    // console.log('data', data)

    return filteredData
})


    const headers = computed(() => {
        const header = [...new Set(localGuests.value.flatMap(obj => Object.keys(obj)))].map((item) =>
            ({title: item, key: item})
        )



            // .map((guest) =>
            //     guest.id === guestId ? {...guest, approved: action === "approve"} : guest
            // )


        header.push({ title: '', key: 'data-table-expand' })
        console.log('headers',headers)
        return header;
    });


const getTerms = async (name, taxonomy ) => {


    await axios.get(`/api/tag/terms/${taxonomy}`).then((response) => {
        // console.log('responseterms',response.data)

        // this.categories = this.parents = response.data.terms

        taxonomieItems.value[name] = response.data.terms


    }).catch((error) => {
        console.log('error', error)
        // if (error.response.status === 422) {
        //     // this.creating.errors = error.response.data
        //     this.editing.errors = error.response.data
        // }
    });
}


watch(
    () => props.eventGuests,
    (newGuests) => {
        // console.log('profilewatch', profileId)
        localGuests.value = newGuests ? JSON.parse(JSON.stringify(newGuests)) : null;
    },
    {immediate: true} // Trigger immediately to initialize localEvent
);



</script>
