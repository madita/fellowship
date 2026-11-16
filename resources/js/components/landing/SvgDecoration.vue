<template>
  <div
    class="svg-decoration-wrapper"
    :class="backgroundClass"
    :style="backgroundStyle"
  >
    <svg
      class="svg-decoration"
      :class="position"
      xmlns="http://www.w3.org/2000/svg"
      :viewBox="svgData.viewBox"
      :style="{ fill: color }"
    >
      <path :d="svgData.path"/>
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  styleType: {
    type: String,
    default: 'wave1',
    validator: (value) => ['wave1', 'wave2', 'wave3', 'curve1', 'curve2', 'angle1', 'angle2'].includes(value)
  },
  color: {
    type: String,
    default: '#f2f5f8'
  },
  backgroundColor: {
    type: String,
    default: 'transparent'
  },
  backgroundType: {
    type: String,
    default: 'custom',
    validator: (value) => ['custom', 'theme', 'transparent'].includes(value)
  },
  backgroundTheme: {
    type: String,
    default: 'background',
    validator: (value) => ['background', 'surface', 'surface-variant', 'surface-bright', 'surface-dim'].includes(value)
  },
  position: {
    type: String,
    default: 'top',
    validator: (value) => ['top', 'bottom'].includes(value)
  }
});

const backgroundClass = computed(() => {
  if (props.backgroundType === 'theme') {
    return `bg-${props.backgroundTheme}`;
  }
  return '';
});

const backgroundStyle = computed(() => {
  if (props.backgroundType === 'custom') {
    return { backgroundColor: props.backgroundColor };
  }
  return {};
});

const svgStyles = {
  wave1: {
    viewBox: '0 0 1442 163',
    path: 'm-3.90909,6l48.30303,16c48.30303,16 144.90908,48 241.51514,48c96.60606,0 193.21211,-32 289.81817,-32c96.60606,0 193.21211,32 289.81817,53.3c96.60606,21.7 193.21211,31.7 289.81817,16c96.60606,-16.3 193.21211,-58.3 241.51514,-80l48.30303,-21.3l0,160l-48.30303,0c-48.30303,0 -144.90908,0 -241.51514,0c-96.60606,0 -193.21211,0 -289.81817,0c-96.60606,0 -193.21211,0 -289.81817,0c-96.60606,0 -193.21211,0 -289.81817,0c-96.60606,0 -193.21211,0 -241.51514,0l-48.30303,0l0,-160z'
  },
  wave2: {
    viewBox: '0 0 1440 120',
    path: 'M0,64L48,58.7C96,53,192,43,288,58.7C384,75,480,117,576,122.7C672,128,768,96,864,80C960,64,1056,64,1152,69.3C1248,75,1344,85,1392,90.7L1440,96L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z'
  },
  wave3: {
    viewBox: '0 0 1440 100',
    path: 'M0,32L60,42.7C120,53,240,75,360,74.7C480,75,600,53,720,48C840,43,960,53,1080,58.7C1200,64,1320,64,1380,64L1440,64L1440,100L1380,100C1320,100,1200,100,1080,100C960,100,840,100,720,100C600,100,480,100,360,100C240,100,120,100,60,100L0,100Z'
  },
  curve1: {
    viewBox: '0 0 1440 150',
    path: 'M0,96L1440,32L1440,150L0,150Z'
  },
  curve2: {
    viewBox: '0 0 1440 150',
    path: 'M0,64L1440,96L1440,150L0,150Z'
  },
  angle1: {
    viewBox: '0 0 1440 100',
    path: 'M0,50L720,10L1440,50L1440,100L0,100Z'
  },
  angle2: {
    viewBox: '0 0 1440 100',
    path: 'M0,20L1440,60L1440,100L0,100Z'
  }
};

const svgData = computed(() => {
  return svgStyles[props.styleType] || svgStyles.wave1;
});
</script>

<style scoped>
.svg-decoration-wrapper {
  width: 100%;
  line-height: 0;
}

.svg-decoration {
  width: 100%;
  display: block;
}

.svg-decoration.top {
  margin-bottom: -1px;
}

.svg-decoration.bottom {
  margin-top: -1px;
  transform: scaleY(-1);
}
</style>
