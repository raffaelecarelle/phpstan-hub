<script setup>
import { ref, watch, computed } from 'vue';
import Copyable from './Copyable.vue';

const props = defineProps({
    filePath: String,
    errors: Array,
    editorUrl: String,
    projectRoot: String,
    hostProjectRoot: String|null,
});

const fileContent = ref(null);
const tokens = ref([]);
const loading = ref(false);
const error = ref(null);
const collapsedSections = ref({});

// Minimum lines between errors to trigger collapsing
const COLLAPSE_THRESHOLD = 10;

// Fetch file content when filePath changes
watch(() => props.filePath, async (newPath) => {
    if (!newPath) {
        fileContent.value = null;
        return;
    }

    loading.value = true;
    error.value = null;
    collapsedSections.value = {};

    try {
        const response = await fetch('http://127.0.0.1:8081/api/file-content', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file: newPath }),
        });

        if (!response.ok) {
            throw new Error(`Failed to load file: ${response.statusText}`);
        }

        const data = await response.json();
        fileContent.value = data.content;
        tokens.value = data.tokens || [];
    } catch (err) {
        error.value = err.message;
        fileContent.value = null;
        tokens.value = [];
    } finally {
        loading.value = false;
    }
}, { immediate: true });

const lines = computed(() => {
    if (!fileContent.value) return [];
    return fileContent.value.split('\n');
});

// Group tokens by line for syntax highlighting
const tokensByLine = computed(() => {
    if (!tokens.value.length) return {};

    const grouped = {};
    tokens.value.forEach(token => {
        if (!grouped[token.line]) {
            grouped[token.line] = [];
        }
        grouped[token.line].push(token);
    });

    return grouped;
});

const errorLines = computed(() => {
    if (!props.errors) return new Set();
    return new Set(props.errors.map(e => e.line));
});

const errorsByLine = computed(() => {
    if (!props.errors) return {};
    const map = {};
    props.errors.forEach(error => {
        if (!map[error.line]) {
            map[error.line] = [];
        }
        map[error.line].push(error);
    });
    return map;
});

// Build sections: determine which line ranges should be collapsible
const sections = computed(() => {
    if (!lines.value.length || errorLines.value.size === 0) {
        return [];
    }

    const sortedErrorLines = Array.from(errorLines.value).sort((a, b) => a - b);
    const result = [];
    let lastErrorLine = 0;

    sortedErrorLines.forEach((errorLine, index) => {
        // Check gap between previous error and current error
        const gap = errorLine - lastErrorLine - 1;

        if (gap >= COLLAPSE_THRESHOLD) {
            // Create a collapsible section
            const startLine = lastErrorLine + 1;
            const endLine = errorLine - 1;
            result.push({
                type: 'collapsible',
                startLine,
                endLine,
                lineCount: gap,
                collapsed: true,
            });
        }

        // Add the error line
        result.push({
            type: 'error',
            line: errorLine,
        });

        lastErrorLine = errorLine;
    });

    // Handle trailing lines after last error
    const trailingGap = lines.value.length - lastErrorLine;
    if (trailingGap >= COLLAPSE_THRESHOLD) {
        result.push({
            type: 'collapsible',
            startLine: lastErrorLine + 1,
            endLine: lines.value.length,
            lineCount: trailingGap,
            collapsed: true,
        });
    }

    return result;
});

const toggleSection = (section) => {
    const key = `${section.startLine}-${section.endLine}`;
    collapsedSections.value[key] = !collapsedSections.value[key];
};

const isSectionCollapsed = (section) => {
    const key = `${section.startLine}-${section.endLine}`;
    return collapsedSections.value[key] !== false; // Default is collapsed
};

const getRelativePath = (filePath) => {
    if (!props.projectRoot || !filePath.startsWith(props.projectRoot)) {
        return filePath;
    }
    return filePath.substring(props.projectRoot.length + 1);
};

const getFileLink = (line) => {
    if (!props.editorUrl || !props.filePath) {
        return '#';
    }

    let finalPath = props.filePath;
    if (props.hostProjectRoot && props.projectRoot && props.filePath.startsWith(props.projectRoot)) {
        finalPath = props.hostProjectRoot + props.filePath.substring(props.projectRoot.length);
    }

    return props.editorUrl
        .replace('%%file%%', finalPath)
        .replace('%%line%%', line);
};

// Render a line with syntax highlighting
const renderLineWithTokens = (lineNum) => {
    const lineTokens = tokensByLine.value[lineNum];
    if (!lineTokens || lineTokens.length === 0) {
        // Fallback to plain text
        return lines.value[lineNum - 1] || '';
    }

    return lineTokens;
};
</script>

<template>
    <div class="h-full bg-gray-900 overflow-y-auto">
        <!-- Empty State -->
        <div v-if="!filePath" class="flex items-center justify-center h-full text-gray-500">
            <div class="text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-lg">Select a file to view its content</p>
            </div>
        </div>

        <!-- Loading State -->
        <div v-else-if="loading" class="flex items-center justify-center h-full">
            <div class="flex items-center text-gray-400">
                <svg class="animate-spin h-8 w-8 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-lg">Loading file content...</span>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center h-full">
            <div class="text-center text-red-400">
                <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-lg font-semibold mb-2">Error loading file</p>
                <p class="text-sm text-gray-500">{{ error }}</p>
            </div>
        </div>

        <!-- File Content -->
        <div v-else class="p-4">
            <!-- File Header -->
            <div class="bg-gray-800 border border-gray-700 rounded-t-lg p-4 sticky top-0 z-10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <Copyable :text="getRelativePath(filePath)">
                            <h3 class="text-lg font-semibold text-gray-200">{{ getRelativePath(filePath) }}</h3>
                        </Copyable>
                    </div>
                    <span class="text-sm text-gray-500">{{ errors?.length || 0 }} errors</span>
                </div>
            </div>

            <!-- Code Content -->
            <div class="bg-gray-850 border border-gray-700 border-t-0 rounded-b-lg overflow-hidden">
                <div class="font-mono text-sm">
                    <template v-for="(section, index) in sections" :key="index">
                        <!-- Collapsible Section -->
                        <div v-if="section.type === 'collapsible'">
                            <div
                                v-if="isSectionCollapsed(section)"
                                @click="toggleSection(section)"
                                class="bg-gray-800 border-y border-gray-700 px-4 py-2 cursor-pointer hover:bg-gray-750 transition-colors flex items-center justify-center"
                            >
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                <span class="text-gray-500 text-xs">
                                    {{ section.lineCount }} lines hidden (lines {{ section.startLine }} - {{ section.endLine }})
                                </span>
                            </div>

                            <div v-else>
                                <div
                                    @click="toggleSection(section)"
                                    class="bg-gray-800 border-y border-gray-700 px-4 py-2 cursor-pointer hover:bg-gray-750 transition-colors flex items-center justify-center"
                                >
                                    <svg class="w-4 h-4 mr-2 text-gray-500 transform rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                    <span class="text-gray-500 text-xs">Collapse {{ section.lineCount }} lines</span>
                                </div>

                                <div v-for="lineNum in (section.endLine - section.startLine + 1)" :key="lineNum" class="py-1">
                                    <div class="flex hover:bg-gray-800 transition-colors leading-tight">
                                        <div class="w-16 flex-shrink-0 text-right pr-4 text-gray-600 select-none border-r border-gray-700">
                                            {{ section.startLine + lineNum - 1 }}
                                        </div>
                                        <div class="flex-grow px-4 overflow-x-auto leading-tight">
                                            <code class="whitespace-pre"><template v-if="tokensByLine[section.startLine + lineNum - 1]"><span v-for="(token, tokenIdx) in tokensByLine[section.startLine + lineNum - 1]" :key="tokenIdx" :style="{ color: token.color }">{{ token.text }}</span></template><template v-else>{{ lines[section.startLine + lineNum - 2] || '' }}</template></code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Error Line Section -->
                        <div v-else-if="section.type === 'error'" class="bg-red-900 bg-opacity-20 border-l-4 border-red-500">
                            <div class="flex">
                                <a
                                    :href="getFileLink(section.line)"
                                    class="w-16 flex-shrink-0 text-right pr-4 py-1 text-red-400 font-semibold select-none border-r border-red-700 hover:underline"
                                >
                                    {{ section.line }}
                                </a>
                                <div class="flex-grow">
                                    <div class="px-4 overflow-x-auto leading-tight">
                                        <code class="whitespace-pre"><template v-if="tokensByLine[section.line]"><span v-for="(token, tokenIdx) in tokensByLine[section.line]" :key="tokenIdx" :style="{ color: token.color }">{{ token.text }}</span></template><template v-else>{{ lines[section.line - 1] || '' }}</template></code>
                                    </div>

                                    <!-- Error Messages -->
                                    <div class="px-4 pb-2">
                                        <div
                                            v-for="(err, errIndex) in errorsByLine[section.line]"
                                            :key="errIndex"
                                            class="mt-2 p-3 bg-gray-800 border border-red-700 rounded"
                                        >
                                            <Copyable :text="err.message">
                                                <p class="text-sm text-gray-300">{{ err.message }}</p>
                                            </Copyable>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
