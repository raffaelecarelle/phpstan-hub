<script setup>
import { computed } from 'vue';

const props = defineProps({
    before: String,
    after: String,
    fileName: String,
    viewMode: {
        type: String,
        default: 'split', // 'split' or 'unified'
    },
});

// Simple line-by-line diff algorithm
const computeDiff = () => {
    const oldLines = (props.before || '').split('\n');
    const newLines = (props.after || '').split('\n');

    const diff = [];
    let oldIndex = 0;
    let newIndex = 0;

    while (oldIndex < oldLines.length || newIndex < newLines.length) {
        const oldLine = oldLines[oldIndex];
        const newLine = newLines[newIndex];

        if (oldIndex >= oldLines.length) {
            // Only new lines left
            diff.push({ type: 'added', content: newLine, oldNum: null, newNum: newIndex + 1 });
            newIndex++;
        } else if (newIndex >= newLines.length) {
            // Only old lines left
            diff.push({ type: 'removed', content: oldLine, oldNum: oldIndex + 1, newNum: null });
            oldIndex++;
        } else if (oldLine === newLine) {
            // Lines are the same
            diff.push({ type: 'unchanged', content: oldLine, oldNum: oldIndex + 1, newNum: newIndex + 1 });
            oldIndex++;
            newIndex++;
        } else {
            // Lines differ - look ahead to see if we can find a match
            let foundMatch = false;
            const lookAhead = 3;

            // Look ahead in new lines
            for (let i = 1; i <= lookAhead && newIndex + i < newLines.length; i++) {
                if (oldLine === newLines[newIndex + i]) {
                    // Found match - mark intermediate lines as added
                    for (let j = 0; j < i; j++) {
                        diff.push({ type: 'added', content: newLines[newIndex + j], oldNum: null, newNum: newIndex + j + 1 });
                    }
                    newIndex += i;
                    foundMatch = true;
                    break;
                }
            }

            if (!foundMatch) {
                // Look ahead in old lines
                for (let i = 1; i <= lookAhead && oldIndex + i < oldLines.length; i++) {
                    if (newLine === oldLines[oldIndex + i]) {
                        // Found match - mark intermediate lines as removed
                        for (let j = 0; j < i; j++) {
                            diff.push({ type: 'removed', content: oldLines[oldIndex + j], oldNum: oldIndex + j + 1, newNum: null });
                        }
                        oldIndex += i;
                        foundMatch = true;
                        break;
                    }
                }
            }

            if (!foundMatch) {
                // No match found - treat as modification (remove + add)
                diff.push({ type: 'removed', content: oldLine, oldNum: oldIndex + 1, newNum: null });
                diff.push({ type: 'added', content: newLine, oldNum: null, newNum: newIndex + 1 });
                oldIndex++;
                newIndex++;
            }
        }
    }

    return diff;
};

const diffLines = computed(() => computeDiff());

const stats = computed(() => {
    let added = 0;
    let removed = 0;
    let unchanged = 0;

    diffLines.value.forEach(line => {
        if (line.type === 'added') added++;
        else if (line.type === 'removed') removed++;
        else unchanged++;
    });

    return { added, removed, unchanged };
});

const getLineClass = (line) => {
    switch (line.type) {
        case 'added':
            return 'bg-green-900 bg-opacity-30 border-l-4 border-green-500';
        case 'removed':
            return 'bg-red-900 bg-opacity-30 border-l-4 border-red-500';
        case 'unchanged':
            return 'bg-gray-850';
        default:
            return '';
    }
};

const getLinePrefix = (line) => {
    switch (line.type) {
        case 'added':
            return '+';
        case 'removed':
            return '-';
        default:
            return ' ';
    }
};

const getLinePrefixClass = (line) => {
    switch (line.type) {
        case 'added':
            return 'text-green-400 font-bold';
        case 'removed':
            return 'text-red-400 font-bold';
        default:
            return 'text-gray-600';
    }
};

// For split view
const splitDiff = computed(() => {
    const before = [];
    const after = [];

    diffLines.value.forEach(line => {
        if (line.type === 'removed') {
            before.push(line);
        } else if (line.type === 'added') {
            after.push(line);
        } else {
            before.push(line);
            after.push(line);
        }
    });

    return { before, after };
});
</script>

<template>
    <div class="bg-gray-900 rounded-lg overflow-hidden border border-gray-700">
        <!-- Header -->
        <div class="bg-gray-800 px-4 py-3 border-b border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                    <h3 class="text-sm font-semibold text-gray-200">{{ fileName || 'Diff View' }}</h3>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span>
                        <span class="text-green-400">+{{ stats.added }}</span>
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-2 h-2 bg-red-500 rounded-full"></span>
                        <span class="text-red-400">-{{ stats.removed }}</span>
                    </span>
                    <span class="text-gray-500">{{ stats.unchanged }} unchanged</span>
                </div>
            </div>
        </div>

        <!-- Unified Diff View -->
        <div v-if="viewMode === 'unified'" class="font-mono text-sm overflow-x-auto">
            <div
                v-for="(line, index) in diffLines"
                :key="index"
                :class="['flex hover:bg-gray-800 transition-colors', getLineClass(line)]"
            >
                <!-- Line Numbers -->
                <div class="flex-shrink-0 flex">
                    <div class="w-12 text-right pr-2 text-gray-600 select-none border-r border-gray-700">
                        {{ line.oldNum || '' }}
                    </div>
                    <div class="w-12 text-right pr-2 text-gray-600 select-none border-r border-gray-700">
                        {{ line.newNum || '' }}
                    </div>
                </div>

                <!-- Line Prefix -->
                <div :class="['w-6 flex-shrink-0 text-center select-none', getLinePrefixClass(line)]">
                    {{ getLinePrefix(line) }}
                </div>

                <!-- Line Content -->
                <div class="flex-grow px-2 py-1">
                    <code class="whitespace-pre text-gray-300">{{ line.content }}</code>
                </div>
            </div>
        </div>

        <!-- Split Diff View -->
        <div v-else class="grid grid-cols-2 gap-0 divide-x divide-gray-700">
            <!-- Before Column -->
            <div class="overflow-x-auto">
                <div class="bg-gray-800 px-3 py-2 text-xs font-semibold text-red-400 border-b border-gray-700">
                    Before
                </div>
                <div class="font-mono text-sm">
                    <div
                        v-for="(line, index) in splitDiff.before"
                        :key="`before-${index}`"
                        :class="['flex hover:bg-gray-800 transition-colors', getLineClass(line)]"
                    >
                        <div class="w-12 flex-shrink-0 text-right pr-2 text-gray-600 select-none border-r border-gray-700">
                            {{ line.oldNum || '' }}
                        </div>
                        <div :class="['w-6 flex-shrink-0 text-center select-none', getLinePrefixClass(line)]">
                            {{ line.type === 'removed' ? '-' : ' ' }}
                        </div>
                        <div class="flex-grow px-2 py-1">
                            <code class="whitespace-pre text-gray-300">{{ line.content }}</code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- After Column -->
            <div class="overflow-x-auto">
                <div class="bg-gray-800 px-3 py-2 text-xs font-semibold text-green-400 border-b border-gray-700">
                    After
                </div>
                <div class="font-mono text-sm">
                    <div
                        v-for="(line, index) in splitDiff.after"
                        :key="`after-${index}`"
                        :class="['flex hover:bg-gray-800 transition-colors', getLineClass(line)]"
                    >
                        <div class="w-12 flex-shrink-0 text-right pr-2 text-gray-600 select-none border-r border-gray-700">
                            {{ line.newNum || '' }}
                        </div>
                        <div :class="['w-6 flex-shrink-0 text-center select-none', getLinePrefixClass(line)]">
                            {{ line.type === 'added' ? '+' : ' ' }}
                        </div>
                        <div class="flex-grow px-2 py-1">
                            <code class="whitespace-pre text-gray-300">{{ line.content }}</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="diffLines.length === 0" class="p-8 text-center text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p>No differences to show</p>
        </div>
    </div>
</template>
