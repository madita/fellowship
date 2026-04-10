import { mount } from '@vue/test-utils'
import { nextTick, defineComponent } from 'vue'
import Conversation from '@/components/conversation/Conversation.vue'

vi.mock('axios', () => ({
  default: {
    get: vi.fn(() => Promise.resolve({ data: { messages: [
      { id: 2, body: 'Second', user_id: 10, user: { id: 10, username: 'u2' }, self_owned: false, created_at_human: '1m ago' },
      { id: 1, body: 'First', user_id: 9, user: { id: 9, username: 'u1' }, self_owned: true, created_at_human: '2m ago' },
    ] } }))
  }
}))

// Mock store
const addMessageMock = vi.fn()
const setMessagesMock = vi.fn()

vi.mock('@/store/conversationStore', () => ({
  useConversationStore: () => ({
    currentConversation: { id: 123, uuid: 'uuid-123' },
    loadingConversation: false,
    messages: [],
    fetchConversation: vi.fn(async (id) => {
      // Simulate what the real fetchConversation does - it reverses and calls setMessages
      const mockMessages = [
        { id: 2, body: 'Second', user_id: 10, user: { id: 10, username: 'u2' }, self_owned: false, created_at_human: '1m ago' },
        { id: 1, body: 'First', user_id: 9, user: { id: 9, username: 'u1' }, self_owned: true, created_at_human: '2m ago' },
      ]
      const reversedMessages = [...mockMessages].reverse()
      setMessagesMock(reversedMessages)
      return { id: 123, uuid: 'uuid-123', messages: mockMessages }
    }),
    addMessage: addMessageMock,
    setMessages: setMessagesMock,
    updateConversationUsers: vi.fn(),
  })
}))

// Provide a fake Echo client that captures listeners so we can trigger them
function installFakeEcho() {
  const listeners = {}
  global.window = global.window || {}
  window.Echo = {
    leave: vi.fn(),
    private: vi.fn(() => ({
      subscribed: function(cb) { cb && cb({}) ; return this },
      listenToAll: function() { return this },
      listen: function(event, handler) {
        listeners[event] = handler
        return this
      }
    }))
  }
  return listeners
}

describe('Conversation.vue', () => {
  it('loads and renders messages on id change', async () => {
    installFakeEcho()
    const wrapper = mount(Conversation, {
      props: { id: 'uuid-123' },
      global: {
        stubs: ['v-container','v-card','v-card-title','v-card-text','v-icon','v-chip','v-menu','v-btn','v-progress-circular','v-divider','v-spacer','v-avatar','v-img','v-card','v-card-text','v-col','v-row','v-chip','v-card-actions'],
        components: {
          UserAvatar: defineComponent({ props: ['user'], template: '<div class="avatar"></div>' }),
          ConversationAddUserForm: defineComponent({ template: '<div />' }),
          ConversationReplyForm: defineComponent({ template: '<div />' }),
        }
      }
    })

    await nextTick()

    // setMessages should be called twice: first with [] to clear, then with reversed messages
    expect(setMessagesMock).toHaveBeenCalledTimes(2)
    // First call clears the messages
    expect(setMessagesMock.mock.calls[0][0]).toEqual([])
    // Second call sets the reversed messages (oldest->newest)
    const passed = setMessagesMock.mock.calls[1][0]
    expect(passed.map(m => m.id)).toEqual([1, 2])

    // Renders empty state or container without throwing
    expect(wrapper.exists()).toBe(true)
  })

  it('appends new message when Echo MessageAdded event fires', async () => {
    const listeners = installFakeEcho()

    mount(Conversation, {
      props: { id: 'uuid-123' },
      global: {
        stubs: ['v-container','v-card','v-card-title','v-card-text','v-icon','v-chip','v-menu','v-btn','v-progress-circular','v-divider','v-spacer','v-avatar','v-img','v-card','v-card-text','v-col','v-row','v-chip','v-card-actions'],
        components: {
          UserAvatar: defineComponent({ props: ['user'], template: '<div class="avatar"></div>' }),
          ConversationAddUserForm: defineComponent({ template: '<div />' }),
          ConversationReplyForm: defineComponent({ template: '<div />' }),
        }
      }
    })

    await nextTick()

    // Simulate broadcast event
    expect(typeof listeners['Conversations\\MessageAdded']).toBe('function')
    listeners['Conversations\\MessageAdded']({ message: { id: 3, body: 'Hi', user_id: 10, user: { id: 10 }, self_owned: false, created_at_human: 'now' } })

    expect(addMessageMock).toHaveBeenCalledWith(expect.objectContaining({ id: 3, body: 'Hi' }))
  })
})
