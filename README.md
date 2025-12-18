# PhpStanHub

PhpStanHub is a modern, real-time web interface for running and analyzing PHPStan static analysis results. It provides a local, self-contained server that runs PHPStan on your project and displays errors in a clean, interactive web UI.

The application is powered by a PHP backend using the high-performance [ReactPHP](https://reactphp.org/) event-loop with WebSocket support via [Ratchet](http://socketo.me/), and a frontend built on [Vue.js 3](https://vuejs.org/) and [Tailwind CSS](https://tailwindcss.com/).

## Features

- **Real-Time Analysis**: Run PHPStan directly from the browser with live results via WebSocket
- **🆕 Live Code Editing**: Edit your PHP code directly in the browser and verify fixes in real-time
  - **In-Browser Editor**: Click any line to edit code directly in the Explorer View
  - **Syntax Highlighting Preserved**: PHP syntax colors remain visible while editing
  - **One-Click Verification**: Press "Check" to save changes and re-run PHPStan instantly
  - **Real-Time Error Updates**: See errors disappear immediately when fixed (via WebSocket)
  - **Smart Visual Feedback**: Modified lines appear in gray, unchanged lines show colored syntax
  - **No Page Refresh**: All updates happen seamlessly without leaving the page
- **Advanced Explorer View**:
  - **Tree-based Navigation**: Hierarchical file browser with error count badges
  - **Smart Search**: Full-text search across files with regex support and syntax highlighting
  - **PHP Syntax Highlighting**: Token-based colorization for better code readability
  - **Intelligent Code Viewer**:
    - Automatic collapsing of code sections without errors (>10 lines)
    - Virtual scrolling for large files (>1000 lines) with smooth performance
    - Click on line numbers to open files in your IDE
  - **Quick Fix Suggestions**: Context-aware suggestions for common PHPStan errors
  - **Instant Error Removal**: Ignore errors with smooth fade-out animation (no re-analysis needed)
  - **Keyboard Navigation**: Full keyboard shortcuts support (Alt+1/2 for view switching, Ctrl+/ for help)
  - **State Preservation**: Expanded folders remain open when updating error counts
- **Multiple View Modes**:
  - **Explorer View**: IDE-like experience with file tree and code viewer
  - **Grouped View**: Errors grouped by file
  - **Individual View**: Flat list of all errors
- **IDE Integration**: Click on filenames or line numbers to open files directly in your IDE (supports PhpStorm, IntelliJ, VSCode)
- **Watch Mode**: Automatically re-run analysis when source files change using the `--watch` flag
- **Configuration Detection**: Automatically detects and uses your project's `phpstan.neon` or `phpstan.neon.dist` configuration
- **Real-Time Error Management**:
  - Ignore errors directly from the UI with instant visual feedback
  - Error counts update automatically without re-running analysis
  - Smooth animations for better UX
- **Baseline Generation**: Generate PHPStan baseline files from the interface
- **Docker Support**: Fully containerized environment with Docker Compose

## Architecture

### Backend (PHP)
- **Command Layer** (`src/Command/ServeCommand.php`): Symfony Console command that bootstraps the application
- **PHPStan Runner** (`src/PhpStan/PhpStanRunner.php`): Executes PHPStan analysis via ReactPHP child processes
- **File Watcher** (`src/Watcher/FileWatcher.php`): Monitors filesystem changes for watch mode using Symfony Finder
- **Web Layer** (`src/Web/`):
  - `StatusHandler.php`: WebSocket server for real-time communication
  - `ViteManifest.php`: Asset management for production builds

### Frontend (Vue.js + Vite)
- **Main App** (`assets/js/App.vue`): Application shell with WebSocket integration
- **Core Components**:
  - `ControlPanel.vue`: Analysis control interface
  - `ResultsList.vue`: Error display with grouping capabilities
  - `ExplorerView.vue`: Advanced IDE-like explorer with tree navigation
  - `FileTreeSidebar.vue`: Interactive file tree with real-time error counts and state preservation
  - `CodeViewer.vue`: Syntax-highlighted code viewer with virtual scrolling for large files
  - `SearchInFiles.vue`: Full-text search with regex support and result highlighting
  - `QuickFixSuggestions.vue`: Context-aware error fix suggestions
  - `KeyboardShortcutsModal.vue`: Keyboard shortcuts help overlay
  - `InlineDiff.vue`: Before/after diff viewer for code changes
  - `SettingsModal.vue`: Configuration interface
  - `SettingsDropdown.vue`: Quick settings access
  - `Copyable.vue`: Reusable copy-to-clipboard component
- **Composables**:
  - `useKeyboardShortcuts.js`: Keyboard navigation and shortcuts management
- **Build Tool**: Vite with Vue plugin for fast HMR during development
- **Styling**: Tailwind CSS with custom dark theme configuration

### Communication
- **HTTP Server**: ReactPHP HTTP server on port 8081 for API and static assets
- **WebSocket Server**: Ratchet WebSocket server on port 8082 for real-time updates
- **API Endpoints**:
  - `GET /api/config`: Retrieve PHPStan configuration
  - `POST /api/run`: Trigger analysis
  - `POST /api/ignore-error`: Add error to ignore list
  - `POST /api/file-content`: Retrieve file content with syntax-highlighted tokens
  - `POST /api/save-file`: 🆕 Save edited file content to disk (for live editing)
  - `POST /api/check-error`: 🆕 Re-run PHPStan analysis after file modifications

## Installation

### As a Development Dependency (Recommended)

Install PhpStanHub in your project:

```sh
composer require --dev raffaelecarelle/phpstan-hub
```

### Via Git Clone (For Development)

```sh
git clone https://github.com/raffaelecarelle/phpstan-hub.git
cd phpstan-hub
composer install
npm install
npm run build
```

## Usage

### Basic Usage

1.  **Start the Server**:
    ```sh
    vendor/bin/phpstan-hub
    ```

2.  **Enable Watch Mode** (auto-reanalysis on file save):
    ```sh
    vendor/bin/phpstan-hub --watch
    ```

3.  **Open the Web UI**:
    Navigate to **http://127.0.0.1:8081**

4.  **Run Analysis**:
    - Configure paths and PHPStan level in the UI
    - Click **Analyze**
    - View results in real-time
    - Switch to **Explorer View** for the best experience (Settings → View Mode)

5.  **Explorer View Features**:
    - Navigate files using the tree sidebar with error count badges
    - Search across files with **Ctrl+P** or using the Search tab
    - Click on errors to see **Quick Fix Suggestions**
    - Ignore errors with one click - they fade out instantly
    - Use keyboard shortcuts: **Ctrl+/** to see all available shortcuts
    - Click on line numbers to open files in your IDE

### 🆕 Live Code Editing (NEW!)

PhpStanHub now includes a powerful **in-browser code editor** that lets you fix errors without leaving the web interface!

**How to use:**

1. **Open a file** in Explorer View that has PHPStan errors
2. **Click "Edit"** button in the file header to enable editing mode
3. **Click any line** to start editing (the line becomes an editable input)
4. **Make your changes** - syntax highlighting is preserved for unmodified lines
5. **Click "Check"** when done - this will:
   - Save your changes to disk
   - Re-run PHPStan on the entire codebase
   - Update errors in real-time via WebSocket
6. **See results instantly** - fixed errors disappear with fade-out animation

**Visual feedback:**
- **Colored syntax** = Original, unmodified code
- **Gray text** = Your modifications
- **Red background** = Lines with errors (always highlighted)
- **Green "Check" button** = You modified an error line (recommended to verify fix)
- **Blue "Check" button** = General modifications

**Example workflow:**
```
1. PHPStan shows: "Missing parameter type for $bar"
2. Click "Edit" → Click on the error line
3. Change: function foo($bar)
   To:     function foo(string $bar)
4. Click "Check" → PHPStan re-runs
5. Error disappears if fixed! ✅
```

This feature is perfect for:
- Quick fixes during code review
- Testing if a change resolves the error before committing
- Learning from PHPStan suggestions by trying fixes immediately
- Remote development where IDE integration isn't available

### Docker Usage

1.  **Build and start the container**:
    ```sh
    docker-compose up
    ```

2.  **Access the UI**:
    Navigate to **http://127.0.0.1:8081**

The Docker setup includes:
- PHP 8.2 CLI environment
- All required PHP extensions (xml, mbstring, zip)
- Node.js and npm for frontend builds
- Desktop notification support (Linux X11)

### Configuration

PhpStanHub automatically reads your project's `phpstan.neon` or `phpstan.neon.dist` file. You can customize behavior by adding a `phpstanHub` section:

```neon
parameters:
    level: 6
    paths:
        - src
    editorUrl: 'phpstorm://open?file=%%file%%&line=%%line%%'
    phpstanHub:
        hostProjectRoot: /path/to/project
```

**Configuration Options**:
- `editorUrl`: URL scheme for opening files in your IDE
  - PhpStorm: `phpstorm://open?file=%%file%%&line=%%line%%`
  - IntelliJ: `idea://open?file=%%file%%&line=%%line%%`
  - VSCode: `vscode://file/%%file%%:%%line%%`
- `hostProjectRoot`: Used in Docker environments to map container paths to host paths

### Frontend Development

For live development of the Vue.js frontend:

1.  **Start the Vite dev server**:
    ```sh
    npm run dev
    ```

2.  **Start PhpStanHub in dev mode**:
    ```sh
    vendor/bin/phpstan-hub
    ```

3.  **Access with HMR**:
    Navigate to **http://127.0.0.1:8081/?dev**

The `?dev` parameter enables Vite's Hot Module Replacement for instant UI updates.

### Building for Production

Generate optimized production assets:

```sh
npm run build
```

This creates minified assets in `public/build/` with a manifest file for cache-busting.

## Requirements

- **PHP**: 8.2 or higher
- **Composer**: For PHP dependency management
- **Node.js**: 16+ and npm (for frontend development)
- **PHPStan**: Installed in your project (via composer)

### PHP Extensions Required
- `ext-json`
- `ext-xml`
- `ext-mbstring`
- `ext-zip`

## Development Tools

The project includes several development and quality tools:

- **PHPStan**: Static analysis (level 6) with baseline support
- **PHP CS Fixer**: Code style enforcement
- **Rector**: Automated refactoring and PHP version upgrades
- **ESLint**: JavaScript linting
- **Babel**: JavaScript transpilation

### Running Quality Checks

```sh
# Run PHPStan on PhpStanHub itself
vendor/bin/phpstan analyse

# Fix PHP code style
vendor/bin/php-cs-fixer fix

# Run Rector refactoring
vendor/bin/rector process
```

## Project Structure

```
PhpStanHub/
├── assets/                 # Frontend source files
│   ├── css/               # Stylesheets
│   └── js/                # Vue.js application
│       ├── components/    # Vue components
│       └── App.vue        # Main app component
├── bin/                   # Executable scripts
│   └── phpstan-hub        # Main CLI entry point
├── public/                # Public web assets
│   ├── build/            # Built assets (generated)
│   └── index.html        # HTML template
├── src/                   # PHP source code
│   ├── Command/          # Symfony Console commands
│   ├── PhpStan/          # PHPStan integration
│   ├── Watcher/          # File watching functionality
│   └── Web/              # Web server components
├── .docker/              # Docker configuration
├── composer.json         # PHP dependencies
├── package.json          # Node.js dependencies
├── phpstan.neon          # PHPStan configuration
├── rector.php            # Rector configuration
├── tailwind.config.js    # Tailwind CSS configuration
└── vite.config.js        # Vite build configuration
```

## Browser Support

PhpStanHub uses modern JavaScript features and requires:
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

## Keyboard Shortcuts

Press **Ctrl+/** (or **Cmd+/** on Mac) in Explorer View to see all available shortcuts:

- **Alt+1**: Switch to Files tab
- **Alt+2**: Switch to Search tab
- **Ctrl+J / Ctrl+K**: Navigate between files
- **Ctrl+W**: Close current file
- **Esc**: Close modals

## Troubleshooting

### WebSocket Connection Issues
If the WebSocket fails to connect:
- Ensure port 8082 is not blocked by a firewall
- Check that no other service is using port 8082

### IDE Integration Not Working
- Verify your IDE supports the URL scheme in `editorUrl`
- For PhpStorm/IntelliJ: Install the "Remote Call" plugin
- For VSCode: Ensure the URL handler is enabled

### Watch Mode Not Detecting Changes
- The file watcher only monitors `.php` files in specified paths
- Default watch path is `src/`, configure via your PHPStan config

### Error Ignoring Not Working
If "Ignore this error" returns a 400 error:
- Ensure the error message matches exactly
- Check that PHPStan configuration file is writable
- Verify the file path is within the project root

## Contributing

Contributions are welcome! Please ensure:
- Code passes PHPStan level 6 analysis
- PHP code follows PSR-12 style (enforced by PHP CS Fixer)
- Vue.js code follows the project's ESLint configuration

## License

This project is open-source software licensed under the **MIT License**.

## Credits

Created by Raffaele Carelle (raffaele.carelle@gmail.com)

Built with:
- [PHPStan](https://phpstan.org/) - PHP Static Analysis Tool
- [ReactPHP](https://reactphp.org/) - Event-driven, non-blocking I/O with PHP
- [Ratchet](http://socketo.me/) - WebSocket library for PHP
- [Vue.js](https://vuejs.org/) - Progressive JavaScript Framework
- [Vite](https://vitejs.dev/) - Next Generation Frontend Tooling
- [Tailwind CSS](https://tailwindcss.com/) - Utility-first CSS Framework
- [Symfony Console](https://symfony.com/doc/current/components/console.html) - CLI component
