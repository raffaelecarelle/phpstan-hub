<script setup>
import { ref, watch, computed, onMounted, onUnmounted, nextTick } from 'vue';
import Copyable from './Copyable.vue';
import QuickFixSuggestions from './QuickFixSuggestions.vue';

const props = defineProps({
    filePath: String,
    errors: Array,
    editorUrl: String,
    projectRoot: String,
    hostProjectRoot: String|null,
});

const emit = defineEmits(['error-ignored', 'checking-started', 'checking-finished']);

const fileContent = ref(null);
const tokens = ref([]);
const loading = ref(false);
const error = ref(null);
const collapsedSections = ref({});
const fadingOutErrors = ref(new Set()); // Track errors being removed

// Live editing state
const isEditingMode = ref(false);
const editedContent = ref('');
const modifiedLines = ref(new Set()); // Track which lines have been modified
const checkingInProgress = ref(false);
const focusedLine = ref(null); // Track which line has focus

// Virtual scrolling state
const containerRef = ref(null);
const scrollTop = ref(0);
const containerHeight = ref(800);
const LINE_HEIGHT = 24; // Fixed height per line in pixels
const BUFFER_SIZE = 20; // Number of lines to render outside viewport
const VIRTUAL_SCROLL_THRESHOLD = 1000; // Enable virtual scrolling for files > 1000 lines

// Minimum lines between errors to trigger collapsing
const COLLAPSE_THRESHOLD = 10;

// Fetch file content when filePath changes
watch(() => props.filePath, async (newPath, oldPath) => {
    if (!newPath) {
        fileContent.value = null;
        return;
    }

    // If changing to a different file, reset everything
    const isNewFile = newPath !== oldPath;

    loading.value = true;
    error.value = null;
    collapsedSections.value = {};
    scrollTop.value = 0;

    // Only reset edit state when changing files, not when reloading same file
    if (isNewFile) {
        isEditingMode.value = false;
        editedContent.value = '';
        modifiedLines.value = new Set();
    }

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

        // Only update editedContent if not in edit mode or if it's a new file
        if (!isEditingMode.value || isNewFile) {
            editedContent.value = data.content;
        }

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

const useVirtualScroll = computed(() => {
    return lines.value.length > VIRTUAL_SCROLL_THRESHOLD;
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

// Virtual scrolling: calculate visible line range
const visibleRange = computed(() => {
    if (!useVirtualScroll.value) {
        return { start: 1, end: lines.value.length };
    }

    const startLine = Math.max(1, Math.floor(scrollTop.value / LINE_HEIGHT) - BUFFER_SIZE);
    const endLine = Math.min(
        lines.value.length,
        Math.ceil((scrollTop.value + containerHeight.value) / LINE_HEIGHT) + BUFFER_SIZE
    );

    return { start: startLine, end: endLine };
});

// Calculate total height for virtual scroll container
const totalHeight = computed(() => {
    if (!useVirtualScroll.value) return 'auto';
    return `${lines.value.length * LINE_HEIGHT}px`;
});

// Calculate offset for visible content
const contentOffset = computed(() => {
    if (!useVirtualScroll.value) return 0;
    return (visibleRange.value.start - 1) * LINE_HEIGHT;
});

// Filtered sections for visible range
const visibleSections = computed(() => {
    if (!useVirtualScroll.value) return sections.value;

    return sections.value.filter(section => {
        if (section.type === 'error') {
            return section.line >= visibleRange.value.start && section.line <= visibleRange.value.end;
        } else {
            // Collapsible section
            return !(section.endLine < visibleRange.value.start || section.startLine > visibleRange.value.end);
        }
    });
});

const handleScroll = (event) => {
    scrollTop.value = event.target.scrollTop;
};

const updateContainerHeight = () => {
    if (containerRef.value) {
        containerHeight.value = containerRef.value.clientHeight;
    }
};

onMounted(() => {
    updateContainerHeight();
    window.addEventListener('resize', updateContainerHeight);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateContainerHeight);
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

const handleIgnoreError = async (error) => {
    try {
        const response = await fetch('http://127.0.0.1:8081/api/ignore-error', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file: props.filePath,
                error: error.message,
            }),
        });

        if (response.ok) {
            // Add to fading out set for animation
            const errorKey = `${error.line}-${error.message}`;
            fadingOutErrors.value.add(errorKey);

            // Wait for animation to complete, then emit event
            setTimeout(() => {
                fadingOutErrors.value.delete(errorKey);
                emit('error-ignored', {
                    file: props.filePath,
                    error: error.message,
                    line: error.line
                });
            }, 500); // Match the CSS transition duration
        } else {
            const errorData = await response.json();
            console.error('Failed to ignore error:', errorData);
            alert(`Failed to add error to ignore list: ${errorData.error || 'Unknown error'}`);
        }
    } catch (err) {
        console.error('Error ignoring error:', err);
        alert('Failed to add error to ignore list.');
    }
};

const handleApplyFix = (fixData) => {
    // For now, just copy the suggested code
    if (fixData.code) {
        navigator.clipboard.writeText(fixData.code);
        alert('Fix code copied to clipboard!');
    }
};

const isErrorFadingOut = (error) => {
    const errorKey = `${error.line}-${error.message}`;
    return fadingOutErrors.value.has(errorKey);
};

// Computed for edited lines
const editedLines = computed(() => {
    if (!editedContent.value) return [];
    return editedContent.value.split('\n');
});

// Live editing functions
const toggleEditMode = () => {
    isEditingMode.value = !isEditingMode.value;
    if (!isEditingMode.value) {
        // Reset to original content when exiting edit mode
        editedContent.value = fileContent.value;
        modifiedLines.value.clear();
    }
};

const handleLineEdit = (lineNumber, newContent) => {
    const linesArray = editedContent.value.split('\n');
    linesArray[lineNumber - 1] = newContent;
    editedContent.value = linesArray.join('\n');

    // Check which error lines have been modified
    const originalLines = fileContent.value.split('\n');

    modifiedLines.value.clear();
    errorLines.value.forEach(lineNum => {
        if (linesArray[lineNum - 1] !== originalLines[lineNum - 1]) {
            modifiedLines.value.add(lineNum);
        }
    });
};

const hasModifiedContent = computed(() => {
    return editedContent.value !== fileContent.value;
});

const hasModifiedErrorLine = computed(() => {
    return modifiedLines.value.size > 0;
});

const saveAndCheckErrors = async () => {
    if (!props.filePath || checkingInProgress.value) return;

    emit('checking-started');
    checkingInProgress.value = true;

    try {
        // Step 1: Save the file
        const saveResponse = await fetch('http://127.0.0.1:8081/api/save-file', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file: props.filePath,
                content: editedContent.value,
            }),
        });

        if (!saveResponse.ok) {
            const errorData = await saveResponse.json();
            throw new Error(errorData.error || 'Failed to save file');
        }

        // Update original content after successful save
        fileContent.value = editedContent.value;

        // Re-tokenize the saved content for syntax highlighting
        try {
            const tokenResponse = await fetch('http://127.0.0.1:8081/api/file-content', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ file: props.filePath }),
            });
            if (tokenResponse.ok) {
                const data = await tokenResponse.json();
                tokens.value = data.tokens || [];
            }
        } catch (tokenErr) {
            console.warn('Failed to re-tokenize, keeping old tokens:', tokenErr);
        }

        // Step 2: Trigger PHPStan check
        const checkResponse = await fetch('http://127.0.0.1:8081/api/check-error', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({}),
        });

        if (!checkResponse.ok) {
            throw new Error('Failed to trigger error check');
        }

        // Clear modified lines since we're checking now
        modifiedLines.value.clear();

        // The results will come through WebSocket and update automatically
    } catch (err) {
        console.error('Error saving and checking:', err);
        alert(`Error: ${err.message}`);
    } finally {
        checkingInProgress.value = false;
        emit('checking-finished');
    }
};

const cancelEdit = () => {
    editedContent.value = fileContent.value;
    modifiedLines.value.clear();
    isEditingMode.value = false;
};

// Watch focusedLine to auto-focus input
watch(focusedLine, (newLine) => {
    if (newLine !== null) {
        // Use nextTick to ensure DOM is updated
        const inputs = document.querySelectorAll('input[type="text"]');
        inputs.forEach(input => {
            if (input.offsetParent !== null) { // Check if visible
                input.focus();
            }
        });
    }
});

</script>

<template>
    <div class="h-full bg-gray-900 flex">
        <div class="flex-1 overflow-y-auto" ref="containerRef" @scroll="handleScroll">
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
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-500">{{ lines.length }} lines</span>
                            <span class="text-sm text-gray-500">{{ errors?.length || 0 }} errors</span>
                            <span v-if="useVirtualScroll" class="text-xs text-blue-400 bg-blue-900 bg-opacity-30 px-2 py-1 rounded">
                            Virtual Scroll
                        </span>

                            <!-- Edit Mode Toggle -->
                            <button
                                v-if="!isEditingMode"
                                @click="toggleEditMode"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded transition-colors flex items-center gap-2"
                                title="Enable live editing"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit
                            </button>

                            <!-- Edit Mode Actions -->
                            <div v-else class="flex items-center gap-2">
                            <span class="text-xs text-blue-400 bg-blue-900 bg-opacity-30 px-2 py-1 rounded">
                                Edit Mode
                            </span>
                                <span v-if="hasModifiedContent && !hasModifiedErrorLine" class="text-xs text-yellow-400 bg-yellow-900 bg-opacity-30 px-2 py-1 rounded">
                                Modified
                            </span>
                                <span v-if="hasModifiedErrorLine" class="text-xs text-orange-400 bg-orange-900 bg-opacity-30 px-2 py-1 rounded">
                                Error line modified
                            </span>
                                <button
                                    v-if="hasModifiedContent"
                                    @click="saveAndCheckErrors"
                                    :disabled="checkingInProgress"
                                    :class="[
                                    'px-4 py-2 text-white text-sm font-medium rounded transition-colors flex items-center gap-2',
                                    hasModifiedErrorLine
                                        ? 'bg-green-600 hover:bg-green-700'
                                        : 'bg-blue-600 hover:bg-blue-700',
                                    checkingInProgress ? 'bg-gray-600 cursor-not-allowed' : ''
                                ]"
                                    :title="hasModifiedErrorLine ? 'Save and check if errors are fixed' : 'Save and re-run PHPStan'"
                                >
                                    <svg v-if="!checkingInProgress" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <svg v-else class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ checkingInProgress ? 'Checking...' : 'Check' }}
                                </button>
                                <button
                                    @click="cancelEdit"
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded transition-colors"
                                    title="Cancel editing"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Code Content -->
                <div class="bg-gray-850 border border-gray-700 border-t-0 rounded-b-lg overflow-hidden">
                    <!-- Edit Mode: Editable Lines with Syntax Highlighting -->
                    <div v-if="isEditingMode" class="overflow-x-auto overflow-y-auto" style="max-height: 800px;">
                        <div class="inline-block min-w-full">
                            <template v-for="(line, index) in editedLines" :key="index">
                                <!-- Error Line -->
                                <div v-if="errorLines.has(index + 1)" class="bg-red-900 bg-opacity-20 border-l-4 border-red-500">
                                    <div class="flex">
                                        <a
                                            :href="getFileLink(index + 1)"
                                            class="w-16 flex-shrink-0 text-right pr-4 py-2 text-red-400 font-semibold select-none border-r border-red-700 hover:underline"
                                        >
                                            {{ index + 1 }}
                                        </a>
                                        <div class="flex-grow">
                                            <!-- Show syntax highlighted code when not focused -->
                                            <div
                                                v-if="focusedLine !== index + 1"
                                                @click="focusedLine = index + 1; $nextTick(() => $event.target.nextElementSibling?.focus())"
                                                class="px-4 py-2 cursor-text font-mono text-sm leading-6"
                                            >
                                                <code class="whitespace-pre">
                                                    <!-- Show edited content if line was modified, otherwise show tokens -->
                                                    <template v-if="line !== lines[index]">
                                                        <span class="text-gray-300">{{ line }}</span>
                                                    </template>
                                                    <template v-else-if="tokensByLine[index + 1]">
                                                        <span v-for="(token, tokenIdx) in tokensByLine[index + 1]" :key="tokenIdx" :style="{ color: token.color }">{{ token.text }}</span>
                                                    </template>
                                                    <template v-else>{{ line }}</template>
                                                </code>
                                            </div>
                                            <!-- Show input when focused -->
                                            <input
                                                v-else
                                                type="text"
                                                :value="line"
                                                @input="handleLineEdit(index + 1, $event.target.value)"
                                                @blur="focusedLine = null"
                                                class="w-full bg-transparent text-gray-300 font-mono text-sm px-4 py-2 border-none focus:outline-none focus:bg-gray-800 focus:bg-opacity-30 focus:ring-2 focus:ring-yellow-500"
                                                spellcheck="false"
                                                style="min-width: 100%;"
                                                ref="errorLineInput"
                                            />
                                        </div>
                                    </div>
                                    <!-- Error Messages -->
                                    <div class="px-4 pb-2" v-if="errorsByLine[index + 1]">
                                        <TransitionGroup name="fade" tag="div">
                                            <div
                                                v-for="(err, errIndex) in errorsByLine[index + 1]"
                                                :key="`${err.line}-${err.message}`"
                                                v-show="!isErrorFadingOut(err)"
                                                class="mt-2"
                                            >
                                                <div class="p-3 bg-gray-800 border border-red-700 rounded">
                                                    <Copyable :text="err.message">
                                                        <p class="text-sm text-gray-300">{{ err.message }}</p>
                                                    </Copyable>
                                                </div>
                                            </div>
                                        </TransitionGroup>
                                    </div>
                                </div>

                                <!-- Normal Line -->
                                <div v-else class="flex hover:bg-gray-800 transition-colors">
                                    <div class="w-16 flex-shrink-0 text-right pr-4 py-2 text-gray-600 select-none border-r border-gray-700">
                                        {{ index + 1 }}
                                    </div>
                                    <div class="flex-grow">
                                        <!-- Show syntax highlighted code when not focused -->
                                        <div
                                            v-if="focusedLine !== index + 1"
                                            @click="focusedLine = index + 1; $nextTick(() => $event.target.nextElementSibling?.focus())"
                                            class="px-4 py-2 cursor-text font-mono text-sm leading-6"
                                        >
                                            <code class="whitespace-pre">
                                                <!-- Show edited content if line was modified, otherwise show tokens -->
                                                <template v-if="line !== lines[index]">
                                                    <span class="text-gray-300">{{ line }}</span>
                                                </template>
                                                <template v-else-if="tokensByLine[index + 1]">
                                                    <span v-for="(token, tokenIdx) in tokensByLine[index + 1]" :key="tokenIdx" :style="{ color: token.color }">{{ token.text }}</span>
                                                </template>
                                                <template v-else>{{ line }}</template>
                                            </code>
                                        </div>
                                        <!-- Show input when focused -->
                                        <input
                                            v-else
                                            type="text"
                                            :value="line"
                                            @input="handleLineEdit(index + 1, $event.target.value)"
                                            @blur="focusedLine = null"
                                            class="w-full bg-transparent text-gray-300 font-mono text-sm px-4 py-2 border-none focus:outline-none focus:bg-gray-800 focus:bg-opacity-50 focus:ring-2 focus:ring-blue-500"
                                            spellcheck="false"
                                            style="min-width: 100%;"
                                            ref="normalLineInput"
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- View Mode: Syntax Highlighted with Sections -->
                    <div v-else class="font-mono text-sm" :style="{ height: totalHeight, position: 'relative' }">
                        <div :style="{ transform: `translateY(${contentOffset}px)` }">
                            <template v-for="(section, index) in visibleSections" :key="index">
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

                                        <div v-for="lineNum in (section.endLine - section.startLine + 1)" :key="lineNum" class="py-1" :style="{ height: LINE_HEIGHT + 'px' }">
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
                                            class="py-2 w-16 flex-shrink-0 text-right pr-4 py-1 text-red-400 font-semibold select-none border-r border-red-700 hover:underline"
                                        >
                                            {{ section.line }}
                                        </a>
                                        <div class="flex-grow">
                                            <div class="px-4 py-2 overflow-x-auto leading-tight">
                                                <code class="whitespace-pre"><template v-if="tokensByLine[section.line]"><span v-for="(token, tokenIdx) in tokensByLine[section.line]" :key="tokenIdx" :style="{ color: token.color }">{{ token.text }}</span></template><template v-else>{{ lines[section.line - 1] || '' }}</template></code>
                                            </div>

                                            <!-- Error Messages -->
                                            <div class="px-4 pb-2">
                                                <TransitionGroup name="fade" tag="div">
                                                    <div
                                                        v-for="(err, errIndex) in errorsByLine[section.line]"
                                                        :key="`${err.line}-${err.message}`"
                                                        v-show="!isErrorFadingOut(err)"
                                                        class="mt-2 space-y-2"
                                                    >
                                                        <div class="p-3 bg-gray-800 border border-red-700 rounded">
                                                            <Copyable :text="err.message">
                                                                <p class="text-sm text-gray-300">{{ err.message }}</p>
                                                            </Copyable>
                                                        </div>
                                                        <!--                                                <QuickFixSuggestions-->
                                                        <!--                                                    :error="err"-->
                                                        <!--                                                    :file-path="filePath"-->
                                                        <!--                                                    :project-root="projectRoot"-->
                                                        <!--                                                    @ignore-error="handleIgnoreError"-->
                                                        <!--                                                    @apply-fix="handleApplyFix"-->
                                                        <!--                                                />-->
                                                    </div>
                                                </TransitionGroup>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease, transform 0.5s ease;
}

.fade-enter-from {
    opacity: 0;
    transform: translateX(-10px);
}

.fade-leave-to {
    opacity: 0;
    transform: translateX(10px);
}

/* Custom horizontal scrollbar */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: rgba(17, 24, 39, 0.5);
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: rgb(75, 85, 99);
    border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: rgb(107, 114, 128);
}

/* Firefox scrollbar */
.overflow-x-auto {
    scrollbar-width: thin;
    scrollbar-color: rgba(75, 85, 99, 0.8) rgba(17, 24, 39, 0.5);
}

/* Edit mode input styling - no word wrap, scroll horizontal */
input[type="text"] {
    white-space: nowrap;
    overflow: visible;
}

/* Prevent text wrapping in edit mode */
.inline-block {
    white-space: nowrap;
}
</style>
