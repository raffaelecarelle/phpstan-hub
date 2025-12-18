<script setup>
import { ref, watch } from 'vue';
import { buildFileTree, getChildrenArray } from '../utils/fileTree.js';
import FileTreeNode from './FileTreeNode.vue';

const props = defineProps({
    files: Object,
    projectRoot: String,
    selectedFile: String,
});

const emit = defineEmits(['file-selected']);

const fileTree = ref(null);

// Build tree when files change
watch(() => [props.files, props.projectRoot], ([newFiles, newProjectRoot]) => {
    // Don't reset tree if files is undefined (happens during reactivity updates)
    if (!newFiles) {
        console.log('[FileTreeSidebar] Files is undefined, keeping existing tree');
        return;
    }

    if (Object.keys(newFiles).length > 0 && newProjectRoot) {
        fileTree.value = buildFileTree(newFiles, newProjectRoot);
    } else if (Object.keys(newFiles).length === 0) {
        console.log('[FileTreeSidebar] No files, setting tree to null');
        fileTree.value = null;
    }
}, { immediate: true });

const toggleFolder = (node) => {
    if (node.type === 'folder') {
        node.expanded = !node.expanded;
    }
};

const selectFile = (node) => {
    if (node.type === 'file') {
        emit('file-selected', node.fullPath);
    }
};

const handleNodeClick = (node) => {
    if (node.type === 'folder') {
        toggleFolder(node);
    } else {
        selectFile(node);
    }
};
</script>

<template>
    <div class="h-full bg-gray-800 border-r border-gray-700 overflow-y-auto">
        <div class="p-4">
            <h2 class="text-lg font-semibold text-gray-200 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                Files
            </h2>

            <div v-if="!fileTree" class="text-gray-500 text-sm">
                No files to display
                <div class="text-xs mt-2">DEBUG: fileTree is null/undefined</div>
            </div>

            <div v-else>
                <div class="text-xs text-gray-600 mb-2">
                    DEBUG: Tree exists, children: {{ Object.keys(fileTree.children || {}).length }}
                </div>
                <FileTreeNode
                    v-for="child in getChildrenArray(fileTree.children)"
                    :key="child.name"
                    :node="child"
                    :depth="0"
                    :selected-file="selectedFile"
                    @node-click="handleNodeClick"
                />
            </div>
        </div>
    </div>
</template>
