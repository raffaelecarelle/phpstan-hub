<script setup>
import { ref, defineProps } from 'vue';

const props = defineProps({
  text: {
    type: String,
    required: true,
  },
});

const copied = ref(false);

const copyToClipboard = () => {
  if (!props.text) return;

  navigator.clipboard.writeText(props.text)
      .then(() => {
        copied.value = true;
        setTimeout(() => {
          copied.value = false;
        }, 1500);
      })
      .catch(err => {
        console.error('Failed to copy text: ', err);
      });
};
</script>

<template>
  <span class="relative group" @click.stop.prevent="copyToClipboard">
    <slot></slot>
    <span
        v-if="!copied"
        class="absolute -top-8 left-1/2 -translate-x-1/2 w-max bg-gray-600 text-white text-xs rounded py-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity"
    >
      Click to copy
    </span>
    <span
        v-if="copied"
        class="absolute -top-8 left-1/2 -translate-x-1/2 w-max bg-green-500 text-white text-xs rounded py-1 px-2"
    >
      Copied!
    </span>
  </span>
</template>
