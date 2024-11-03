<template>
  <transition name="fade">
    <div
        v-if="open"
        class="tinybox"
        @click="close"
        @wheel.prevent
        @touchmove.prevent
    >
      <div
          class="tinybox__content"
          :class="{ 'tinybox__content--no-thumbs': noThumbs }"
          @touchstart="swipeStart"
          @touchmove="swipe"
      >
        <transition :name="slide">
          <img
              :key="images[index].original_url || images[index] || ''"
              :src="images[index].original_url || images[index] || ''"
              :alt="images[index].original_url || images[index].caption || ''"
              class="tinybox__content__image"
              @click.stop="next"
          />
        </transition>

        <span
            class="tinybox__content__image__caption"
        >{{images[index].caption}} Von {{images[index].photographer}} am {{images[index].created_at}}</span>

        <div
            v-if="prevImage !== index"
            class="tinybox__content__control tinybox__content__control--prev"
            @click.stop="prev"
        />
        <div
            v-if="nextImage !== index"
            class="tinybox__content__control tinybox__content__control--next"
            @click.stop="next"
        />
        <div
            class="tinybox__content__control tinybox__content__control--close"
            @click.stop="close"
        />
      </div>
      <div
          v-if="!noThumbs"
          ref="thumbs"
          class="tinybox__thumbs"
          @touchmove.stop
          @wheel.stop
      >
        <img
            v-for="(image, idx) in images"
            :key="idx"
            ref="thumbItems"
            :class="{ 'tinybox__thumbs__item--active': index === idx }"
            :src="image.thumbnail || image.original_url || image || ''"
            :alt="images[index].alt || images[index].caption || ''"
            class="tinybox__thumbs__item"
            @click.stop="goto(idx)"
        />
      </div>
    </div>
  </transition>
</template>

<script>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

export default {
  name: "Tinybox",
  props: {
    images: {
      type: Array,
      default: () => [],
    },
    index: {
      type: Number,
      default: null,
    },
    loop: {
      type: Boolean,
      default: false,
    },
    noThumbs: {
      type: Boolean,
      default: false,
    },
  },
  setup(props, { emit }) {
    const slide = ref("next");
    const swipeDone = ref(false);
    const swipeX = ref(null);

    const open = computed(() => props.index !== null);

    const prevImage = computed(() => {
      if (props.index > 0) {
        return props.index - 1;
      }
      return props.loop ? props.images.length - 1 : props.index;
    });

    const nextImage = computed(() => {
      if (props.index < props.images.length - 1) {
        return props.index + 1;
      }
      return props.loop ? 0 : props.index;
    });

    const close = () => {
      const oldIndex = props.index;
      goto(null);
      emit("close", oldIndex);
    };

    const prev = () => {
      console.log('prev')
      emit("prev", prevImage.value);
      goto(prevImage.value, "prev");
    };

    const next = () => {
      emit("next", nextImage.value);
      goto(nextImage.value, "next");
    };

    const goto = (idx, slideDirection) => {
      slide.value = slideDirection || (props.index < idx ? "next" : "prev");
      emit("change", idx);
    };

    const keyup = (e) => {
      if (["ArrowRight", "Right", "ArrowLeft", "Left", "Escape", "Esc"].includes(e.key)) {
        e.key === "ArrowRight" || e.key === "Right" ? next() : e.key === "ArrowLeft" || e.key === "Left" ? prev() : close();
      }
    };

    const swipeStart = (e) => {
      swipeDone.value = false;
      if (e.changedTouches.length === 1) {
        swipeX.value = e.changedTouches[0].screenX;
      }
    };

    const swipe = (e) => {
      if (!swipeDone.value && e.changedTouches.length === 1) {
        const swipeDistance = e.changedTouches[0].screenX - swipeX.value;
        if (swipeDistance >= 50) {
          prev();
          swipeDone.value = true;
        } else if (swipeDistance <= -50) {
          next();
          swipeDone.value = true;
        }
      }
    };

    watch(open, (newValue) => {
      if (newValue) {
        window.addEventListener("keyup", keyup);
      } else {
        window.removeEventListener("keyup", keyup);
      }
    });

    watch(() => props.index, (newIndex) => {
      if (!props.noThumbs && newIndex !== null) {
        nextTick(() => {
          const { thumbs, thumbItems } = refs;
          const curThumb = thumbItems[newIndex];
          const distance = curThumb.offsetLeft - window.innerWidth / 2;
          thumbs.scrollLeft =
              distance < thumbs.scrollWidth
                  ? distance + curThumb.clientWidth / 2
                  : thumbs.scrollWidth;
        });
      }
    });

    onMounted(() => {
      if (open.value) {
        window.addEventListener("keyup", keyup);
      }
    });

    onBeforeUnmount(() => {
      window.removeEventListener("keyup", keyup);
    });

    return {
      open,
      prevImage,
      nextImage,
      slide,
      swipeDone,
      swipeX,
      close,
      prev,
      next,
      goto,
      swipeStart,
      swipe,
    };
  },
};
</script>

<style scoped>
.tinybox {
  background-color: rgba(0, 0, 0, 0.9);
  bottom: 0;
  left: 0;
  position: fixed;
  right: 0;
  text-align: center;
  top: 0;
  z-index: 1000;
}

.tinybox__content {
  height: 85%;
  position: relative;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tinybox__content--no-thumbs {
  height: 100%;
}

.tinybox__content__image {
  background-color: #222;
  cursor: pointer;
  display: inline-block;
  max-height: 90%;
  max-width: 80%;
  position: absolute;
}

.tinybox__content__image__caption {
  position: absolute;
  bottom: 0;
  padding: 0.5rem 0.75rem;
  border-radius: 5px;
  color: white;
  background-color: rgba(0, 0, 0, 0.9);
  opacity: 0.75;
  font-family: sans-serif;
  font-weight: lighter;
  font-size: 1.2rem;
}

.tinybox__content__control {
  background: no-repeat center/24px;

  cursor: pointer;
  opacity: 0.5;
  position: absolute;
  top: 0;
  transition: opacity 300ms ease;
  width: 4em;
}

.tinybox__content__control:hover {
  opacity: 1;
}

.tinybox__content__control--prev {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='2 -2 28 36' width='40' height='60' fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3'%3E%3Cpath d='M20 30 L8 16 20 2' /%3E%3C/svg%3E");
  bottom: 0;
  left: 0;
}

.tinybox__content__control--next {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='2 -2 28 36' width='40' height='60' fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3'%3E%3Cpath d='M12 30 L24 16 12 2' /%3E%3C/svg%3E");
  bottom: 0;
  right: 0;
}

.tinybox__content__control--close {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-5 -5 46 46' width='40' height='40' fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='4'%3E%3Cpath d='M2 30 L30 2 M30 30 L2 2' /%3E%3C/svg%3E");
  height: 2.6em;
  right: 0;
}

.tinybox__thumbs {
  bottom: 0;
  height: 15%;
  left: 0;
  line-height: 0;
  padding: 0 10px;
  position: absolute;
  right: 0;
  overflow-x: scroll;
  overflow-y: hidden;
  scroll-behavior: smooth;
  white-space: nowrap;
}

.tinybox__thumbs__item {
  cursor: pointer;
  display: inline-block;
  height: 10vh;
  margin: 2.5vh 5px;
  object-fit: cover;
  overflow: hidden;
  width: 10vh;
}

.tinybox__thumbs__item--active {
  opacity: 0.3;
}

/*******************/
/*   TRANSITIONS   */
/*******************/

.fade-enter,
.next-enter,
.prev-enter,
.fade-leave-active,
.prev-leave-active,
.next-leave-active {
  opacity: 0;
}

.fade-enter-active,
.fade-leave-active,
.prev-leave-active,
.next-leave-active {
  transition: opacity 300ms ease;
}

.prev-enter {
  transform: translateX(-40px);
}

.next-enter {
  transform: translateX(40px);
}

.next-enter-active,
.prev-enter-active {
  transition: opacity 300ms ease, transform 300ms ease;
}
</style>
