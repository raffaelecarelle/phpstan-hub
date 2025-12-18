<script setup>
import {computed, ref} from 'vue';
import FileTreeSidebar from './FileTreeSidebar.vue';
import CodeViewer from './CodeViewer.vue';
import SearchInFiles from './SearchInFiles.vue';
import KeyboardShortcutsModal from './KeyboardShortcutsModal.vue';
import { useKeyboardShortcuts } from '../composables/useKeyboardShortcuts';

const props = defineProps({
    results: Object,
    status: String,
    editorUrl: String,
    projectRoot: String,
    hostProjectRoot: String|null,
});

const emit = defineEmits(['error-ignored']);

const selectedFile = ref(null);
const sidebarWidth = ref(300); // Default sidebar width in pixels
const isResizing = ref(false);
const sidebarMode = ref('tree'); // 'tree' or 'search'
const searchSelectedLine = ref(null);
const showShortcutsModal = ref(false);

const files = computed(() => {
    return props.results?.files || {};
});

const selectedFileErrors = computed(() => {
    if (!selectedFile.value || !files.value[selectedFile.value]) {
        return [];
    }
    return files.value[selectedFile.value].messages || [];
});

const handleFileSelected = (filePath) => {
    selectedFile.value = filePath;
    searchSelectedLine.value = null;
};

const handleLineSelected = (lineNumber) => {
    searchSelectedLine.value = lineNumber;
};

const toggleSidebarMode = (mode) => {
    sidebarMode.value = mode;
};

const handleErrorIgnored = (data) => {
    emit('error-ignored', data);
};

// Resizable sidebar functionality
const startResize = (event) => {
    isResizing.value = true;
    document.addEventListener('mousemove', handleResize);
    document.addEventListener('mouseup', stopResize);
    event.preventDefault();
};

const handleResize = (event) => {
    if (!isResizing.value) return;

    const newWidth = event.clientX;
    if (newWidth >= 200 && newWidth <= 600) {
        sidebarWidth.value = newWidth;
    }
};

const stopResize = () => {
    isResizing.value = false;
    document.removeEventListener('mousemove', handleResize);
    document.removeEventListener('mouseup', stopResize);
};

// Keyboard shortcuts
const filesList = computed(() => Object.keys(files.value));
const currentFileIndex = computed(() => {
    if (!selectedFile.value) return -1;
    return filesList.value.indexOf(selectedFile.value);
});

const shortcuts = [
    {
        key: 'ctrl+j',
        callback: () => {
            // Next file
            if (currentFileIndex.value < filesList.value.length - 1) {
                handleFileSelected(filesList.value[currentFileIndex.value + 1]);
            }
        },
    },
    {
        key: 'ctrl+k',
        callback: () => {
            // Previous file
            if (currentFileIndex.value > 0) {
                handleFileSelected(filesList.value[currentFileIndex.value - 1]);
            }
        },
    },
    {
        key: 'alt+1',
        callback: () => {
            toggleSidebarMode('tree');
        },
    },
    {
        key: 'alt+2',
        callback: () => {
            toggleSidebarMode('search');
        },
    },
    {
        key: 'ctrl+/',
        callback: () => {
            showShortcutsModal.value = !showShortcutsModal.value;
        },
    },
    {
        key: 'ctrl+w',
        callback: () => {
            // Close current file
            selectedFile.value = null;
        },
    },
    {
        key: 'escape',
        callback: () => {
            // Close modals
            showShortcutsModal.value = false;
        },
    },
];

useKeyboardShortcuts(shortcuts);
</script>

<template>
    <div class="flex h-full overflow-hidden bg-gray-900 w-full relative">
        <!-- Loading State -->
        <div v-if="status === 'running'" class="flex-grow flex items-center justify-center">
            <div class="text-center p-12 bg-gray-800 rounded-lg shadow-lg">
                <div class="flex justify-center items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-xl text-gray-300">Analysis in progress...</p>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!results" class="flex-grow flex items-center justify-center">
            <div class="text-center p-12 bg-gray-800 rounded-lg shadow-lg">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>
                <p class="text-xl text-gray-400">Run analysis to see results</p>
            </div>
        </div>

        <!-- No Errors State -->
        <div v-else-if="Object.keys(files).length === 0" class="flex-grow flex items-center justify-center">
            <div class="text-center p-12 bg-gray-800 rounded-lg shadow-lg">
                <svg class="w-16 h-16 mx-auto mb-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-xl text-gray-400">🎉 No errors found! Your code is clean!</p>
            </div>
        </div>

        <!-- Explorer View with Sidebar and Code Viewer -->
        <template v-else>
            <!-- Sidebar -->
            <div
                class="flex-shrink-0 overflow-hidden flex flex-col"
                :style="{ width: sidebarWidth + 'px' }"
            >
                <!-- Sidebar Mode Tabs -->
                <div class="flex bg-gray-800 border-b border-gray-700">
                    <button
                        @click="toggleSidebarMode('tree')"
                        :class="[
                            'flex-1 px-3 py-2 text-sm font-medium transition-colors flex items-center justify-center gap-1',
                            sidebarMode === 'tree' ? 'bg-gray-900 text-blue-400 border-b-2 border-blue-400' : 'text-gray-400 hover:text-white hover:bg-gray-750'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        <span>Files</span>
                    </button>
                    <button
                        @click="toggleSidebarMode('search')"
                        :class="[
                            'flex-1 px-3 py-2 text-sm font-medium transition-colors flex items-center justify-center gap-1',
                            sidebarMode === 'search' ? 'bg-gray-900 text-blue-400 border-b-2 border-blue-400' : 'text-gray-400 hover:text-white hover:bg-gray-750'
                        ]"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <span>Search</span>
                    </button>
                </div>

                <!-- Sidebar Content -->
                <div class="flex-1 overflow-hidden">
                    <FileTreeSidebar
                        v-if="sidebarMode === 'tree'"
                        :files="files"
                        :project-root="projectRoot"
                        :selected-file="selectedFile"
                        @file-selected="handleFileSelected"
                    />
                    <SearchInFiles
                        v-else
                        :files="files"
                        :project-root="projectRoot"
                        @file-selected="handleFileSelected"
                        @line-selected="handleLineSelected"
                    />
                </div>
            </div>

            <!-- Resizer -->
            <div
                @mousedown="startResize"
                class="w-1 bg-gray-700 cursor-col-resize hover:bg-blue-500 transition-colors flex-shrink-0"
                :class="{ 'bg-blue-500': isResizing }"
            ></div>

            <!-- Code Viewer -->
            <div class="flex-grow overflow-hidden">
                <CodeViewer
                    :file-path="selectedFile"
                    :errors="selectedFileErrors"
                    :editor-url="editorUrl"
                    :project-root="projectRoot"
                    :host-project-root="hostProjectRoot"
                    @error-ignored="handleErrorIgnored"
                />
            </div>
        </template>

        <!-- Keyboard Shortcuts Modal -->
        <KeyboardShortcutsModal
            :show="showShortcutsModal"
            @close="showShortcutsModal = false"
        />

        <!-- Help Button (floating) -->
        <button
            v-if="!showShortcutsModal && Object.keys(files).length > 0"
            @click="showShortcutsModal = true"
            class="fixed bottom-6 right-6 w-12 h-12 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg flex items-center justify-center transition-all hover:scale-110 z-10"
            title="Show keyboard shortcuts (Ctrl+/)"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
/* Prevent text selection during resize */
.cursor-col-resize {
    user-select: none;
}
</style>
