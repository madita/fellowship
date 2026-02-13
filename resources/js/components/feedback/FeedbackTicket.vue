<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12" md="9">
        <!-- Loading State -->
        <v-card v-if="loading" class="pa-8 text-center">
          <v-progress-circular indeterminate color="primary" />
        </v-card>

        <!-- Ticket Detail -->
        <div v-else-if="ticket">
          <!-- Header -->
          <v-card class="mb-4">
            <v-card-text>
              <div class="d-flex align-center mb-3">
                <v-chip
                  :color="getTypeColor(ticket.ticket_type.slug)"
                  class="mr-2"
                  size="small"
                >
                  <v-icon left size="small">{{ ticket.ticket_type.icon }}</v-icon>
                  {{ ticket.ticket_type.name }}
                </v-chip>
                <v-chip
                  :color="getStatusColor(ticket.bga_status)"
                  size="small"
                  label
                >
                  {{ ticket.bga_status_label }}
                </v-chip>
                <v-spacer />
                <span class="text-caption text-medium-emphasis">
                  #{{ ticket.id }}
                </span>
              </div>

              <h1 class="text-h4 mb-4">{{ ticket.title }}</h1>

              <div class="d-flex align-center text-body-2 text-medium-emphasis">
                <v-avatar size="24" class="mr-2">
                  <v-icon>mdi-account-circle</v-icon>
                </v-avatar>
                <span>
                  Created by <strong>{{ ticket.creator.name }}</strong> • 
                  {{ formatDate(ticket.created_at) }}
                </span>
                <v-divider vertical class="mx-3" />
                <v-icon size="small" class="mr-1">mdi-comment</v-icon>
                <span class="mr-3">{{ ticket.comments_count }}</span>
                <v-icon size="small" class="mr-1">mdi-eye</v-icon>
                <span>{{ ticket.watchers_count }}</span>
              </div>

              <!-- Tags -->
              <div v-if="ticket.tags.length" class="mt-3">
                <v-chip
                  v-for="tag in ticket.tags"
                  :key="tag.id"
                  :color="tag.color"
                  size="small"
                  class="mr-1"
                >
                  {{ tag.name }}
                </v-chip>
              </div>

              <!-- Duplicate Notice -->
              <v-alert
                v-if="ticket.duplicate_of"
                type="warning"
                variant="tonal"
                class="mt-3"
              >
                This is a duplicate of 
                <router-link :to="{ name: 'feedback.ticket', params: { id: ticket.duplicate_of.id } }">
                  #{{ ticket.duplicate_of.id }}: {{ ticket.duplicate_of.title }}
                </router-link>
              </v-alert>

              <!-- Fixed Version -->
              <v-alert
                v-if="ticket.fixed_in_version"
                type="success"
                variant="tonal"
                class="mt-3"
              >
                <v-icon left>mdi-check-circle</v-icon>
                Fixed in version {{ ticket.fixed_in_version }}
              </v-alert>
            </v-card-text>
          </v-card>

          <!-- Description -->
          <v-card class="mb-4">
            <v-card-text>
              <div class="text-body-1" v-html="formatMarkdown(ticket.description)" />
            </v-card-text>
          </v-card>

          <!-- Comments -->
          <v-card>
            <v-card-title>
              <v-icon left>mdi-comment-multiple</v-icon>
              Comments ({{ ticket.comments.length }})
            </v-card-title>

            <v-divider />

            <v-card-text>
              <!-- Comment List -->
              <div
                v-for="(comment, index) in ticket.comments"
                :key="comment.id"
                :class="{ 'mt-4': index > 0 }"
              >
                <div class="d-flex align-start">
                  <v-avatar size="40" class="mr-3">
                    <v-icon>mdi-account-circle</v-icon>
                  </v-avatar>

                  <div class="flex-grow-1">
                    <div class="d-flex align-center mb-1">
                      <strong>{{ comment.user.name }}</strong>
                      <v-chip
                        v-if="comment.is_official"
                        color="primary"
                        size="x-small"
                        class="ml-2"
                      >
                        <v-icon left size="x-small">mdi-shield-check</v-icon>
                        Developer
                      </v-chip>
                      <span class="text-caption text-medium-emphasis ml-2">
                        {{ formatDate(comment.created_at) }}
                      </span>
                    </div>
                    <div class="text-body-2" v-html="formatMarkdown(comment.comment)" />
                  </div>
                </div>
                <v-divider v-if="index < ticket.comments.length - 1" class="mt-4" />
              </div>

              <!-- Add Comment Form -->
              <div v-if="$store.state.auth.user" class="mt-6">
                <v-divider class="mb-4" />
                <v-textarea
                  v-model="newComment"
                  label="Add a comment"
                  outlined
                  rows="4"
                  hide-details
                />
                <div class="d-flex justify-end mt-2">
                  <v-btn
                    color="primary"
                    :loading="submittingComment"
                    :disabled="!newComment.trim()"
                    @click="submitComment"
                  >
                    <v-icon left>mdi-send</v-icon>
                    Post Comment
                  </v-btn>
                </div>
              </div>

              <!-- Login Prompt -->
              <div v-else class="mt-6 text-center">
                <v-divider class="mb-4" />
                <p class="text-body-2 text-medium-emphasis">
                  Please log in to add a comment
                </p>
              </div>
            </v-card-text>
          </v-card>
        </div>
      </v-col>

      <!-- Sidebar -->
      <v-col cols="12" md="3">
        <v-card v-if="ticket" sticky>
          <v-card-text>
            <!-- Vote Button -->
            <v-btn
              block
              :color="ticket.user_has_voted ? 'primary' : 'default'"
              :variant="ticket.user_has_voted ? 'elevated' : 'outlined'"
              class="mb-3"
              @click="toggleVote"
              :disabled="!$store.state.auth.user"
            >
              <v-icon left>mdi-arrow-up-bold</v-icon>
              {{ ticket.user_has_voted ? 'Voted' : 'Vote' }} ({{ ticket.votes_count }})
            </v-btn>

            <!-- Watch Button -->
            <v-btn
              block
              :color="ticket.user_is_watching ? 'secondary' : 'default'"
              :variant="ticket.user_is_watching ? 'elevated' : 'outlined'"
              @click="toggleWatch"
              :disabled="!$store.state.auth.user"
            >
              <v-icon left>{{ ticket.user_is_watching ? 'mdi-eye-check' : 'mdi-eye' }}</v-icon>
              {{ ticket.user_is_watching ? 'Watching' : 'Watch' }}
            </v-btn>

            <v-divider class="my-4" />

            <!-- Info -->
            <div class="text-body-2">
              <div class="mb-2">
                <strong>Priority:</strong><br>
                <v-chip size="x-small">{{ ticket.priority_label }}</v-chip>
              </div>

              <div v-if="ticket.assignee" class="mb-2">
                <strong>Assigned to:</strong><br>
                {{ ticket.assignee.name }}
              </div>

              <div v-if="ticket.duplicates.length" class="mb-2">
                <strong>Duplicates:</strong><br>
                <div
                  v-for="dup in ticket.duplicates"
                  :key="dup.id"
                  class="text-caption"
                >
                  <router-link :to="{ name: 'feedback.ticket', params: { id: dup.id } }">
                    #{{ dup.id }}
                  </router-link>
                </div>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script>
import axios from 'axios';

export default {
  name: 'FeedbackTicket',
  data() {
    return {
      loading: false,
      ticket: null,
      newComment: '',
      submittingComment: false,
    };
  },
  mounted() {
    this.fetchTicket();
  },
  methods: {
    async fetchTicket() {
      this.loading = true;
      try {
        const { data } = await axios.get(`/api/feedback/tickets/${this.$route.params.id}`);
        this.ticket = data;
      } catch (error) {
        console.error('Error fetching ticket:', error);
        this.$router.push({ name: 'feedback.bugs' });
      } finally {
        this.loading = false;
      }
    },
    async toggleVote() {
      if (!this.$store.state.auth.user) return;

      try {
        const { data } = await axios.post(`/api/feedback/tickets/${this.ticket.id}/vote`);
        this.ticket.user_has_voted = data.voted;
        this.ticket.votes_count = data.votes_count;
      } catch (error) {
        console.error('Error toggling vote:', error);
      }
    },
    async toggleWatch() {
      if (!this.$store.state.auth.user) return;

      try {
        const { data } = await axios.post(`/api/feedback/tickets/${this.ticket.id}/watch`);
        this.ticket.user_is_watching = data.watching;
        this.ticket.watchers_count = data.watchers_count;
      } catch (error) {
        console.error('Error toggling watch:', error);
      }
    },
    async submitComment() {
      if (!this.newComment.trim()) return;

      this.submittingComment = true;
      try {
        const { data } = await axios.post(
          `/api/feedback/tickets/${this.ticket.id}/comments`,
          { comment: this.newComment }
        );
        this.ticket.comments.unshift(data.comment);
        this.ticket.comments_count++;
        this.newComment = '';

        // Auto-watch when commenting
        if (!this.ticket.user_is_watching) {
          this.ticket.user_is_watching = true;
          this.ticket.watchers_count++;
        }
      } catch (error) {
        console.error('Error submitting comment:', error);
        alert('Failed to post comment. Please try again.');
      } finally {
        this.submittingComment = false;
      }
    },
    getTypeColor(slug) {
      const colors = {
        bug: 'red',
        feature: 'orange',
        support: 'blue',
      };
      return colors[slug] || 'grey';
    },
    getStatusColor(status) {
      const colors = {
        reported: 'blue-grey',
        confirmed: 'orange',
        investigating: 'purple',
        planned: 'blue',
        in_progress: 'indigo',
        completed: 'green',
        wontfix: 'red',
        duplicate: 'grey',
      };
      return colors[status] || 'grey';
    },
    formatDate(date) {
      return new Date(date).toLocaleDateString();
    },
    formatMarkdown(text) {
      // Simple markdown-like formatting (for production, use a proper markdown library)
      return text
        .replace(/\n/g, '<br>')
        .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.*?)\*/g, '<em>$1</em>');
    },
  },
};
</script>

<style scoped>
.flex-grow-1 {
  flex-grow: 1;
}
</style>
