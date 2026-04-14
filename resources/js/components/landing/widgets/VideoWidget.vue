<template>
  <v-sheet class="py-16 bg-grey-lighten-4">
    <v-container>
      <div class="text-center mb-8">
        <h2 v-if="content.title" class="text-h3 text-md-h2 font-weight-bold mb-4">
          {{ content.title }}
        </h2>
        <p v-if="content.description" class="text-h6 text-grey">
          {{ content.description }}
        </p>
      </div>

      <v-row justify="center">
        <v-col cols="12" :md="content.videoWidth || 10" :lg="content.videoWidth || 8">
          <v-card elevation="8" class="video-container">
            <div class="video-wrapper">
              <iframe
                v-if="embedUrl"
                :src="embedUrl"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
                class="video-iframe"
              ></iframe>
              <div v-else class="video-placeholder pa-8 text-center">
                <v-icon size="64" color="grey">mdi-video-off</v-icon>
                <p class="text-h6 text-grey mt-4">No video URL provided</p>
              </div>
            </div>
          </v-card>

          <div v-if="content.caption" class="text-center mt-4">
            <p class="text-body-1 text-grey">{{ content.caption }}</p>
          </div>
        </v-col>
      </v-row>
    </v-container>
  </v-sheet>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  content: {
    type: Object,
    default: () => ({
      title: '',
      description: '',
      videoUrl: '',
      videoProvider: 'youtube',
      autoplay: false,
      caption: '',
      videoWidth: 10
    })
  },
  config: {
    type: Object,
    default: () => ({})
  }
});

const embedUrl = computed(() => {
  if (!props.content.videoUrl) return null;

  const autoplayParam = props.content.autoplay ? '1' : '0';
  const url = props.content.videoUrl;

  if (props.content.videoProvider === 'youtube') {
    // Extract YouTube video ID
    const youtubeRegex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
    const match = url.match(youtubeRegex);
    if (match && match[1]) {
      return `https://www.youtube.com/embed/${match[1]}?autoplay=${autoplayParam}`;
    }
  } else if (props.content.videoProvider === 'vimeo') {
    // Extract Vimeo video ID
    const vimeoRegex = /vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/([^\/]*)\/videos\/|album\/(\d+)\/video\/|)(\d+)(?:$|\/|\?)/;
    const match = url.match(vimeoRegex);
    if (match && match[3]) {
      return `https://player.vimeo.com/video/${match[3]}?autoplay=${autoplayParam}`;
    }
  } else if (props.content.videoProvider === 'custom') {
    // For custom embed URLs
    return url;
  }

  return null;
});
</script>

<style scoped>
.video-wrapper {
  position: relative;
  padding-bottom: 56.25%; /* 16:9 aspect ratio */
  height: 0;
  overflow: hidden;
}

.video-iframe {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.video-placeholder {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.05);
}
</style>
