<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    isRunning: Boolean,
    config: Object,
});

const emit = defineEmits(['run-analysis', 'view-changed']);

const viewMode = ref('grouped');

const run = () => {
    emit('run-analysis', {
        paths: props.config?.paths || [],
        level: props.config?.level || 5,
    });
};
</script>

<template>
    <div class="flex items-center space-x-4">
        <button @click="run" :disabled="isRunning" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:bg-gray-600 transition-colors duration-200 shadow-md">
            <span v-if="isRunning" class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Running...
            </span>
            <span v-else>Analyze</span>
        </button>
    </div>
</template>
