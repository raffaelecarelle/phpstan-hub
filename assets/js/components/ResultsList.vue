<script setup>
import { computed, ref, watch } from 'vue';
import Copyable from './Copyable.vue';

const props = defineProps({
    results: Object,
    status: String,
    viewMode: String,
    editorUrl: String,
    projectRoot: String,
    hostProjectRoot: String|null,
});

const identifierColorCache = ref({});

const getIdentifierBackgroundColor = (identifier) => {
    if (!identifier) return {};
    if (!identifierColorCache.value[identifier]) {
        const r = Math.floor(Math.random() * 150);
        const g = Math.floor(Math.random() * 150);
        const b = Math.floor(Math.random() * 150);
        identifierColorCache.value[identifier] = `rgb(${r}, ${g}, ${b})`;
    }
    return { backgroundColor: identifierColorCache.value[identifier] };
};

const localResults = ref(null);

watch(() => props.results, (newResults) => {
    if (newResults) {
        localResults.value = JSON.parse(JSON.stringify(newResults));
    } else {
        localResults.value = null;
    }
}, { immediate: true, deep: true });


const collapsedFiles = ref({});

const toggleFile = (fileName) => {
    collapsedFiles.value[fileName] = !collapsedFiles.value[fileName];
};

const hasErrors = computed(() => {
    if (!localResults.value) return false;
    return (localResults.value.totals.errors > 0) || (localResults.value.totals.file_errors > 0);
});

const filesWithErrors = computed(() => localResults.value ? localResults.value.files : {});

const getRelativePath = (filePath) => {
    if (!props.projectRoot || !filePath.startsWith(props.projectRoot)) {
        return filePath;
    }
    return filePath.substring(props.projectRoot.length + 1);
};

const individualErrors = computed(() => {
    if (!localResults.value || !localResults.value.files) {
        return [];
    }
    const allErrors = [];
    for (const fileName in localResults.value.files) {
        for (const message of localResults.value.files[fileName].messages) {
            allErrors.push({
                ...message,
                file: fileName,
                relativeFile: getRelativePath(fileName),
            });
        }
    }
    return allErrors;
});

const getFileLink = (filePath, line) => {
    if (!props.editorUrl) {
        return '#';
    }

    let finalPath = filePath;
    if (props.hostProjectRoot && props.projectRoot && filePath.startsWith(props.projectRoot)) {
        finalPath = props.hostProjectRoot + filePath.substring(props.projectRoot.length);
    }

    return props.editorUrl
        .replace('%%file%%', finalPath)
        .replace('%%line%%', line);
};

const ignoreError = async (errorMessage, filePath) => {
    try {
        const response = await fetch('http://127.0.0.1:8081/api/ignore-error', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ error: errorMessage, file: filePath }),
        });

        if (response.ok && localResults.value) {
            const fileInResults = localResults.value.files[filePath];
            if (fileInResults) {
                const errorIndex = fileInResults.messages.findIndex(msg => msg.message === errorMessage);
                if (errorIndex !== -1) {
                    fileInResults.messages.splice(errorIndex, 1);
                    if (fileInResults.errors > 0) {
                        fileInResults.errors--;
                    }

                    if (localResults.value.totals.file_errors > 0) {
                        localResults.value.totals.file_errors--;
                    }

                    if (fileInResults.messages.length === 0) {
                        delete localResults.value.files[filePath];
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error ignoring error:', error);
    }
};

const successMessages = [
    "🚀 No errors! Your code is ready for takeoff!",
    "🎯 Flawless code! You're a quality sniper!",
    "⚡ Zero errors! Your code is lightning fast!",
    "🏆 No errors! You've won the clean code trophy!",
    "🔥 Everything's perfect! Your code is on fire (in a good way)!",
    "💎 No errors! Diamond quality code!",
    "🎨 Zero problems! A masterpiece of code!",
    "🌟 Impeccable! Your code shines!",
    "🎪 No errors! The show can begin!",
    "🦸 Clean code! You're the quality hero!",
    "🎵 Zero errors! Your code is a perfect symphony!",
    "🧙 No errors! Pure code magic!",
    "🎰 Jackpot! All tests are green!",
    "🏅 Gold medal for your code!",
    "🌈 Zero errors! Your code is a rainbow of perfection!",
    "🚁 All clear! Green light for deployment!",
    "🎬 Aaand... Action! No errors, let's roll!",
    "🧩 Perfect fit! No piece out of place!",
    "⚙️ Well-oiled machine! Zero problems detected!",
    "🎊 It's party time! The code is flawless!",
    "🔮 I've looked into the future... no bugs on the horizon!",
    "🏰 Impregnable fortress! Bulletproof code!",
    "🌊 Calm seas! Smooth sailing ahead!",
    "🎯 Target hit! 100% quality!",
    "🌺 Code in bloom! No weeds to pull!",
    "🎪 The circus is perfect! Every act in place!",
    "🏎️ Perfect engine! Ready to race!",
    "🎓 Graduated with honors for this code!",
    "🌙 Starry night! Your code lights up the darkness!",
    "🎸 Rock & Roll! Your code rocks!",
    "🍕 Like a perfect pizza! No wrong ingredients!",
    "🏔️ Summit conquered! Expert climber code!",
    "🎭 Curtain up! Flawless performance!",
    "🔧 Everything in place! Zero maintenance needed!",
    "🌻 Sunny code! No shadows to report!",
    "🎮 Level complete! No game over in sight!",
    "🏖️ Peaceful vacation! Code relaxes without issues!",
    "🎺 Perfect jazz! Your code improvises with class!",
    "🌍 Around the world without hitches! Global code!",
    "🎨 Perfect palette! Every color in the right place!",
    "🏹 Straight arrow! Code as precise as an archer!",
    "🌙 Peaceful night! The code sleeps soundly!",
    "🎉 Champagne! Time to celebrate!",
    "🔬 Analysis complete! Everything under control!",
    "🎪 Perfect trapeze! No falls!",
    "🌋 Dormant volcano! No bug eruptions!",
    "🎯 Arrows in the bullseye! Absolute precision!",
    "🏆 Podium conquered! Gold in quality!",
    "🌠 Shooting stars! Your code shines in the sky!",
    "🎪 Grand finale! Applause for this code!",
    "🔐 Armored safe! Maximum security!",
    "🎨 Digital Monet! A working work of art!",
    "🌴 Paradise found! Dream code!"
];

const randomSuccessMessage = () => {
    return successMessages[Math.floor(Math.random() * successMessages.length)];
}

</script>

<template>
    <div class="flex-grow overflow-y-auto p-4 bg-gray-900">
        <!-- Loading State -->
        <div v-if="status === 'running'" class="text-center p-12 bg-gray-800 rounded-lg shadow-lg">
            <div class="flex justify-center items-center">
                <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xl text-gray-300">Analysis in progress...</p>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!results" class="text-center p-12 bg-gray-800 rounded-lg shadow-lg">
            <p class="text-xl text-gray-400">Run analysis to see results</p>
        </div>

        <!-- Results -->
        <div v-else-if="hasErrors" class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <!-- Grouped View -->
            <div v-if="viewMode === 'grouped'">
                <div v-for="(file, fileName) in filesWithErrors" :key="fileName" class="border-b border-gray-700 last:border-b-0">
                    <h3 @click="toggleFile(fileName)" class="flex justify-between items-center text-lg font-semibold text-gray-200 p-4 bg-gray-750 border-b border-gray-700 cursor-pointer">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-400 transform transition-transform" :class="{ 'rotate-90': !collapsedFiles[fileName] }" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            <span>
                                <Copyable :text="getRelativePath(fileName)">
                                    <a :href="getFileLink(fileName)" class="hover:underline hover:text-blue-400">{{ getRelativePath(fileName) }}</a>
                                 </Copyable>
                                <span class="ml-2 text-sm font-normal text-gray-500">({{ file.messages.length }} errors)</span>
                            </span>
                        </div>
                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wider w-20 text-center">Action</span>
                    </h3>
                    <table v-if="!collapsedFiles[fileName]" class="min-w-full">
                        <transition-group tag="tbody" name="fade" class="divide-y divide-gray-700">
                        <tr v-for="(error) in file.messages" :key="error.message" class="hover:bg-gray-750 transition-colors duration-150">
                            <td class="pl-4 pr-2 py-3 whitespace-nowrap text-right align-top w-20">
                                <a :href="getFileLink(fileName, error.line)" class="font-mono text-sm text-gray-500 hover:underline hover:text-blue-400">L{{ error.line }}</a>
                            </td>
                            <td class="px-2 py-3 align-top">
                                <Copyable :text="error.message">
                                    <p class="text-gray-300">{{ error.message }}</p>
                                </Copyable>
                                <div v-if="error.tip" class="mt-2 pl-4 border-l-2 border-blue-600">
                                    <p class="text-sm text-blue-400">💡 Tip: <a :href="error.tip" target="_blank" rel="noopener noreferrer" class="underline">{{ error.tip }}</a></p>
                                </div>
                                <div v-if="error.identifier" class="mt-2">
                                    <a :href="`https://phpstan.org/error-identifiers/${error.identifier}`" target="_blank" rel="noopener noreferrer" class="inline-block text-gray-300 text-xs font-mono px-2 py-1 rounded-full hover:opacity-80 transition-opacity" title="View on phpstan.org" :style="getIdentifierBackgroundColor(error.identifier)">
                                        {{ error.identifier }}
                                    </a>
                                </div>
                            </td>
                            <td class="pl-2 pr-4 py-3 whitespace-nowrap text-center align-top w-24">
                                <button @click="ignoreError(error.message, fileName)" class="bg-gray-600 text-gray-300 text-xs font-mono px-2 py-1 rounded-full hover:bg-gray-500 transition-colors" title="Ignore this error">
                                    Ignore
                                </button>
                            </td>
                        </tr>
                        </transition-group>
                    </table>
                </div>
            </div>

            <!-- Individual View -->
            <div v-if="viewMode === 'individual'">
                <table class="min-w-full divide-y divide-gray-700">
                    <thead class="bg-gray-750">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">File</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Line</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Message</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Identifier</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                    </thead>
                    <transition-group tag="tbody" name="fade" class="divide-y divide-gray-700">
                    <tr v-for="(error) in individualErrors" :key="`${error.file}:${error.line}:${error.message}`" class="hover:bg-gray-750 transition-colors duration-150">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Copyable :text="error.relativeFile">
                                <a :href="getFileLink(error.file, error.line)" class="text-sm text-gray-300 hover:underline hover:text-blue-400">{{ error.relativeFile }}</a>
                            </Copyable>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-500">{{ error.line }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <Copyable :text="error.message">
                                <p class="text-sm text-gray-300">{{ error.message }}</p>
                            </Copyable>
                            <div v-if="error.tip" class="mt-2">
                                <p class="text-sm text-blue-400">💡 Tip: <a :href="error.tip" target="_blank" rel="noopener noreferrer" class="underline">{{ error.tip }}</a></p>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <a v-if="error.identifier" :href="`https://phpstan.org/error-identifiers/${error.identifier}`" target="_blank" rel="noopener noreferrer" class="inline-block text-gray-300 text-xs font-mono px-2 py-1 rounded-full hover:opacity-80 transition-opacity" title="View on phpstan.org" :style="getIdentifierBackgroundColor(error.identifier)">
                                {{ error.identifier }}
                            </a>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <button @click="ignoreError(error.message, error.file)" class="bg-gray-600 text-gray-300 text-xs font-mono px-2 py-1 rounded-full hover:bg-gray-500 transition-colors" title="Ignore this error">
                                Ignore
                            </button>
                        </td>
                    </tr>
                    </transition-group>
                </table>
            </div>
        </div>
        <div v-else-if="!hasErrors" class="text-center p-12 bg-gray-800 rounded-lg shadow-lg">
            <p class="text-xl text-gray-400">{{ randomSuccessMessage() }}</p>
        </div>
    </div>
</template>

<style>
.fade-leave-active {
    transition: opacity 0.5s;
}
.fade-leave-to {
    opacity: 0;
}
</style>
