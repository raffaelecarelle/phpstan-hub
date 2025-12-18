<script setup>
import { computed } from 'vue';
import { useBookmarks } from '../composables/useBookmarks';

const props = defineProps({
    projectRoot: String,
});

const emit = defineEmits(['file-selected']);

const { bookmarks, removeBookmark, clearBookmarks } = useBookmarks();

const getRelativePath = (filePath) => {
    if (!props.projectRoot || !filePath.startsWith(props.projectRoot)) {
        return filePath;
    }
    return filePath.substring(props.projectRoot.length + 1);
};

const getFileName = (filePath) => {
    const parts = filePath.split('/');
    return parts[parts.length - 1];
};

const getDirectory = (filePath) => {
    const relativePath = getRelativePath(filePath);
    const parts = relativePath.split('/');
    parts.pop(); // Remove filename
    return parts.join('/') || '/';
};

const formatDate = (timestamp) => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;

    return date.toLocaleDateString();
};

const handleFileClick = (filePath) => {
    emit('file-selected', filePath);
};

const handleRemoveClick = (filePath, event) => {
    event.stopPropagation();
    removeBookmark(filePath);
};

const handleClearAll = () => {
    if (confirm('Remove all bookmarks?')) {
        clearBookmarks();
    }
};
</script>

<template>
    <div class="h-full flex flex-col bg-gray-900">
        <!-- Header -->
        <div class="p-4 bg-gray-800 border-b border-gray-700">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    <h3 class="text-sm font-semibold text-gray-200">Bookmarks</h3>
                </div>
                <button
                    v-if="bookmarks.length > 0"
                    @click="handleClearAll"
                    class="text-xs text-gray-400 hover:text-red-400 transition-colors"
                    title="Clear all bookmarks"
                >
                    Clear all
                </button>
            </div>
            <p class="text-xs text-gray-400">{{ bookmarks.length }} {{ bookmarks.length === 1 ? 'file' : 'files' }} bookmarked</p>
        </div>

        <!-- Bookmarks List -->
        <div class="flex-1 overflow-y-auto">
            <!-- Empty State -->
            <div v-if="bookmarks.length === 0" class="flex items-center justify-center h-full text-gray-500">
                <div class="text-center p-8">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                    </svg>
                    <p class="text-lg mb-2">No bookmarks yet</p>
                    <p class="text-sm text-gray-600">Click the star icon on files to bookmark them</p>
                </div>
            </div>

            <!-- Bookmarks -->
            <div v-else class="p-2 space-y-1">
                <div
                    v-for="bookmark in bookmarks"
                    :key="bookmark.filePath"
                    @click="handleFileClick(bookmark.filePath)"
                    class="group p-3 bg-gray-800 hover:bg-gray-750 rounded border border-gray-700 hover:border-yellow-500 cursor-pointer transition-all"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-200 truncate">
                                    {{ getFileName(bookmark.filePath) }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 truncate pl-6">
                                {{ getDirectory(bookmark.filePath) }}
                            </div>
                            <div class="text-xs text-gray-600 mt-1 pl-6">
                                {{ formatDate(bookmark.addedAt) }}
                            </div>
                        </div>
                        <button
                            @click="handleRemoveClick(bookmark.filePath, $event)"
                            class="flex-shrink-0 p-1 text-yellow-400 hover:text-red-400 opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Remove bookmark"
                        >
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Optional: Error count badge -->
                    <div v-if="bookmark.errorCount" class="mt-2 pl-6">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-900 text-red-200">
                            {{ bookmark.errorCount }} {{ bookmark.errorCount === 1 ? 'error' : 'errors' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
