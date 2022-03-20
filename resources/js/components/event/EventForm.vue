<template>
    <div class="flex-grow-1">
<v-container>
    <v-row>
        <v-col cols="3">
            <event-date-picker
                v-if="event.title.length > 0"
                :event="event"
                @change="updateEventPickerData"></event-date-picker>

        </v-col>
        <v-col cols="9">
            <v-text-field
                label="Title"
                v-model="event.title"
            ></v-text-field>

            <simple-editor v-model="event.description" :value="event.bodescriptiondy" id="text-body"
                           name="content"></simple-editor>
        </v-col>
    </v-row>


    <!--      <simple-editor  v-model="page.body" :value="page.body" id="text-body" name="content"></simple-editor>-->

    <v-btn @click="save">{{ form }}</v-btn>
</v-container>



    </div>
</template>

<script>
import SimpleEditor from '../../components/common/SimpleEditor'
import EventDatePicker from "./EventDatePicker";

export default {
    components: {
        EventDatePicker,
        SimpleEditor
    },
    data() {
        return {
            loading: true,
            event: {title: "", body: ""},
            endpoint: '/api/events',
            form: "Create",
            id: null,
            message: ""
        }
    },

    methods: {
        getEvent() {
            this.loading = true
            return axios.get(`/api/events/${this.id}/edit`).then((response) => {
                this.event = response.data.data.event

                this.loading = false
            });
        },
        save() {
            if (this.id) {
                this.update()
            } else {
                this.store()
            }
        },
        update() {
            axios.patch(`${this.endpoint}/${this.id}`, this.page).then(() => {
                this.message = "Page updated"
            }).catch((error) => {
                if (error.response.status === 422) {
                    this.editing.errors = error.response.data
                }
            })
        },
        store() {
            axios.post(`${this.endpoint}`, this.event).then(() => {
                this.page = {title: "", body: ""};
                this.message = "Page saved ..link"
            }).catch((error) => {
                if (error.response.status === 422) {
                    // this.creating.errors = error.response.data
                    this.editing.errors = error.response.data
                }
            })
        },
        updateEventPickerData(eventPickerData) {
            if (eventPickerData.dates !== undefined) {
                this.event.dates = eventPickerData.dates
            }
            if (eventPickerData.startTime !== undefined) {
                this.event.startTime = eventPickerData.startTime
            }
            if (eventPickerData.endTime !== undefined) {
                this.event.endTime = eventPickerData.endTime
            }
        }
    },

    created() {
        // if(this.$route.params.form) {
        //     this.form = this.$route.params.form;
        // }

        if (this.$route.params.id) {
            this.form = "Update"
            this.id = this.$route.params.id;
            this.getEvent();
        }

    }
}
</script>
