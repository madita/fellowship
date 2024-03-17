<template>
    <div>
<!--        <v-row-->
<!--            justify="space-around"-->
<!--            align="center"-->
<!--        >-->
        <v-row>
            <!--            <v-col>-->
            <v-date-picker
                first-day-of-week="1"
                v-model="dates"
                :locale="currentLocale"
                @change="changeDates"
                range
            ></v-date-picker>
        </v-row>
            <v-row>

            <v-text-field
                v-model="dateRangeText"
                label="Date range"
                prepend-icon="mdi-calendar"
                readonly
            ></v-text-field>
            <!--                <input v-for="date in dates" name="dates[]" type="hidden" :value="date">-->

            <!--            </v-col>-->

            <!--            <v-col>-->
                </v-row><v-row>

            <v-menu
                ref="startTimeMenu"
                v-model="startTimeMenu"
                :close-on-content-click="false"
                :nudge-right="40"
                :return-value.sync="startTime"
                transition="scale-transition"
                offset-y
                max-width="290px"
                min-width="290px"
            >
                <template v-slot:activator="{ probs }">
                    <v-text-field
                        v-model="startTime"
                        label="Starttime"
                        prepend-icon="mdi-clock-time-four-outline"
                        readonly
                        v-bind="probs"
                    ></v-text-field>
                </template>
                <v-time-picker
                    v-if="startTimeMenu"
                    locale="currentLocale"
                    format="24hr"
                    v-model="startTime"
                    full-width
                    @click:minute="$refs.startTimeMenu.save(startTime)"
                    @change="changeStartTime"
                ></v-time-picker>
            </v-menu>
            <!--                <input name="startTime" type="hidden" :value="startTime">-->

    </v-row>
        <v-row>
            <v-menu
                ref="endTimeMenu"
                v-model="endTimeMenu"
                :close-on-content-click="false"
                :nudge-right="40"
                :return-value.sync="endTime"
                transition="scale-transition"
                offset-y
                max-width="290px"
                min-width="290px"
            >
                <template v-slot:activator="{ probs }">
                    <v-text-field
                        v-model="endTime"
                        label="Endtime"
                        prepend-icon="mdi-clock-time-four-outline"
                        readonly
                        v-bind="probs"
                    ></v-text-field>
                </template>
                <v-time-picker
                    v-if="endTimeMenu"
                    locale="currentLocale"
                    format="24hr"
                    v-model="endTime"
                    full-width
                    @click:minute="$refs.endTimeMenu.save(endTime)"
                    @change="changeEndTime"
                ></v-time-picker>
            </v-menu>
            <!--                <input name="endTime" type="hidden" :value="endTime">-->

            <!--            </v-col>-->
            <!--        </v-row>-->
            <!--        <v-row>-->
            <!--            <v-col-->
            <!--                cols="12"-->
            <!--                sm="6"-->
            <!--            >-->

            <!--                model: {{ dates }}-->
            <!--            </v-col>-->
        </v-row>
    </div>
</template>

<script>
export default {
    props: {
        event: {},
        startDateProp: {
            type: String,
            default: ""
        },
        endDateProp: {
            type: String,
            default: ""
        },
        startTimeProp: {
            type: String,
            default: null
        },
        endTimeProp: {
            type: String,
            default: null
        }
    },
    data() {
        return {
            startTimeMenu: false,
            endTimeMenu: false,
            dates: [],
            startDate: null,
            endDate: null,
            startTime: null,
            endTime: null,
        }
    },
    computed: {
        dateRangeText() {
            return this.dates.join(' ~ ')
        },
        currentLocale() {
            return this.$vuetify.lang.current;
        },
    },
    methods: {
        changeDates() {
            let dates = this.dates;
            this.$emit('change', {dates});
        },
        changeStartTime() {
            let startTime = this.startTime;
            this.$emit('change', {startTime});
        },
        changeEndTime() {
            let endTime = this.endTime;
            this.$emit('change', {endTime});
        }
    },
    created() {
        console.log('testpicker',this.event)

        if(this.event !== undefined) {
            this.startDate = this.event.startDate
            this.endDate = this.event.endDate
            this.startTime = this.event.startTime
            this.endTime = this.event.endTime
        }


        if (this.startDate && this.endDate) {
            this.dates.push(this.startDate)
            this.dates.push(this.endDate)
        }
    }
}
</script>
