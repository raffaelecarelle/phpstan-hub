<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
    show: Boolean,
});

const emit = defineEmits(['close']);

const shortcuts = [
    {
        category: 'Navigation',
        items: [
            { key: 'Ctrl+J', description: 'Next file in list' },
            { key: 'Ctrl+K', description: 'Previous file in list' },
            { key: 'Ctrl+G', description: 'Go to line' },
            { key: 'Ctrl+B', description: 'Toggle sidebar' },
        ],
    },
    {
        category: 'Search',
        items: [
            { key: 'Ctrl+F', description: 'Search in current file' },
            { key: 'Ctrl+P', description: 'Quick file search' },
            { key: 'Ctrl+Shift+F', description: 'Global search in files' },
        ],
    },
    {
        category: 'Bookmarks',
        items: [
            { key: 'Ctrl+D', description: 'Toggle bookmark on current file' },
            { key: 'Ctrl+Shift+B', description: 'Show bookmarks panel' },
        ],
    },
    {
        category: 'Views',
        items: [
            { key: 'Alt+1', description: 'Switch to Files view' },
            { key: 'Alt+2', description: 'Switch to Search view' },
            { key: 'Alt+3', description: 'Switch to Bookmarks view' },
        ],
    },
    {
        category: 'Actions',
        items: [
            { key: 'Ctrl+R', description: 'Run PHPStan analysis' },
            { key: 'Ctrl+W', description: 'Close current file' },
            { key: 'Esc', description: 'Close modals/panels' },
        ],
    },
];

const formatKey = (key) => {
    return key.replace('Ctrl', '⌘').replace('Alt', '⌥').replace('Shift', '⇧');
};

const handleClose = () => {
    emit('close');
};

const handleBackdropClick = (event) => {
    if (event.target === event.currentTarget) {
        handleClose();
    }
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                @click="handleBackdropClick"
                class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4"
            >
                <div
                    class="bg-gray-800 rounded-lg shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden border border-gray-700"
                    @click.stop
                >
                    <!-- Header -->
                    <div class="bg-gray-750 px-6 py-4 border-b border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-white">Keyboard Shortcuts</h2>
                                <p class="text-sm text-gray-400">Speed up your workflow</p>
                            </div>
                        </div>
                        <button
                            @click="handleClose"
                            class="text-gray-400 hover:text-white transition-colors p-2 hover:bg-gray-700 rounded"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-100px)]">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div
                                v-for="(category, index) in shortcuts"
                                :key="index"
                                class="bg-gray-850 rounded-lg p-4 border border-gray-700"
                            >
                                <h3 class="text-sm font-semibold text-blue-400 mb-3 flex items-center gap-2">
                                    <span class="w-1 h-4 bg-blue-500 rounded"></span>
                                    {{ category.category }}
                                </h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="(item, itemIndex) in category.items"
                                        :key="itemIndex"
                                        class="flex items-center justify-between py-2 border-b border-gray-750 last:border-0"
                                    >
                                        <span class="text-sm text-gray-300">{{ item.description }}</span>
                                        <div class="flex items-center gap-1">
                                            <kbd
                                                v-for="(part, partIndex) in item.key.split('+')"
                                                :key="partIndex"
                                                class="px-2 py-1 text-xs font-mono bg-gray-700 text-gray-200 rounded border border-gray-600 shadow-sm"
                                            >
                                                {{ part }}
                                            </kbd>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Note -->
                        <div class="mt-6 p-4 bg-blue-900 bg-opacity-20 border border-blue-700 rounded-lg">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div class="text-sm text-blue-300">
                                    <p class="font-medium mb-1">Pro Tip</p>
                                    <p class="text-blue-200 text-opacity-80">
                                        Press <kbd class="px-2 py-0.5 text-xs font-mono bg-blue-800 rounded">Ctrl+/</kbd>
                                        at any time to show this shortcuts panel.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="bg-gray-750 px-6 py-3 border-t border-gray-700 flex justify-end">
                        <button
                            @click="handleClose"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition-colors font-medium"
                        >
                            Got it!
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
