<script setup>
import { computed } from 'vue';
import Copyable from './Copyable.vue';

const props = defineProps({
    error: Object, // Single error object with message, tip, identifier, etc.
    filePath: String,
    projectRoot: String,
});

const emit = defineEmits(['apply-fix', 'ignore-error']);

// Extract potential quick fixes from error message and tip
const quickFixes = computed(() => {
    const fixes = [];

    // Check if error has a tip (PHPStan often provides helpful tips)
    if (props.error?.tip) {
        fixes.push({
            type: 'tip',
            title: 'PHPStan Suggestion',
            description: props.error.tip,
            action: null,
        });
    }

    // Pattern matching for common PHPStan errors to suggest quick fixes
    const message = props.error?.message || '';

    // 1. Undefined variable
    if (message.includes('Undefined variable:')) {
        const varMatch = message.match(/Undefined variable: \$(\w+)/);
        if (varMatch) {
            const varName = varMatch[1];
            fixes.push({
                type: 'quickfix',
                title: `Initialize $${varName}`,
                description: `Add initialization for $${varName} before use`,
                action: 'initialize_variable',
                code: `$${varName} = null; // or appropriate default value`,
            });
        }
    }

    // 2. Missing parameter type
    if (message.includes('Parameter') && message.includes('has no type')) {
        const paramMatch = message.match(/Parameter #\d+ \$(\w+)/);
        if (paramMatch) {
            const paramName = paramMatch[1];
            fixes.push({
                type: 'quickfix',
                title: `Add type to parameter $${paramName}`,
                description: 'Add a type declaration to this parameter',
                action: 'add_parameter_type',
                code: `string|int|array $${paramName}`, // Generic suggestion
            });
        }
    }

    // 3. Missing return type
    if (message.includes('Method') && message.includes('has no return type')) {
        fixes.push({
            type: 'quickfix',
            title: 'Add return type declaration',
            description: 'Specify the return type for this method',
            action: 'add_return_type',
            code: ': void|string|int|array',
        });
    }

    // 4. Dead code / unreachable statement
    if (message.includes('Unreachable statement') || message.includes('Dead code')) {
        fixes.push({
            type: 'quickfix',
            title: 'Remove unreachable code',
            description: 'This code will never execute and can be safely removed',
            action: 'remove_dead_code',
        });
    }

    // 5. Property access on possible null
    if (message.includes('on possibly null')) {
        fixes.push({
            type: 'quickfix',
            title: 'Add null check',
            description: 'Add a null check before accessing the property',
            action: 'add_null_check',
            code: 'if ($variable !== null) { ... }',
        });
    }

    // 6. Always add "Ignore this error" option
    fixes.push({
        type: 'ignore',
        title: 'Ignore this error',
        description: 'Add this error to PHPStan ignore list',
        action: 'ignore_error',
    });

    return fixes;
});

const getFixIcon = (fix) => {
    switch (fix.type) {
        case 'tip':
            return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>`;
        case 'quickfix':
            return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>`;
        case 'ignore':
            return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
            </svg>`;
        default:
            return `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>`;
    }
};

const getFixColorClass = (fix) => {
    switch (fix.type) {
        case 'tip':
            return 'border-blue-500 bg-blue-900 bg-opacity-20';
        case 'quickfix':
            return 'border-green-500 bg-green-900 bg-opacity-20';
        case 'ignore':
            return 'border-gray-500 bg-gray-800';
        default:
            return 'border-gray-600 bg-gray-800';
    }
};

const handleFixClick = (fix) => {
    if (fix.action === 'ignore_error') {
        emit('ignore-error', props.error);
    } else if (fix.action) {
        emit('apply-fix', {
            action: fix.action,
            error: props.error,
            code: fix.code,
        });
    }
};
</script>

<template>
    <div v-if="quickFixes.length > 0" class="space-y-2">
        <div
            v-for="(fix, index) in quickFixes"
            :key="index"
            :class="['border-l-4 rounded p-3 cursor-pointer hover:bg-opacity-30 transition-colors', getFixColorClass(fix)]"
            @click="handleFixClick(fix)"
        >
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5" v-html="getFixIcon(fix)"></div>
                <div class="flex-1">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-semibold text-gray-200">{{ fix.title }}</h4>
                        <span
                            v-if="fix.type !== 'ignore'"
                            :class="[
                                'text-xs px-2 py-0.5 rounded',
                                fix.type === 'tip' ? 'bg-blue-600 text-blue-100' : 'bg-green-600 text-green-100'
                            ]"
                        >
                            {{ fix.type }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">{{ fix.description }}</p>
                    <div v-if="fix.code" class="mt-2">
                        <Copyable :text="fix.code">
                            <code class="text-xs font-mono bg-gray-900 px-2 py-1 rounded block text-gray-300">
                                {{ fix.code }}
                            </code>
                        </Copyable>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
