<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    lines: Array, // Array of line strings
    errorLines: Set, // Set of line numbers with errors
    currentScrollPosition: Number, // Current scroll position (0-1)
    viewportHeight: Number, // Height of the visible viewport
});

const emit = defineEmits(['scroll-to']);

const minimapRef = ref(null);
const MINIMAP_WIDTH = 100;
const LINE_HEIGHT = 2; // Each line in minimap is 2px tall
const CHAR_WIDTH = 0.5; // Each character in minimap

// Generate minimap data
const minimapLines = computed(() => {
    if (!props.lines) return [];

    return props.lines.map((line, index) => {
        const lineNum = index + 1;
        const hasError = props.errorLines?.has(lineNum);

        // Calculate visual representation
        const trimmed = line.trim();
        const isEmpty = trimmed.length === 0;
        const density = Math.min(1, line.length / 100); // 0-1 based on line length

        return {
            lineNum,
            isEmpty,
            hasError,
            density,
            content: line,
        };
    });
});

// Calculate minimap height
const minimapHeight = computed(() => {
    return minimapLines.value.length * LINE_HEIGHT;
});

// Calculate viewport indicator position and height
const viewportIndicator = computed(() => {
    if (!props.viewportHeight || !minimapHeight.value) {
        return { top: 0, height: 100 };
    }

    const totalHeight = minimapLines.value.length;
    const viewportRatio = props.viewportHeight / totalHeight;
    const indicatorHeight = Math.max(20, minimapHeight.value * viewportRatio);
    const indicatorTop = (props.currentScrollPosition || 0) * (minimapHeight.value - indicatorHeight);

    return {
        top: indicatorTop,
        height: indicatorHeight,
    };
});

// Handle click on minimap to scroll
const handleMinimapClick = (event) => {
    if (!minimapRef.value) return;

    const rect = minimapRef.value.getBoundingClientRect();
    const clickY = event.clientY - rect.top;
    const ratio = clickY / rect.height;

    // Emit scroll event
    emit('scroll-to', ratio);
};

// Get color for minimap line
const getLineColor = (line) => {
    if (line.hasError) return '#ef4444'; // red
    if (line.isEmpty) return '#1f2937'; // very dark gray
    return `rgba(156, 163, 175, ${0.2 + line.density * 0.5})`; // gray with varying opacity
};
</script>

<template>
    <div
        ref="minimapRef"
        @click="handleMinimapClick"
        class="relative bg-gray-900 border-l border-gray-700 cursor-pointer select-none"
        :style="{ width: MINIMAP_WIDTH + 'px', height: '100%' }"
    >
        <!-- Minimap Content -->
        <div class="relative" :style="{ height: minimapHeight + 'px', minHeight: '100%' }">
            <!-- Lines -->
            <div
                v-for="(line, index) in minimapLines"
                :key="index"
                class="absolute left-0 right-0"
                :style="{
                    top: index * LINE_HEIGHT + 'px',
                    height: LINE_HEIGHT + 'px',
                    backgroundColor: getLineColor(line),
                }"
                :title="`Line ${line.lineNum}`"
            ></div>

            <!-- Viewport Indicator -->
            <div
                class="absolute left-0 right-0 bg-blue-500 bg-opacity-20 border border-blue-500 pointer-events-none"
                :style="{
                    top: viewportIndicator.top + 'px',
                    height: viewportIndicator.height + 'px',
                }"
            ></div>
        </div>

        <!-- Overlay info (optional) -->
        <div class="absolute top-2 right-2 bg-gray-800 bg-opacity-90 rounded px-2 py-1 text-xs text-gray-400 pointer-events-none">
            <div class="flex items-center gap-1 mb-1">
                <div class="w-2 h-2 bg-red-500 rounded-full"></div>
                <span>Errors</span>
            </div>
            <div class="flex items-center gap-1">
                <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                <span>Viewport</span>
            </div>
        </div>
    </div>
</template>
