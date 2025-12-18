/**
 * Builds a hierarchical file tree structure from a flat list of file paths
 * @param {Object} files - Object with file paths as keys and error data as values
 * @param {string} projectRoot - The project root path to remove from file paths
 * @returns {Object} Tree structure with folders and files
 */
export function buildFileTree(files, projectRoot = '') {
    const tree = {
        name: 'root',
        type: 'folder',
        children: {},
        errors: 0,
        expanded: true,
    };

    Object.entries(files).forEach(([filePath, fileData]) => {
        // Remove project root from path
        let relativePath = filePath;
        if (projectRoot && filePath.startsWith(projectRoot)) {
            relativePath = filePath.substring(projectRoot.length + 1);
        }

        const parts = relativePath.split('/');
        let currentNode = tree;

        // Traverse/create folder structure
        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];
            const isFile = i === parts.length - 1;

            if (!currentNode.children[part]) {
                currentNode.children[part] = {
                    name: part,
                    type: isFile ? 'file' : 'folder',
                    children: isFile ? null : {},
                    errors: 0,
                    expanded: false,
                    fullPath: filePath,
                    relativePath: relativePath,
                };

                if (isFile) {
                    currentNode.children[part].messages = fileData.messages;
                }
            }

            // Update error count
            if (isFile) {
                currentNode.children[part].errors = fileData.messages.length;

                // Bubble up error count to parent folders
                let ancestor = currentNode;
                while (ancestor) {
                    ancestor.errors += fileData.messages.length;
                    // Navigate up - we'll need to track parent references
                    ancestor = ancestor.parent;
                }
            }

            currentNode = currentNode.children[part];
        }
    });

    // Set parent references for upward traversal
    setParentReferences(tree, null);

    // Auto-expand first level folders for better UX
    Object.values(tree.children).forEach(child => {
        if (child.type === 'folder') {
            child.expanded = true;
        }
    });

    return tree;
}

/**
 * Recursively sets parent references in the tree
 * @param {Object} node - Current node
 * @param {Object|null} parent - Parent node
 */
function setParentReferences(node, parent) {
    node.parent = parent;
    if (node.children) {
        Object.values(node.children).forEach(child => {
            setParentReferences(child, node);
        });
    }
}

/**
 * Converts the tree children object to a sorted array
 * @param {Object} children - Children object from tree node
 * @returns {Array} Sorted array (folders first, then files, alphabetically)
 */
export function getChildrenArray(children) {
    if (!children) return [];

    const childArray = Object.values(children);

    // Sort: folders first, then files, both alphabetically
    return childArray.sort((a, b) => {
        if (a.type !== b.type) {
            return a.type === 'folder' ? -1 : 1;
        }
        return a.name.localeCompare(b.name);
    });
}

/**
 * Finds a file node in the tree by its full path
 * @param {Object} tree - Root tree node
 * @param {string} filePath - Full file path to find
 * @returns {Object|null} File node or null if not found
 */
export function findFileInTree(tree, filePath) {
    if (tree.type === 'file' && tree.fullPath === filePath) {
        return tree;
    }

    if (tree.children) {
        for (const child of Object.values(tree.children)) {
            const result = findFileInTree(child, filePath);
            if (result) return result;
        }
    }

    return null;
}

/**
 * Expands all parent folders of a given file path
 * @param {Object} tree - Root tree node
 * @param {string} filePath - Full file path
 */
export function expandToFile(tree, filePath) {
    const fileNode = findFileInTree(tree, filePath);
    if (!fileNode) return;

    let current = fileNode.parent;
    while (current) {
        current.expanded = true;
        current = current.parent;
    }
}
