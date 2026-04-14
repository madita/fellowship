<template>
  <v-sheet class="py-16">
    <v-container>
      <div class="text-center mb-12">
        <h2 class="text-h3 text-md-h2 font-weight-bold mb-4">
          {{ content.title }}
        </h2>
        <p v-if="content.subtitle" class="text-h6 text-grey">
          {{ content.subtitle }}
        </p>
      </div>

      <v-row>
        <v-col
          v-for="(post, index) in displayedPosts"
          :key="index"
          cols="12"
          :md="postsPerRow === 2 ? 6 : 4"
        >
          <v-card class="blog-post-card h-100" elevation="2" hover>
            <v-img
              v-if="post.image"
              :src="post.image"
              height="200"
              cover
              class="blog-post-image"
            >
              <template v-if="post.category">
                <v-chip
                  color="primary"
                  size="small"
                  class="ma-2"
                >
                  {{ post.category }}
                </v-chip>
              </template>
            </v-img>

            <v-card-title class="text-h5">
              {{ post.title }}
            </v-card-title>

            <v-card-subtitle v-if="post.author || post.date" class="d-flex align-center">
              <v-icon size="small" class="mr-1">mdi-account</v-icon>
              {{ post.author }}
              <v-icon size="small" class="mx-2">mdi-circle-small</v-icon>
              <v-icon size="small" class="mr-1">mdi-calendar</v-icon>
              {{ post.date }}
            </v-card-subtitle>

            <v-card-text>
              <p class="text-body-1">{{ post.excerpt }}</p>
            </v-card-text>

            <v-card-actions>
              <v-btn
                color="primary"
                variant="text"
                :href="post.link"
                append-icon="mdi-arrow-right"
              >
                Read More
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-col>
      </v-row>

      <div v-if="content.showViewAllButton" class="text-center mt-8">
        <v-btn
          color="primary"
          size="large"
          :href="content.viewAllLink || '/blog'"
        >
          View All Posts
        </v-btn>
      </div>
    </v-container>
  </v-sheet>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: 'Latest Blog Posts',
      subtitle: '',
      posts: [],
      postsToShow: 3,
      postsPerRow: 3,
      showViewAllButton: true,
      viewAllLink: '/blog'
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const displayedPosts = computed(() => {
  const count = props.content.postsToShow || 3;
  return (props.content.posts || []).slice(0, count);
});

const postsPerRow = computed(() => props.content.postsPerRow || 3);
</script>

<style scoped>
.blog-post-card {
  transition: transform 0.3s ease;
}

.blog-post-card:hover {
  transform: translateY(-8px);
}

.blog-post-image {
  position: relative;
}
</style>
