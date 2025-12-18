<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    config: Object,
    currentViewMode: String,
});

const show = ref(false);

const emit = defineEmits(['save', 'view-changed']);

const selectedPaths = ref([]);
const level = ref(5);
const generateBaseline = ref(false);

watch(() => props.config, (newConfig) => {
    if (newConfig) {
        selectedPaths.value = newConfig.paths || [];
        level.value = newConfig.level || 5;
    }
}, { immediate: true });

const save = () => {
    emit('save', {
        paths: selectedPaths.value,
        level: level.value,
        generateBaseline: generateBaseline.value,
    });
    show.value = false;
};

const close = () => {
    show.value = false;
};

const toggle = () => {
    show.value = !show.value;
};
</script>

<template>
    <div class="relative inline-block text-left">
        <div>
            <button @click="toggle" type="button" class="inline-flex justify-center w-full rounded-md border border-gray-700 shadow-sm px-4 py-2 bg-gray-800 text-sm font-medium text-gray-300 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-blue-500" id="options-menu" aria-haspopup="true" aria-expanded="true">
                Settings
                <svg class="-mr-1 ml-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <div v-if="show" class="origin-top-right absolute right-0 mt-2 w-96 rounded-md shadow-2xl bg-gray-800 ring-1 ring-black ring-opacity-5 z-50 border border-gray-700">
            <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                <div class="px-4 py-2">
                    <h2 class="text-xl font-bold text-white">Settings</h2>
                </div>
                <!-- Content -->
                <div class="px-6 py-4 space-y-6">
                    <!-- Paths Selection -->
                    <div>
                        <label for="settings-paths" class="block text-sm font-medium text-gray-300 mb-2">
                            Analysis Paths
                        </label>
                        <select
                            id="settings-paths"
                            v-model="selectedPaths"
                            multiple
                            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-gray-200 focus:ring-blue-500 focus:border-blue-500 min-h-[150px]"
                        >
                            <option
                                v-for="path in config.availablePaths"
                                :key="path"
                                :value="path"
                                class="py-1"
                            >
                                {{ path }}
                            </option>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">
                            Hold Ctrl (Cmd on Mac) to select multiple paths. Paths are automatically detected from phpstan.neon or composer.json autoload
                        </p>
                    </div>

                    <!-- Level Selection -->
                    <div>
                        <label for="settings-level" class="block text-sm font-medium text-gray-300 mb-2">
                            Analysis Level
                        </label>
                        <select
                            id="settings-level"
                            v-model="level"
                            class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-gray-200 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option v-for="n in 11" :key="n-1" :value="n-1">Level {{ n-1 }}</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">
                            Higher levels perform more thorough analysis
                        </p>
                    </div>

                    <!-- Generate Baseline -->
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" v-model="generateBaseline" class="form-checkbox h-5 w-5 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500">
                            <span class="ml-2 text-gray-300">Generate baseline</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1">
                            This will create a baseline file with the current errors.
                        </p>
                    </div>

                    <!-- View Mode Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Default View Mode
                        </label>
                        <div class="flex rounded-md overflow-hidden">
                            <button
                                @click="$emit('view-changed', 'grouped')"
                                :class="[
                                    'flex-1 px-3 py-2 text-xs font-medium',
                                    currentViewMode === 'grouped'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                                ]"
                                title="Group errors by file"
                            >
                                <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                                Grouped
                            </button>
                            <button
                                @click="$emit('view-changed', 'individual')"
                                :class="[
                                    'flex-1 px-3 py-2 text-xs font-medium border-l border-gray-600',
                                    currentViewMode === 'individual'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                                ]"
                                title="Show all errors in a flat list"
                            >
                                <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                Individual
                            </button>
                            <button
                                @click="$emit('view-changed', 'explorer')"
                                :class="[
                                    'flex-1 px-3 py-2 text-xs font-medium border-l border-gray-600',
                                    currentViewMode === 'explorer'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                                ]"
                                title="Browse files in a tree with inline code viewer"
                            >
                                <svg class="w-4 h-4 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                Explorer
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end space-x-3 px-6 py-4 border-t border-gray-700">
                    <button
                        @click="save"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        Save & Run
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
