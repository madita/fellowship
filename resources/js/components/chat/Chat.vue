<template>
    <v-container fluid>
        <v-row>
            <div class="left-part" v-if="1">
                <!-- <perfect-scrollbar style="height: calc(100vh - 290px)"> -->
<!--                <slot name="leftpart"></slot>-->
                <!-- </perfect-scrollbar> -->

            </div>
            <div class="right-part">

                <div class="d-flex">
                    <div class="w-100">
<!--                        <perfect-scrollbar ref="" style="height: calc(100vh - 290px)">-->
                            <ChatMessages></ChatMessages>
<!--                        </perfect-scrollbar>-->

                    </div>
                    <div class="right-sidebar">
                        <v-sheet style="height: calc(100vh - 290px)">
                            <Users></Users>
                            <!--                            <ChatInfo :chatDetail="chatDetail" />-->
                        </v-sheet>
                    </div>
                </div>

            </div><v-divider />
            <!---Chat send-->
            <form class="w-100 d-flex align-center pa-4" @submit.prevent="handleMessageInput()">
                <v-text-field
                    variant="solo"
                    hide-details
                    v-model="body"
                    color="primary"
                    class="shadow-none"
                    density="compact"
                    placeholder="Type a Message"
                ></v-text-field>
                <v-btn icon variant="text" type="submit" class="text-medium-emphasis" :disabled="!body">
                    <v-icon small>mdi-send</v-icon>
                </v-btn>
            </form>

        </v-row>
<!--        <v-row>-->
<!--            <v-col :cols="8">-->
<!--                <v-card>-->
<!--                    <v-card-title>Chat Room</v-card-title>-->
<!--                    <v-card-text class="chat-messages">-->
<!--&lt;!&ndash;                        <div v-for="message in messages" :key="message.id" class="mb-2">&ndash;&gt;-->
<!--&lt;!&ndash;                            <strong>{{ message.username }}:</strong> {{ message.content }}&ndash;&gt;-->
<!--&lt;!&ndash;                        </div>&ndash;&gt;-->
<!--                        <ChatMessages></ChatMessages>-->
<!--                    </v-card-text>-->
<!--                    <v-card-actions>-->
<!--                        <form class="" @submit.prevent="handleMessageInput()">-->
<!--                        <v-text-field-->
<!--                            variant="solo"-->
<!--                            hide-details-->
<!--                            v-model="body"-->
<!--                            color="primary"-->
<!--                            class="shadow-none"-->
<!--                            density="compact"-->
<!--                            placeholder="Type a Message"-->
<!--                        ></v-text-field>-->
<!--                        <v-btn icon variant="text" type="submit" class="text-medium-emphasis" :disabled="!body">-->
<!--                            <v-icon small>mdi-send</v-icon>-->
<!--                        </v-btn>-->
<!--                        </form>-->
<!--                    </v-card-actions>-->
<!--                </v-card>-->
<!--            </v-col>-->
<!--            <v-col :cols="4">-->
<!--                <Users></Users>-->
<!--            </v-col>-->

<!--        </v-row>-->
    </v-container>
</template>

<script>
import {onMounted, ref} from 'vue';
//import moment from 'moment';
import ChatMessages from './Messages.vue';
import Users from './Users.vue';
import { useUserStore } from "@/store/userStore.js";
import { useChatStore } from '@/store/chatStore';
import {useOnlineUsersStore} from "@/store/onlineUsersStore.js";
// import useEventBus from "@/bus.js";
const { onlineUsersStore } = useOnlineUsersStore();

export default {
    components: {
        Users,
        ChatMessages
    },
    setup() {
        const body = ref('');
        const bodyBackedUp = ref('');
        const usersDrawer = ref(true);
        const userStore = useUserStore();
        // const { emit, on } = useEventBus();

        const chatStore = useChatStore();
        const onlineUsersStore = useOnlineUsersStore();



        const handleMessageInput = () => {
            console.log('addmessage')
            bodyBackedUp.value = body.value;



            send()

            // body.value = ''; // Clear input after sending
        }

        const buildTempMessage = () => {
            let tempId = Date.now();
            return {
                id: tempId,
                body: body.value,
                created_at: moment().utc(0).format('YYYY-MM-DD HH:mm:ss'),
                selfOwned: true,
                user: {
                    username: userStore.user.username
                }
            }
        }

        const send = () => {
            if (!body.value || body.value.trim() === '') {
                return;
            }
            let tempMessage = buildTempMessage();
            axios.post('/api/chat/messages', {
                body: body.value.trim()
            }).then((response) => {
                console.log(response)

                chatStore.addMessage(response.data);
                }
            ).catch(() => {
                body.value = bodyBackedUp.value;
            });
            body.value = '';
        };

        onMounted(() => {
            Echo.join('chat')
                .here((users) => {
                    console.log('usershere', users)
                    //emit('chatUsers.here', users)
                    onlineUsersStore.setUsers(users)
                })
                .joining((user) => {
                    console.log('joining', user)
                    //emit('chatUsers.joined', user)
                    onlineUsersStore.addUser(user)
                })
                .leaving((user) => {
                    console.log('leaving', user)
                    onlineUsersStore.removeUser(user)
                    //emit('chatUsers.left', user)
                })
                .listen('.message-created', (e) => {
                    //emit('message.added', e.message)
                    console.log('LISTEnnewmessage', e.message)
                    chatStore.addMessage(e.message);
                })
        })

        return {
            body,
            send,
            handleMessageInput,
            user: userStore.user
        }
    }
}
</script>

<style lang="scss">
/* Styles for chat layout */
.mainbox {
    position: relative;
    overflow: hidden;
}
left-part {
    width: 320px;
    border-right: 1px solid rgb(var(--v-theme-borderColor));
    min-height: 500px;
    transition: 0.1s ease-in;
    flex-shrink: 0;
}
.right-part {
    width: 100%;
    min-height: 500px;
    position: relative;
}
.rightpartHeight {
    height: 530px;
}
.badg-dotDetail {
    left: -9px;
    position: relative;
    bottom: -10px;
}
.right-sidebar {
    width: 320px;
    border-left: 1px solid rgb(var(--v-theme-borderColor));
    transition: 0.1s ease-in;
    flex-shrink: 0;
}

.shadow-none .v-field--no-label {
    --v-field-padding-top: -7px;
}
@media (max-width: 960px) {
    .right-sidebar {
        position: absolute;
        right: -320px;
        &.showLeftPart {
            right: 0;
            z-index: 2;
            box-shadow: 2px 1px 20px rgba(0, 0, 0, 0.1);
        }
    }
}
/* Transition Animation */
.list-enter-active {
    transition: all 0.3s;
}
.list-move {
    transition: transform 0.3s;
}
.list-enter, .list-leave-to {
    opacity: 0;
    transform: translateX(-10px);
}
/* Channel page style */
.channel-page {
    position: absolute;
    top: 65px;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    .messages {
        flex-grow: 1;
        margin-bottom: 68px;
        overflow-y: scroll;
        min-height: 0;
    }
    .input-box {
        position: fixed;
        bottom: 12px;
        width: 100%;
    }
}


.chat-messages {
    max-height: 500px;  /* You can adjust this value based on your preference */
    overflow-y: auto;
}
</style>
