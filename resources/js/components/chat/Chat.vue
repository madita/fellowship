<template>
    <div class="d-flex flex-grow-1 flex-row mt-2">

        <v-card class="flex-grow-1">
            <!-- channel toolbar -->
            <v-app-bar flat height="64">
                <v-app-bar-nav-icon class="hidden-lg-and-up" @click="$emit('toggle-menu')"></v-app-bar-nav-icon>
                <div class="title font-weight-bold"># {{ $route.params.id }}</div>

                <v-spacer></v-spacer>

                <v-btn class="mx-1" icon @click.stop="usersDrawer = !usersDrawer">
                    <v-icon>mdi-account-group-outline</v-icon>
                </v-btn>
            </v-app-bar>

            <v-divider></v-divider>

            <div class="channel-page">
                <ChatMessages></ChatMessages>

                <div class="input-box pa-2">
                    <div class="d-flex position-relative">
                        <v-text-field
                            ref="input"
                            dense
                            outlined
                            maxlength="150"
                            :placeholder="`${$t('chat.message')} #test`"
                            class="font-weight-bold position-relative"
                            hide-details
                            v-model="body"
                            @keydown="handleMessageInput"
                        >
                            <template v-slot:append>
                                <!--                    <emoji-picker @insert="insertEmoji"></emoji-picker>-->
                            </template>
                        </v-text-field>
                        <v-btn
                            fab
                            small
                            class="mx-1 primary"
                            :disabled="!body"
                            @click="handleMessageInput"
                        >
                            <v-icon small>mdi-send</v-icon>
                        </v-btn>
                    </div>
                </div>
            </div>
            <Users></Users>
            <!--        <form action="#" class="chat__form">-->
            <!--            <textarea-->
            <!--                    id="body"-->
            <!--                    cols="30"-->
            <!--                    rows="4"-->
            <!--                    class="chat__form-input"-->
            <!--                    v-model="body"-->
            <!--                    @keydown="handleMessageInput"-->
            <!--            ></textarea>-->
            <!--            <span class="chat__form-helptext">Hit return to send or Ctrl + Enter for a new line</span>-->
            <!--        </form>-->
        </v-card>
    </div>
</template>

<script>
import Bus from '../../bus'
import moment from 'moment'
import ChatMessages from './Messages'
import Users from './Users';
import {mapGetters} from "vuex";

export default {
    components: {
        Users,
        ChatMessages
    },
    data() {
        return {
            usersDrawer: true,
            drawer: null,
            body: null,
            bodyBackedUp: null,
            channels: ['general', 'production', 'qa', 'staging', 'random'],
            showCreateDialog: false,
            isLoadingAdd: false,
            newChannel: ''
        }
    },
    methods: {
        addChannel() {
            if (!this.newChannel) {
                this.$refs.channel.focus()

                return
            }

            this.isLoadingAdd = true

            setTimeout(() => {
                this.isLoadingAdd = false
                this.channels.push(this.newChannel)
                this.showCreateDialog = false
                // this.$router.push(`/apps/chat/channel/${this.newChannel}`)
                this.newChannel = ''
            }, 300)
        },
        handleMessageInput(e) {
            this.bodyBackedUp = this.body

            if (e.keyCode === 13 && !e.shiftKey) {
                e.preventDefault();
                this.send();
            }
        },
        buildTempMessage() {
            let tempId = Date.now();

            return {
                id: tempId,
                body: this.body,
                created_at: moment().utc(0).format('YYYY-MM-DD HH:mm:ss'),
                selfOwned: true,
                user: {
                    username: this.user.username
                }

            }
        },
        send() {
            if (!this.body || this.body.trim() === '') {
                return
            }

            let tempMessage = this.buildTempMessage();

            Bus.$emit('message.added', tempMessage)

            axios.post('/api/chat/messages', {
                    body: this.body.trim()
                }
            ).catch(() => {
                this.body = this.bodyBackedUp
                Bus.$emit('message.removed', tempMessage)
            });

            this.body = null
        }
    },
    computed: {
        ...mapGetters({
            user: 'auth/user',
        })
    },
    created() {
        Echo.join('chat')
            .here((users) => {
                Bus.$emit('chatUsers.here', users)
            })
            .joining((user) => {
                console.log('joining', user)
                Bus.$emit('chatUsers.joined', user)
            })
            .leaving((user) => {
                console.log('leaving', user)
                Bus.$emit('chatUsers.left', user)
            })
            .listen('Chat.MessageCreated', (e) => {
                Bus.$emit('message.added', e.message)
            })
    }
}
</script>

<style lang="scss" scoped>
// List Transition Animation
.list-enter-active {
    transition: all 0.3s;
}

.list-move {
    transition: transform 0.3s;
}

.list-enter,
.list-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}

// -- End List Transition Animation

.channel-page {
    position: absolute;
    top: 65px;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    //background: url("/images/chat/chat-bg-2.png");

    .messages {
        flex-grow: 1;
        margin-bottom: 68px;
        overflow-y: scroll;
        -webkit-overflow-scrolling: touch;
        min-height: 0;
    }

    .input-box {
        position: fixed;
        bottom: 12px;
        width: 100%;
    }

    .messages {
        padding-bottom: 0;
    }

    .input-box {
        position: absolute;
        bottom: 12px;
    }
}

.theme--dark {
    .channel-page {
        background: none;
    }
}
</style>
