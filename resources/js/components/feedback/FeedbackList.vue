<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <!-- Header -->
        <div class="d-flex justify-space-between align-center mb-4">
          <div>
            <h1 class="text-h4 mb-2">{{ title }}</h1>
            <p class="text-subtitle-1 text-medium-emphasis">{{ subtitle }}</p>
          </div>
          <v-btn
            v-if="$store.state.auth.user"
            color="primary"
            size="large"
            @click="showComposer = true"
          >
            <v-icon left>mdi-plus</v-icon>
            {{ type === 'bug' ? 'Report Bug' : 'Request Feature' }}
          </v-btn>
        </div>

        <!-- Filters -->
        <v-card class="mb-4">
          <v-card-text>
            <v-row dense>
              <v-col cols="12" md="4">
                <v-select
                  v-model="filters.status"
                  :items="statusOptions"
                  label="Status"
                  dense
                  clearable
                  hide-details
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="filters.tag"
                  :items="tagOptions"
                  label="Tag"
                  dense
                  clearable
                  hide-details
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="filters.sort"
                  :items="sortOptions"
                  label="Sort by"
                  dense
                  hide-details
                />
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Tickets List -->
        <v-card v-if="loading" class="text-center pa-8">
          <v-progress-circular indeterminate color="primary" />
        </v-card>

        <div v-else>
          <v-card
            v-for="ticket in tickets"
            :key="ticket.id"
            class="mb-3 hover-elevation"
            @click="goToTicket(ticket.id)"
          >
            <v-card-text>
              <v-row dense align="center">
                <!-- Vote Button -->
                <v-col cols="auto">
                  <div class="text-center" @click.stop>
                    <v-btn
                      icon
                      variant="text"
                      :color="ticket.user_has_voted ? 'primary' : 'default'"
                      @click="toggleVote(ticket)"
                      :disabled="!$store.state.auth.user"
                    >
                      <v-icon>mdi-arrow-up-bold</v-icon>
                    </v-btn>
                    <div class="text-subtitle-2 font-weight-bold">
                      {{ ticket.votes_count }}
                    </div>
                  </div>
                </v-col>

                <!-- Ticket Info -->
                <v-col>
                  <div class="d-flex align-center mb-2">
                    <h3 class="text-h6 mr-2">{{ ticket.title }}</h3>
                    <v-chip
                      :color="getStatusColor(ticket.bga_status)"
                      size="small"
                      label
                    >
                      {{ ticket.bga_status_label }}
                    </v-chip>
                  </div>
                  
                  <div class="d-flex align-center text-caption text-medium-emphasis">
                    <span>
                      Created by {{ ticket.creator.name }} • 
                      {{ formatDate(ticket.created_at) }}
                    </span>
                    <v-divider vertical class="mx-2" />
                    <v-icon size="small" class="mr-1">mdi-comment</v-icon>
                    <span class="mr-3">{{ ticket.comments_count }}</span>
                    <v-icon size="small" class="mr-1">mdi-eye</v-icon>
                    <span>{{ ticket.watchers_count }}</span>
                  </div>

                  <!-- Tags -->
                  <div v-if="ticket.tags.length" class="mt-2">
                    <v-chip
                      v-for="tag in ticket.tags"
                      :key="tag.id"
                      :color="tag.color"
                      size="x-small"
                      class="mr-1"
                    >
                      {{ tag.name }}
                    </v-chip>
                  </div>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="text-center mt-4">
            <v-pagination
              v-model="pagination.current_page"
              :length="pagination.last_page"
              @update:model-value="fetchTickets"
            />
          </div>

          <!-- Empty State -->
          <v-card v-if="!tickets.length" class="text-center pa-8">
            <v-icon size="64" color="grey-lighten-1">
              {{ type === 'bug' ? 'mdi-bug' : 'mdi-lightbulb' }}
            </v-icon>
            <p class="text-h6 mt-4">No {{ type === 'bug' ? 'bugs' : 'features' }} found</p>
          </v-card>
        </div>
      </v-col>
    </v-row>

    <!-- Composer Dialog -->
    <feedback-composer
      v-model="showComposer"
      :type="type"
      @submitted="onTicketSubmitted"
    />
  </v-container>
</template>

<script>
import axios from 'axios';
import FeedbackComposer from './FeedbackComposer.vue';

export default {
  name: 'FeedbackList',
  components: {
    FeedbackComposer,
  },
  props: {
    type: {
      type: String,
      required: true,
      validator: (value) => ['bug', 'feature'].includes(value),
    },
  },
  data() {
    return {
      loading: false,
      tickets: [],
      pagination: {},
      filters: {
        status: null,
        tag: null,
        sort: 'popular',
      },
      statusOptions: [
        { title: 'Reported', value: 'reported' },
        { title: 'Confirmed', value: 'confirmed' },
        { title: 'Investigating', value: 'investigating' },
        { title: 'Planned', value: 'planned' },
        { title: 'In Progress', value: 'in_progress' },
        { title: 'Completed', value: 'completed' },
        { title: 'Won\'t Fix', value: 'wontfix' },
      ],
      tagOptions: [],
      sortOptions: [
        { title: 'Most Popular', value: 'popular' },
        { title: 'Newest First', value: 'newest' },
        { title: 'Oldest First', value: 'oldest' },
      ],
      showComposer: false,
    };
  },
  computed: {
    title() {
      return this.type === 'bug' ? 'Bug Reports' : 'Feature Requests';
    },
    subtitle() {
      return this.type === 'bug'
        ? 'Help us improve by reporting bugs and issues'
        : 'Share your ideas for new features and improvements';
    },
  },
  watch: {
    'filters.status'() {
      this.fetchTickets();
    },
    'filters.tag'() {
      this.fetchTickets();
    },
    'filters.sort'() {
      this.fetchTickets();
    },
  },
  mounted() {
    this.fetchTickets();
    this.fetchTags();
  },
  methods: {
    async fetchTickets(page = 1) {
      this.loading = true;
      try {
        const endpoint = this.type === 'bug' ? 'bugs' : 'features';
        const { data } = await axios.get(`/api/feedback/${endpoint}`, {
          params: {
            page,
            ...this.filters,
          },
        });
        this.tickets = data.data;
        this.pagination = {
          current_page: data.current_page,
          last_page: data.last_page,
          per_page: data.per_page,
          total: data.total,
        };
      } catch (error) {
        console.error('Error fetching tickets:', error);
      } finally {
        this.loading = false;
      }
    },
    async fetchTags() {
      try {
        const { data } = await axios.get('/api/feedback/tags');
        this.tagOptions = data.map((tag) => ({
          title: tag.name,
          value: tag.slug,
        }));
      } catch (error) {
        console.error('Error fetching tags:', error);
      }
    },
    async toggleVote(ticket) {
      if (!this.$store.state.auth.user) return;

      try {
        const { data } = await axios.post(`/api/feedback/tickets/${ticket.id}/vote`);
        ticket.user_has_voted = data.voted;
        ticket.votes_count = data.votes_count;
      } catch (error) {
        console.error('Error toggling vote:', error);
      }
    },
    goToTicket(id) {
      this.$router.push({ name: 'feedback.ticket', params: { id } });
    },
    onTicketSubmitted(ticket) {
      this.showComposer = false;
      this.$router.push({ name: 'feedback.ticket', params: { id: ticket.id } });
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
  },
};
</script>

<style scoped>
.hover-elevation {
  cursor: pointer;
  transition: all 0.2s;
}

.hover-elevation:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>
