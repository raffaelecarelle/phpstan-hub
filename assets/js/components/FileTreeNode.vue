<script setup>
import { computed } from 'vue';
import { getChildrenArray } from '../utils/fileTree.js';

const props = defineProps({
    node: Object,
    depth: Number,
    selectedFile: String,
});

const emit = defineEmits(['node-click']);

const isSelected = computed(() => {
    return props.node.type === 'file' && props.node.fullPath === props.selectedFile;
});

const handleClick = () => {
    emit('node-click', props.node);
};
</script>

<template>
    <div>
        <div
            @click="handleClick"
            :style="{ paddingLeft: (depth * 16 + 8) + 'px' }"
            :class="[
                'flex items-center py-1.5 px-2 cursor-pointer hover:bg-gray-700 rounded transition-colors',
                isSelected ? 'bg-blue-600 hover:bg-blue-600' : ''
            ]"
        >
            <!-- Folder/File Icon Container -->
            <div class="flex items-center flex-shrink-0 mr-2">
                <!-- Folder Chevron -->
                <svg
                    v-if="node.type === 'folder'"
                    class="w-4 h-4 text-gray-400 transition-transform mr-1"
                    :class="{ 'rotate-90': node.expanded }"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>

                <!-- Folder Icon -->
                <svg
                    v-if="node.type === 'folder'"
                    class="w-5 h-5 text-yellow-500"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                </svg>

                <!-- File Icon -->
                <svg
                    v-if="node.type === 'file'"
                    class="w-5 h-5 text-gray-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>

            <!-- Name -->
            <span class="text-sm text-gray-200 flex-grow truncate">
                {{ node.name }}
            </span>

            <!-- Error Badge -->
            <span
                v-if="node.errors > 0"
                class="ml-2 px-2 py-0.5 text-xs font-medium bg-red-500 text-white rounded-full"
            >
                {{ node.errors }}
            </span>
        </div>

        <!-- Children (if folder is expanded) -->
        <div v-if="node.type === 'folder' && node.expanded && node.children">
            <FileTreeNode
                v-for="child in getChildrenArray(node.children)"
                :key="child.name"
                :node="child"
                :depth="depth + 1"
                :selected-file="selectedFile"
                @node-click="$emit('node-click', $event)"
            />
        </div>
    </div>
</template>
