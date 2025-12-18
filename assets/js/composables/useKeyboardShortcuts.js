import { onMounted, onUnmounted } from 'vue';

export function useKeyboardShortcuts(shortcuts) {
    const handleKeyDown = (event) => {
        // Build the key combination string
        const modifiers = [];
        if (event.ctrlKey || event.metaKey) modifiers.push('ctrl');
        if (event.altKey) modifiers.push('alt');
        if (event.shiftKey) modifiers.push('shift');

        const key = event.key.toLowerCase();
        const combination = [...modifiers, key].join('+');

        // Check if this combination matches any registered shortcut
        const shortcut = shortcuts.find(s => s.key === combination);

        if (shortcut) {
            // Prevent default browser behavior
            event.preventDefault();
            event.stopPropagation();

            // Execute the callback
            shortcut.callback(event);
        }
    };

    onMounted(() => {
        window.addEventListener('keydown', handleKeyDown);
    });

    onUnmounted(() => {
        window.removeEventListener('keydown', handleKeyDown);
    });

    return {
        // Return a function to show available shortcuts
        getShortcuts: () => shortcuts,
    };
}

// Predefined shortcuts for PhpStanHub
export const DEFAULT_SHORTCUTS = {
    // Navigation
    NEXT_FILE: 'ctrl+j',
    PREV_FILE: 'ctrl+k',
    GO_TO_LINE: 'ctrl+g',
    TOGGLE_SIDEBAR: 'ctrl+b',

    // Search
    SEARCH: 'ctrl+f',
    SEARCH_FILES: 'ctrl+p',
    GLOBAL_SEARCH: 'ctrl+shift+f',

    // Bookmarks
    TOGGLE_BOOKMARK: 'ctrl+d',
    SHOW_BOOKMARKS: 'ctrl+shift+b',

    // Views
    SWITCH_TO_FILES: 'alt+1',
    SWITCH_TO_SEARCH: 'alt+2',
    SWITCH_TO_BOOKMARKS: 'alt+3',

    // Actions
    RUN_ANALYSIS: 'ctrl+r',
    CLOSE_FILE: 'ctrl+w',

    // Other
    SHOW_SHORTCUTS: 'ctrl+/',
};
