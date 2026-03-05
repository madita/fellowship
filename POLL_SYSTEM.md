# Poll System

A polymorphic polling system that can be attached to any model in the application.

## Features

- **Polymorphic Relationships** - Attach polls to any model (Forum, Ticket, Page, etc.)
- **Single or Multiple Choice** - Support for both voting types
- **Anonymous Voting** - Option to hide vote counts until poll closes
- **Time-Limited Polls** - Optional closing date/time
- **Real-time Results** - Live vote counting and percentage display
- **Vote Management** - Users can change their votes while poll is open
- **Visual Results** - Color-coded progress bars showing vote distribution

## Database Structure

### Tables

1. **polls** - Main poll data
   - `pollable_type` & `pollable_id` - Polymorphic relation
   - `title` - Poll question
   - `description` - Optional details
   - `type` - 'single' or 'multiple' choice
   - `anonymous` - Hide vote counts until closed
   - `closes_at` - Optional closing timestamp
   - `created_by` - User who created the poll

2. **poll_options** - Available choices
   - `poll_id` - Foreign key to polls
   - `option_text` - Option text
   - `position` - Display order

3. **poll_votes** - User votes
   - `poll_id` - Foreign key to polls
   - `poll_option_id` - Foreign key to poll_options
   - `user_id` - Foreign key to users
   - Unique constraint on (poll_id, poll_option_id, user_id)

## Backend Usage

### Adding Polls to a Model

1. Add the `HasPolls` trait to your model:

```php
use App\Models\Concerns\HasPolls;

class Forum extends Model
{
    use HasPolls;
    
    // ... rest of your model
}
```

2. The trait provides:
   - `polls()` - Relationship to all polls
   - `activePoll()` - Get the most recent open poll

### API Endpoints

All endpoints require authentication (`auth:sanctum` middleware).

#### List Polls
```
GET /api/polls
GET /api/polls?pollable_type=App\Models\Forum&pollable_id=1
```

#### Get Single Poll
```
GET /api/polls/{poll}
```

#### Create Poll
```
POST /api/polls
{
  "pollable_type": "App\\Models\\Forum",
  "pollable_id": 1,
  "title": "What's your favorite feature?",
  "description": "Help us prioritize development",
  "type": "single",
  "anonymous": false,
  "closes_at": "2026-12-31T23:59:59",
  "options": [
    { "option_text": "Feature A" },
    { "option_text": "Feature B" },
    { "option_text": "Feature C" }
  ]
}
```

#### Update Poll
```
PUT /api/polls/{poll}
```
**Note:** Can only update polls that have no votes yet.

#### Delete Poll
```
DELETE /api/polls/{poll}
```

#### Vote
```
POST /api/polls/{poll}/vote
{
  "option_ids": [1, 2]  // Single ID for single-choice, multiple for multiple-choice
}
```

#### Remove Vote
```
DELETE /api/polls/{poll}/vote
```

## Frontend Usage

### Components

Three Vue components are provided:

1. **PollCard** - Main poll display with voting UI
2. **PollResults** - Results visualization
3. **PollCreator** - Dialog for creating/editing polls

### Basic Integration

```vue
<template>
  <div>
    <!-- Create Poll Button -->
    <poll-creator
      :pollable-type="'App\\Models\\Forum'"
      :pollable-id="forumId"
      @created="onPollCreated"
    />

    <!-- Display Polls -->
    <poll-card
      v-for="poll in polls"
      :key="poll.id"
      :poll="poll"
      :current-user="currentUser"
      @voted="onVoted"
      @edit="onEdit"
      @delete="onDelete"
    />
  </div>
</template>

<script>
import PollCard from '@/components/poll/PollCard.vue'
import PollCreator from '@/components/poll/PollCreator.vue'

export default {
  components: {
    PollCard,
    PollCreator
  },
  data() {
    return {
      polls: [],
      forumId: 1,
      currentUser: null
    }
  },
  methods: {
    async loadPolls() {
      const response = await this.$axios.get('/polls', {
        params: {
          pollable_type: 'App\\Models\\Forum',
          pollable_id: this.forumId
        }
      })
      this.polls = response.data.polls
    },
    onPollCreated(poll) {
      this.polls.unshift(poll)
    },
    onVoted(poll) {
      // Poll data is already updated
    },
    async onDelete(poll) {
      if (confirm('Delete this poll?')) {
        await this.$axios.delete(`/polls/${poll.id}`)
        this.polls = this.polls.filter(p => p.id !== poll.id)
      }
    }
  },
  mounted() {
    this.loadPolls()
  }
}
</script>
```

## Integration Examples

### Forum Threads

Add polls to forum threads to gather community opinions:

```php
// ForumThread model
use App\Models\Concerns\HasPolls;

class ForumThread extends Model
{
    use HasPolls;
}
```

```vue
<!-- ForumThread.vue -->
<poll-creator
  pollable-type="App\Models\Forum\ForumThread"
  :pollable-id="thread.id"
  @created="onPollCreated"
/>
```

### Tickets (Feature Requests)

Let users vote on implementation priorities:

```php
// Ticket model
use App\Models\Concerns\HasPolls;

class Ticket extends Model
{
    use HasPolls;
}
```

### Pages

Add polls to pages for feedback or surveys:

```php
// Page model already has HasPolls trait added
```

## Permissions

Current implementation:
- Any authenticated user can create polls
- Only poll creator can edit/delete
- Cannot edit polls that have votes
- Can delete polls even with votes (cascading delete)

To add role-based permissions, wrap routes in middleware:

```php
Route::group(['middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::resource('polls', 'PollController')->only(['store', 'update', 'destroy']);
});
```

## Styling

The poll components use Vuetify and are styled to match the Fellowship design system:
- Primary color for active elements
- Success/info/grey colors for result bars
- Consistent spacing and typography
- Responsive design

## Future Enhancements

Potential additions:
- Poll templates
- Vote change history
- Export poll results
- Poll scheduling (future start time)
- Image options
- Required voting
- Voter lists (for non-anonymous polls)
- Poll analytics dashboard

## Testing

Example test scenarios:
1. Create single-choice poll → vote → see results
2. Create multiple-choice poll → vote for multiple options
3. Change vote while poll is open
4. Try to vote after poll closes
5. Anonymous poll → verify vote counts hidden
6. Edit poll with no votes → verify updates work
7. Try to edit poll with votes → verify rejection

## Notes

- Poll results update in real-time after voting
- Votes are stored per user, not IP (requires authentication)
- Closed polls show final results even if anonymous
- Poll closing is checked on every request (no cron job needed)
- User votes are stored to show which options they selected
