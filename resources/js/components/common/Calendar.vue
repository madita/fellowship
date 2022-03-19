<template>
    <div class="flex-grow-1">
        <v-sheet height="64">
            <v-toolbar
                flat
            >
                <v-btn
                    outlined
                    class="mr-4"
                    color="grey darken-2"
                    @click="setToday"
                >
                    Today
                </v-btn>
                <v-btn
                    fab
                    text
                    small
                    color="grey darken-2"
                    @click="prev"
                >
                    <v-icon small>
                        mdi-chevron-left
                    </v-icon>
                </v-btn>
                <v-btn
                    fab
                    text
                    small
                    color="grey darken-2"
                    @click="next"
                >
                    <v-icon small>
                        mdi-chevron-right
                    </v-icon>
                </v-btn>
                <v-toolbar-title v-if="$refs.calendar">
                    {{ $refs.calendar.title }}
                </v-toolbar-title>
                <v-spacer></v-spacer>
                <v-menu
                    bottom
                    right
                >
                    <template v-slot:activator="{ on, attrs }">
                        <v-btn
                            outlined
                            color="grey darken-2"
                            v-bind="attrs"
                            v-on="on"
                        >
                            <span>{{ typeToLabel[type] }}</span>
                            <v-icon right>
                                mdi-chevron-down
                            </v-icon>
                        </v-btn>
                    </template>
                    <v-list>
                        <v-list-item @click="changeType('day')">
                            <v-list-item-title>Day</v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="changeType('week')">
                            <v-list-item-title>Week</v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="changeType('month')">
                            <v-list-item-title>Month</v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="changeType('4day')">
                            <v-list-item-title>4 days</v-list-item-title>
                        </v-list-item>
                        <v-list-item @click="listView()">
                            <v-list-item-title>List</v-list-item-title>
                        </v-list-item>
                    </v-list>
                </v-menu>
            </v-toolbar>
        </v-sheet>
        <v-sheet height="600">
            <v-list-item-group
                color="primary"
                v-if="view === 'list'">
                <v-list-item v-for="event in this.events" :key="`event-${event.id}`" two-line
                             @click="$router.push({name: 'event-show', params: { id: event.id },})">
                    <v-list-item-content>
                        <v-list-item-title>{{ event.name }}</v-list-item-title>
                        <v-list-item-subtitle>{{ event.start }} - {{ event.end }}</v-list-item-subtitle>
                    </v-list-item-content>
                </v-list-item>
            </v-list-item-group>
            <v-calendar
                v-show="view !== 'list'"
                ref="calendar"
                v-model="focus"
                color="primary"
                :weekdays="weekday"
                :events="getEvents"
                locale="de"
                :event-color="getEventColor"
                :type="type"
                @click:event="showEvent"
                @click:more="viewDay"
                @click:date="viewDay"
            ></v-calendar>
            <v-menu
                v-model="selectedOpen"
                :close-on-content-click="false"
                :activator="selectedElement"
                offset-x
            >
                <v-card
                    color="grey lighten-4"
                    min-width="350px"
                    flat
                >
                    <v-toolbar
                        :color="selectedEvent.color"
                        dark
                    >
                        <v-toolbar-title v-html="selectedEvent.name"></v-toolbar-title>
                        <v-spacer></v-spacer>

                    </v-toolbar>
                    <v-card-text>
                        <v-row>
                            <v-col cols="8"><span v-html="selectedEvent.description"></span></v-col>
                            <v-col cols="4"><span>{{
                                    selectedEvent.startDate | formatDate('DD.MM.YYYY')
                                }} at {{ selectedEvent.startTime }}
                                </span>
                                <span>
                                {{
                                        selectedEvent.endDate | formatDate('DD.MM.YYYY')
                                    }} at {{ selectedEvent.endTime }}</span>
                            </v-col>

                        </v-row>

                    </v-card-text>
                    <v-card-actions>
                        <v-btn
                            text
                            color="secondary"
                            @click="selectedOpen = false"
                        >
                            Cancel
                        </v-btn>
                        <v-btn
                            text
                            color="secondary"
                            @click="$router.push({name: 'event-show', params: { id: selectedEvent.id },})"
                        >
                            Register
                        </v-btn>
                    </v-card-actions>
                </v-card>
            </v-menu>
        </v-sheet>
    </div>
</template>

<script>
export default {
    props: ['data'],
    data: () => ({
        focus: '',
        view: 'calendar',
        weekday: [1, 2, 3, 4, 5, 6, 0],
        type: 'month',
        typeToLabel: {
            month: 'Month',
            week: 'Week',
            day: 'Day',
            '4day': '4 Days',
            category: 'List'
        },
        selectedEvent: {},
        selectedElement: null,
        selectedOpen: false,
        events: [],
        colors: ['blue', 'indigo', 'deep-purple', 'cyan', 'green', 'orange', 'grey darken-1'],
        names: ['Meeting', 'Holiday', 'PTO', 'Travel', 'Event', 'Birthday', 'Conference', 'Party'],
    }),
    mounted() {
        this.events = this.data.map(item => {
            const start = this.getStart(item)
            const end = this.getEnd(item)
            return {...item, name: item.title, start: start, end: end, color: "blue"}
        });
        this.$refs.calendar.checkChange()
    },
    methods: {
        viewDay({date}) {
            this.focus = date
            this.type = 'day'
        },
        getEventColor(event) {
            return event.color
        },
        setToday() {
            this.focus = ''
        },
        prev() {
            this.$refs.calendar.prev()
        },
        next() {
            this.$refs.calendar.next()
        },
        listView() {
            this.type = 'category';
            this.view = 'list';
        },
        changeType(type) {
            this.type = type;
            this.view = 'calendar';
        },
        showEvent({nativeEvent, event}) {
            const open = () => {
                this.selectedEvent = event
                this.selectedElement = nativeEvent.target
                setTimeout(() => {
                    this.selectedOpen = true
                }, 10)
            }

            if (this.selectedOpen) {
                this.selectedOpen = false
                setTimeout(open, 10)
            } else {
                open()
            }

            nativeEvent.stopPropagation()
        },
        rnd(a, b) {
            return Math.floor((b - a + 1) * Math.random()) + a
        },
        getStart(event) {
            let start = "0000-00-00"
            if (event.startDate !== null) {
                start = event.startDate
            }
            if (event.startTime !== null) {
                start += " " + event.startTime
            }

            return start;
        },
        getEnd(event) {
            let end = "0000-00-00"
            if (event.endDate !== null) {
                end = event.endDate
            }
            if (event.endTime !== null) {
                end += " " + event.endTime
            }

            return end;
        }
    },
    computed: {
        getEvents() {
            return this.data.map(item => {
                const start = this.getStart(item)
                const end = this.getEnd(item)
                return {...item, name: item.title, start: start, end: end, color: "blue"}
            });
        }
    }
}
</script>
