# PhpStanHub

PhpStanHub is a modern, real-time web interface for running and analyzing PHPStan static analysis results. It provides a local, self-contained server that runs PHPStan on your project and displays errors in a clean, interactive web UI.

The application is powered by a PHP backend using the high-performance [ReactPHP](https://reactphp.org/) event-loop with WebSocket support via [Ratchet](http://socketo.me/), and a frontend built on [Vue.js 3](https://vuejs.org/) and [Tailwind CSS](https://tailwindcss.com/).

## Features

- **Real-Time Analysis**: Run PHPStan directly from the browser with live results via WebSocket
- **Interactive Results**: Errors are grouped by file and displayed clearly with syntax highlighting
- **IDE Integration**: Click on filenames or line numbers to open files directly in your IDE (supports PhpStorm, IntelliJ, VSCode)
- **Watch Mode**: Automatically re-run analysis when source files change using the `--watch` flag
- **Configuration Detection**: Automatically detects and uses your project's `phpstan.neon` or `phpstan.neon.dist` configuration
- **Customizable UI**:
  - Adjust analysis paths and levels directly from the UI
  - Switch between grouped (by file) and individual error views
  - Real-time error counter in the header
- **Error Ignoring**: Add errors to your PHPStan configuration directly from the UI
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
- **Components**:
  - `ControlPanel.vue`: Analysis control interface
  - `ResultsList.vue`: Error display with grouping capabilities
  - `SettingsModal.vue`: Configuration interface
  - `SettingsDropdown.vue`: Quick settings access
  - `Copyable.vue`: Reusable copy-to-clipboard component
- **Build Tool**: Vite with Vue plugin for fast HMR during development
- **Styling**: Tailwind CSS with custom configuration

### Communication
- **HTTP Server**: ReactPHP HTTP server on port 8081 for API and static assets
- **WebSocket Server**: Ratchet WebSocket server on port 8082 for real-time updates
- **API Endpoints**:
  - `GET /api/config`: Retrieve PHPStan configuration
  - `POST /api/run`: Trigger analysis
  - `POST /api/ignore-error`: Add error to ignore list

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
    - Click on file paths to open them in your IDE

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
