import { describe, it, expect } from 'vitest';
import { buildFileTree, getChildrenArray, findFileInTree, expandToFile } from './fileTree.js';

describe('fileTree', () => {
    describe('buildFileTree', () => {
        it('should build a tree from flat file list', () => {
            const files = {
                '/project/src/Controller/UserController.php': {
                    messages: [
                        { line: 10, message: 'Error 1' },
                        { line: 20, message: 'Error 2' }
                    ]
                },
                '/project/src/Service/AuthService.php': {
                    messages: [
                        { line: 5, message: 'Error 3' }
                    ]
                }
            };

            const tree = buildFileTree(files, '/project');

            expect(tree.name).toBe('root');
            expect(tree.type).toBe('folder');
            expect(tree.children).toBeDefined();
            expect(tree.children.src).toBeDefined();
            expect(tree.children.src.type).toBe('folder');
            expect(tree.children.src.children.Controller).toBeDefined();
            expect(tree.children.src.children.Service).toBeDefined();
        });

        it('should calculate error counts correctly', () => {
            const files = {
                '/project/src/File1.php': { messages: [{ line: 1 }, { line: 2 }] },
                '/project/src/File2.php': { messages: [{ line: 1 }] }
            };

            const tree = buildFileTree(files, '/project');

            expect(tree.errors).toBe(3); // Total errors
            expect(tree.children.src.errors).toBe(3); // Src folder has 3 errors
            expect(tree.children.src.children['File1.php'].errors).toBe(2);
            expect(tree.children.src.children['File2.php'].errors).toBe(1);
        });

        it('should handle files without project root', () => {
            const files = {
                'src/File.php': { messages: [{ line: 1 }] }
            };

            const tree = buildFileTree(files, '');

            expect(tree.children.src).toBeDefined();
            expect(tree.children.src.children['File.php']).toBeDefined();
        });

        it('should set parent references correctly', () => {
            const files = {
                '/project/src/Controller/UserController.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');

            const srcFolder = tree.children.src;
            const controllerFolder = srcFolder.children.Controller;
            const file = controllerFolder.children['UserController.php'];

            expect(srcFolder.parent).toBe(tree);
            expect(controllerFolder.parent).toBe(srcFolder);
            expect(file.parent).toBe(controllerFolder);
        });

        it('should handle deep nested paths', () => {
            const files = {
                '/project/a/b/c/d/e/file.php': { messages: [{ line: 1 }] }
            };

            const tree = buildFileTree(files, '/project');

            let current = tree.children.a;
            expect(current).toBeDefined();
            current = current.children.b;
            expect(current).toBeDefined();
            current = current.children.c;
            expect(current).toBeDefined();
            current = current.children.d;
            expect(current).toBeDefined();
            current = current.children.e;
            expect(current).toBeDefined();
            expect(current.children['file.php']).toBeDefined();
        });
    });

    describe('getChildrenArray', () => {
        it('should return empty array for null children', () => {
            const result = getChildrenArray(null);
            expect(result).toEqual([]);
        });

        it('should sort folders first, then files', () => {
            const children = {
                'file1.php': { name: 'file1.php', type: 'file' },
                'folder1': { name: 'folder1', type: 'folder' },
                'file2.php': { name: 'file2.php', type: 'file' },
                'folder2': { name: 'folder2', type: 'folder' }
            };

            const result = getChildrenArray(children);

            expect(result[0].type).toBe('folder');
            expect(result[0].name).toBe('folder1');
            expect(result[1].type).toBe('folder');
            expect(result[1].name).toBe('folder2');
            expect(result[2].type).toBe('file');
            expect(result[2].name).toBe('file1.php');
            expect(result[3].type).toBe('file');
            expect(result[3].name).toBe('file2.php');
        });

        it('should sort alphabetically within same type', () => {
            const children = {
                'zebra.php': { name: 'zebra.php', type: 'file' },
                'apple.php': { name: 'apple.php', type: 'file' },
                'middle.php': { name: 'middle.php', type: 'file' }
            };

            const result = getChildrenArray(children);

            expect(result[0].name).toBe('apple.php');
            expect(result[1].name).toBe('middle.php');
            expect(result[2].name).toBe('zebra.php');
        });
    });

    describe('findFileInTree', () => {
        it('should find file by full path', () => {
            const files = {
                '/project/src/File1.php': { messages: [] },
                '/project/src/File2.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');
            const found = findFileInTree(tree, '/project/src/File2.php');

            expect(found).toBeDefined();
            expect(found.name).toBe('File2.php');
            expect(found.fullPath).toBe('/project/src/File2.php');
        });

        it('should return null if file not found', () => {
            const files = {
                '/project/src/File1.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');
            const found = findFileInTree(tree, '/project/src/NotExisting.php');

            expect(found).toBeNull();
        });

        it('should find file in nested structure', () => {
            const files = {
                '/project/a/b/c/deep.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');
            const found = findFileInTree(tree, '/project/a/b/c/deep.php');

            expect(found).toBeDefined();
            expect(found.name).toBe('deep.php');
        });
    });

    describe('expandToFile', () => {
        it('should expand all parent folders of a file', () => {
            const files = {
                '/project/src/Controller/UserController.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');

            // Initially all folders are collapsed
            expect(tree.children.src.expanded).toBe(false);
            expect(tree.children.src.children.Controller.expanded).toBe(false);

            expandToFile(tree, '/project/src/Controller/UserController.php');

            // Now all parent folders should be expanded
            expect(tree.children.src.expanded).toBe(true);
            expect(tree.children.src.children.Controller.expanded).toBe(true);
        });

        it('should not throw if file not found', () => {
            const files = {
                '/project/src/File.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');

            expect(() => {
                expandToFile(tree, '/project/not/existing.php');
            }).not.toThrow();
        });

        it('should expand only necessary folders', () => {
            const files = {
                '/project/src/File1.php': { messages: [] },
                '/project/lib/File2.php': { messages: [] }
            };

            const tree = buildFileTree(files, '/project');

            expandToFile(tree, '/project/src/File1.php');

            expect(tree.children.src.expanded).toBe(true);
            expect(tree.children.lib.expanded).toBe(false); // Should not be expanded
        });
    });
});
