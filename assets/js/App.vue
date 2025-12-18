<script setup>
import { ref, onMounted, computed } from 'vue';
import ControlPanel from './components/ControlPanel.vue';
import ResultsList from './components/ResultsList.vue';
import ExplorerView from './components/ExplorerView.vue';
import SettingsDropdown from './components/SettingsDropdown.vue';

const results = ref(null);
const status = ref('idle'); // idle, running
const viewMode = ref('grouped'); // grouped, individual, explorer
const config = ref({
    paths: [],
    level: 5,
    availablePaths: [],
    editorUrl: 'idea://open?file=%%file%%&line=%%line%%',
    projectRoot: '',
    hostProjectRoot: null,
});

const errorCount = computed(() => {
    if (!results.value || !results.value.files) {
        return 0;
    }
    return Object.values(results.value.files).reduce((count, file) => count + file.messages.length, 0);
});

const connectWebSocket = () => {
    const socket = new WebSocket('ws://127.0.0.1:8082');
    socket.onopen = () => console.log('WebSocket connected');
    socket.onmessage = (event) => {
        try {
            results.value = JSON.parse(event.data);
        } catch (e) {
            console.error('Failed to parse WebSocket data:', e);
        } finally {
            status.value = 'idle';
        }
    };
    socket.onclose = () => {
        console.log('WebSocket disconnected. Retrying in 3 seconds...');
        setTimeout(connectWebSocket, 3000);
    };
};

const fetchConfig = async () => {
    try {
        const response = await fetch('http://127.0.0.1:8081/api/config');
        config.value = await response.json();
    } catch (error) {
        console.error('Error fetching config:', error);
    }
};

const runAnalysis = (params) => {
    status.value = 'running';
    results.value = null;
    fetch('http://127.0.0.1:8081/api/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(params),
    });
};

const handleViewChange = (mode) => {
    viewMode.value = mode;
};

const saveSettings = (settings) => {
    config.value.paths = settings.paths;
    config.value.level = settings.level;
    runAnalysis(settings);
};

onMounted(() => {
    fetchConfig();
    connectWebSocket();
});
</script>

<template>
    <div id="app" class="flex flex-col h-screen">
        <!-- Header -->
        <header class="bg-gray-800 shadow-md p-4 flex justify-between items-center z-10 relative">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold text-white">PhpStanHub</h1>
            </div>

            <div class="absolute left-1/2 transform -translate-x-1/2">
                <div
                     :class="[
                         'w-24 h-12 flex items-center justify-center rounded-lg text-white font-bold text-2xl',
                         errorCount > 0 ? 'bg-red-500' : 'bg-green-500'
                     ]"
                >
                    {{ errorCount }}
                </div>
            </div>

            <div class="flex items-center space-x-4">
                <SettingsDropdown
                    :config="config"
                    :current-view-mode="viewMode"
                    @save="saveSettings"
                    @view-changed="handleViewChange"
                />
                <ControlPanel
                    @run-analysis="runAnalysis"
                    @view-changed="handleViewChange"
                    :is-running="status === 'running'"
                    :config="config"
                />
            </div>
        </header>

        <!-- Main Container -->
        <div class="flex flex-grow overflow-hidden">
            <ResultsList
                v-if="viewMode === 'grouped' || viewMode === 'individual'"
                :results="results"
                :status="status"
                :view-mode="viewMode"
                :editor-url="config.editorUrl"
                :project-root="config.projectRoot"
                :host-project-root="config.hostProjectRoot"
            />
            <ExplorerView
                v-else-if="viewMode === 'explorer'"
                :results="results"
                :status="status"
                :editor-url="config.editorUrl"
                :project-root="config.projectRoot"
                :host-project-root="config.hostProjectRoot"
            />
        </div>
    </div>
</template>
