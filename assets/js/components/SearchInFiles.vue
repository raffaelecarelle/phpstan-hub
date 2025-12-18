<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    files: Object, // All files from analysis results
    projectRoot: String,
});

const emit = defineEmits(['file-selected', 'line-selected']);

const searchQuery = ref('');
const searchResults = ref([]);
const isSearching = ref(false);
const caseSensitive = ref(false);
const useRegex = ref(false);
const searchInContent = ref(true);
const searchInFilenames = ref(true);

const hasResults = computed(() => searchResults.value.length > 0);
const resultCount = computed(() => {
    return searchResults.value.reduce((sum, file) => sum + file.matches.length, 0);
});

// Debounced search
let searchTimeout = null;
watch([searchQuery, caseSensitive, useRegex, searchInContent, searchInFilenames], () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    if (!searchQuery.value) {
        searchResults.value = [];
        return;
    }
    searchTimeout = setTimeout(() => performSearch(), 300);
});

const performSearch = async () => {
    if (!searchQuery.value || !props.files) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    const results = [];

    try {
        let pattern;
        if (useRegex.value) {
            const flags = caseSensitive.value ? 'g' : 'gi';
            pattern = new RegExp(searchQuery.value, flags);
        } else {
            const escapedQuery = searchQuery.value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const flags = caseSensitive.value ? 'g' : 'gi';
            pattern = new RegExp(escapedQuery, flags);
        }

        for (const [filePath, fileData] of Object.entries(props.files)) {
            const matches = [];

            // Search in filename
            if (searchInFilenames.value) {
                const relativePath = getRelativePath(filePath);
                if (pattern.test(relativePath)) {
                    matches.push({
                        type: 'filename',
                        text: relativePath,
                        line: 0,
                    });
                }
            }

            // Search in content
            if (searchInContent.value) {
                try {
                    const response = await fetch('http://127.0.0.1:8081/api/file-content', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ file: filePath }),
                    });

                    if (response.ok) {
                        const data = await response.json();
                        const lines = data.content.split('\n');

                        lines.forEach((line, index) => {
                            if (pattern.test(line)) {
                                const matchPositions = [];
                                let match;
                                pattern.lastIndex = 0; // Reset regex
                                while ((match = pattern.exec(line)) !== null) {
                                    matchPositions.push({
                                        start: match.index,
                                        end: match.index + match[0].length,
                                    });
                                    if (!pattern.global) break;
                                }

                                matches.push({
                                    type: 'content',
                                    text: line,
                                    line: index + 1,
                                    matchPositions,
                                });
                            }
                        });
                    }
                } catch (err) {
                    console.error(`Error searching in ${filePath}:`, err);
                }
            }

            if (matches.length > 0) {
                results.push({
                    filePath,
                    relativePath: getRelativePath(filePath),
                    matches,
                });
            }
        }

        searchResults.value = results;
    } catch (err) {
        console.error('Search error:', err);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

const getRelativePath = (filePath) => {
    if (!props.projectRoot || !filePath.startsWith(props.projectRoot)) {
        return filePath;
    }
    return filePath.substring(props.projectRoot.length + 1);
};

const selectFile = (filePath) => {
    emit('file-selected', filePath);
};

const selectLine = (filePath, lineNumber) => {
    emit('file-selected', filePath);
    emit('line-selected', lineNumber);
};

const highlightMatch = (text, matchPositions) => {
    if (!matchPositions || matchPositions.length === 0) {
        return text;
    }

    let result = '';
    let lastIndex = 0;

    matchPositions.forEach(pos => {
        result += text.substring(lastIndex, pos.start);
        result += `<mark class="bg-yellow-400 text-gray-900">${text.substring(pos.start, pos.end)}</mark>`;
        lastIndex = pos.end;
    });

    result += text.substring(lastIndex);
    return result;
};

const clearSearch = () => {
    searchQuery.value = '';
    searchResults.value = [];
};
</script>

<template>
    <div class="h-full flex flex-col bg-gray-900">
        <!-- Search Header -->
        <div class="p-4 bg-gray-800 border-b border-gray-700">
            <div class="flex items-center gap-2 mb-3">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search in files..."
                    class="flex-1 px-3 py-2 bg-gray-700 text-white border border-gray-600 rounded focus:outline-none focus:border-blue-500"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="p-2 text-gray-400 hover:text-white hover:bg-gray-700 rounded transition-colors"
                    title="Clear search"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Search Options -->
            <div class="flex flex-wrap gap-3 text-sm">
                <label class="flex items-center gap-2 cursor-pointer text-gray-300 hover:text-white transition-colors group">
                    <input
                        type="checkbox"
                        v-model="caseSensitive"
                        class="w-4 h-4 bg-gray-700 border-2 border-gray-600 rounded text-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 focus:ring-offset-gray-800 cursor-pointer transition-all checked:bg-blue-600 checked:border-blue-600 hover:border-blue-500"
                    >
                    <span class="select-none">Case sensitive</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-gray-300 hover:text-white transition-colors group">
                    <input
                        type="checkbox"
                        v-model="useRegex"
                        class="w-4 h-4 bg-gray-700 border-2 border-gray-600 rounded text-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 focus:ring-offset-gray-800 cursor-pointer transition-all checked:bg-blue-600 checked:border-blue-600 hover:border-blue-500"
                    >
                    <span class="select-none">Regex</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-gray-300 hover:text-white transition-colors group">
                    <input
                        type="checkbox"
                        v-model="searchInFilenames"
                        class="w-4 h-4 bg-gray-700 border-2 border-gray-600 rounded text-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 focus:ring-offset-gray-800 cursor-pointer transition-all checked:bg-blue-600 checked:border-blue-600 hover:border-blue-500"
                    >
                    <span class="select-none">Filenames</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-gray-300 hover:text-white transition-colors group">
                    <input
                        type="checkbox"
                        v-model="searchInContent"
                        class="w-4 h-4 bg-gray-700 border-2 border-gray-600 rounded text-blue-500 focus:ring-2 focus:ring-blue-500 focus:ring-offset-0 focus:ring-offset-gray-800 cursor-pointer transition-all checked:bg-blue-600 checked:border-blue-600 hover:border-blue-500"
                    >
                    <span class="select-none">Content</span>
                </label>
            </div>

            <!-- Results Count -->
            <div v-if="searchQuery" class="mt-3 text-sm text-gray-400">
                <span v-if="isSearching">Searching...</span>
                <span v-else-if="hasResults">
                    Found {{ resultCount }} {{ resultCount === 1 ? 'match' : 'matches' }} in {{ searchResults.length }} {{ searchResults.length === 1 ? 'file' : 'files' }}
                </span>
                <span v-else>No results</span>
            </div>
        </div>

        <!-- Search Results -->
        <div class="flex-1 overflow-y-auto">
            <div v-if="!searchQuery" class="flex items-center justify-center h-full text-gray-500">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <p class="text-lg">Enter a search query</p>
                </div>
            </div>

            <div v-else-if="isSearching" class="flex items-center justify-center h-full">
                <div class="flex items-center text-gray-400">
                    <svg class="animate-spin h-8 w-8 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Searching...</span>
                </div>
            </div>

            <div v-else-if="hasResults" class="p-4 space-y-4">
                <div v-for="(result, index) in searchResults" :key="index" class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden">
                    <!-- File Header -->
                    <div
                        @click="selectFile(result.filePath)"
                        class="px-4 py-3 bg-gray-750 cursor-pointer hover:bg-gray-700 transition-colors flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-gray-200">{{ result.relativePath }}</span>
                        </div>
                        <span class="text-xs text-gray-500">{{ result.matches.length }} {{ result.matches.length === 1 ? 'match' : 'matches' }}</span>
                    </div>

                    <!-- Matches -->
                    <div class="divide-y divide-gray-700">
                        <div
                            v-for="(match, matchIndex) in result.matches"
                            :key="matchIndex"
                            @click="match.type === 'content' && selectLine(result.filePath, match.line)"
                            class="px-4 py-2 hover:bg-gray-750 transition-colors cursor-pointer"
                        >
                            <div class="flex items-start gap-3">
                                <span v-if="match.type === 'content'" class="text-xs text-gray-500 font-mono w-12 flex-shrink-0 text-right">
                                    {{ match.line }}
                                </span>
                                <span v-else class="text-xs text-blue-400 font-semibold w-12 flex-shrink-0">
                                    NAME
                                </span>
                                <code
                                    class="text-sm font-mono text-gray-300 flex-1 overflow-x-auto"
                                    v-html="highlightMatch(match.text, match.matchPositions)"
                                ></code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
