import { ref, computed } from 'vue';

const STORAGE_KEY = 'phpstanhub_bookmarks';

// Persistent bookmarks stored in localStorage
const bookmarks = ref(loadBookmarks());

function loadBookmarks() {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (err) {
        console.error('Failed to load bookmarks:', err);
        return [];
    }
}

function saveBookmarks() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(bookmarks.value));
    } catch (err) {
        console.error('Failed to save bookmarks:', err);
    }
}

export function useBookmarks() {
    const addBookmark = (filePath, metadata = {}) => {
        if (isBookmarked(filePath)) {
            return;
        }

        bookmarks.value.push({
            filePath,
            addedAt: Date.now(),
            ...metadata,
        });

        saveBookmarks();
    };

    const removeBookmark = (filePath) => {
        bookmarks.value = bookmarks.value.filter(b => b.filePath !== filePath);
        saveBookmarks();
    };

    const toggleBookmark = (filePath, metadata = {}) => {
        if (isBookmarked(filePath)) {
            removeBookmark(filePath);
        } else {
            addBookmark(filePath, metadata);
        }
    };

    const isBookmarked = (filePath) => {
        return bookmarks.value.some(b => b.filePath === filePath);
    };

    const getBookmark = (filePath) => {
        return bookmarks.value.find(b => b.filePath === filePath);
    };

    const clearBookmarks = () => {
        bookmarks.value = [];
        saveBookmarks();
    };

    const sortedBookmarks = computed(() => {
        return [...bookmarks.value].sort((a, b) => b.addedAt - a.addedAt);
    });

    return {
        bookmarks: sortedBookmarks,
        addBookmark,
        removeBookmark,
        toggleBookmark,
        isBookmarked,
        getBookmark,
        clearBookmarks,
    };
}
