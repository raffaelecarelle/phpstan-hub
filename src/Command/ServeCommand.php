<?php

namespace PhpStanHub\Command;

use Nette\Neon\Neon;
use PhpStanHub\PhpStan\PhpStanRunner;
use PhpStanHub\Web\StatusHandler;
use PhpStanHub\Web\ViteManifest;
use PhpStanHub\Watcher\FileWatcher;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\Http\HttpServer as RatchetHttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    protected static $defaultName = 'serve';

    private const MIME_TYPES = [
        'js' => 'application/javascript',
        'css' => 'text/css',
        'json' => 'application/json',
        'ico' => 'image/x-icon',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];

    protected function configure()
    {
        $this->setDescription('Starts the PhpStanHub server.')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Watch files for changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $loop = Loop::get();
        $projectRoot = getcwd();
        $phpStanRunner = new PhpStanRunner($projectRoot);
        $statusHandler = new StatusHandler();
        $viteManifest = new ViteManifest($projectRoot . '/public/build/.vite/manifest.json');

        $http = new HttpServer(
            $loop,
            function (ServerRequestInterface $request) use ($phpStanRunner, $statusHandler, $loop, $output, $viteManifest, $projectRoot) {
                $path = $request->getUri()->getPath();

                if ($path === '/') {
                    $isDev = isset($request->getQueryParams()['dev']);
                    return $this->createIndexResponse($isDev, $viteManifest);
                }

                if (str_starts_with($path, '/build/')) {
                    $filePath = __DIR__ . '/../../public' . $path;
                    if (file_exists($filePath) && is_file($filePath)) {
                        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                        $contentType = self::MIME_TYPES[$extension] ?? 'text/plain';
                        return new Response(200, ['Content-Type' => $contentType], file_get_contents($filePath));
                    }
                }

                if ($path === '/api/config') {
                    $config = $this->getPhpStanConfig($projectRoot);
                    return new Response(200, ['Content-Type' => 'application/json'], json_encode($config));
                }

                if ($path === '/api/run' && $request->getMethod() === 'POST') {
                    try {
                        $body = (string)$request->getBody();
                        $output->writeln('Received /api/run request with body: ' . $body);

                        $params = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                        $paths = $params['paths'] ?? ['src'];
                        $level = $params['level'] ?? 5;
                        $generateBaseline = $params['generateBaseline'] ?? false;

                        // Converte paths in stringa se è un array
                        $pathsString = is_array($paths) ? implode(' ', $paths) : $paths;

                        $this->runAnalysis($phpStanRunner, $statusHandler, $loop, $pathsString, $level, $output, $generateBaseline);

                        return new Response(202, ['Content-Type' => 'application/json'], json_encode(['status' => 'running']));
                    } catch (\Throwable $e) {
                        $errorMessage = sprintf("Error processing /api/run: %s\n%s", $e->getMessage(), $e->getTraceAsString());
                        $output->writeln(sprintf('<error>%s</error>', $errorMessage));
                        return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]));
                    }
                }

                if ($path === '/api/ignore-error' && $request->getMethod() === 'POST') {
                    try {
                        $body = (string)$request->getBody();
                        $params = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                        $error = $params['error'] ?? null;
                        $file = $params['file'] ?? null;

                        if ($error && $file) {
                            $this->addErrorToPhpStanConfig($projectRoot, $error, $file);
                            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'success']));
                        }

                        return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid request']));
                    } catch (\Throwable $e) {
                        return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]));
                    }
                }

                if ($path === '/api/file-content' && $request->getMethod() === 'POST') {
                    try {
                        $body = (string)$request->getBody();
                        $params = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                        $file = $params['file'] ?? null;

                        if (!$file) {
                            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'File path is required']));
                        }

                        // Security: verify file is within project root
                        $realPath = realpath($file);
                        if ($realPath === false || !str_starts_with($realPath, $projectRoot)) {
                            return new Response(403, ['Content-Type' => 'application/json'], json_encode(['error' => 'Access denied']));
                        }

                        if (!file_exists($realPath) || !is_file($realPath)) {
                            return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'File not found']));
                        }

                        $content = file_get_contents($realPath);
                        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['content' => $content]));
                    } catch (\Throwable $e) {
                        return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]));
                    }
                }

                return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
            }
        );

        if ($input->getOption('watch')) {
            $pathsToWatch = ['src']; // Default paths to watch
            $fileWatcher = new FileWatcher($pathsToWatch);
            $loop?->addPeriodicTimer(1, function () use ($fileWatcher, $phpStanRunner, $statusHandler, $loop, $pathsToWatch, $output) {
                if ($fileWatcher->hasChanges()) {
                    $this->runAnalysis($phpStanRunner, $statusHandler, $loop, implode(' ', $pathsToWatch), 5, $output);
                }
            });
        }

        $socket = new SocketServer('127.0.0.1:8081', [], $loop);
        $http->listen($socket);

        $wsServer = new WsServer($statusHandler);
        $wsSocket = new SocketServer('127.0.0.1:8082', [], $loop);
        $ioServer = new IoServer(new RatchetHttpServer($wsServer), $wsSocket, $loop);

        $output->writeln('Server running at http://127.0.0.1:8081');
        $output->writeln('WebSocket server running at ws://12-7.0.0.1:8082');
        $output->writeln('Run `npm run dev` for frontend development.');

        $ioServer->run();

        return Command::SUCCESS;
    }

    private function createIndexResponse(bool $isDev, ViteManifest $viteManifest): Response
    {
        if ($isDev) {
            $head = '<script type="module" src="http://localhost:5173/@vite/client"></script>
                     <script type="module" src="http://localhost:5173/assets/js/app.js"></script>';
        } else {
            $head = $viteManifest->getStyles() . $viteManifest->getScript();
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en" class="bg-gray-100">
        <head>
            <meta charset="UTF-8">
            <title>PhpStanHub</title>
            $head
        </head>
        <body>
            <div id="app"></div>
        </body>
        </html>
        HTML;

        return new Response(200, ['Content-Type' => 'text/html'], $html);
    }

    private function getPhpStanConfig(string $projectRoot): array
    {
        $defaultPaths = $this->getDefaultPaths($projectRoot);

        $defaultConfig = [
            'level' => 5,
            'paths' => $defaultPaths,
            'availablePaths' => $defaultPaths,
            'editorUrl' => 'idea://open?file=%%file%%&line=%%line%%',
            'projectRoot' => $projectRoot,
            'hostProjectRoot' => null,
        ];

        $neonPath = $projectRoot . '/phpstan.neon.dist';
        if (!file_exists($neonPath)) {
            $neonPath = $projectRoot . '/phpstan.neon';
        }

        if (!file_exists($neonPath)) {
            return $defaultConfig;
        }

        try {
            $neonContent = file_get_contents($neonPath);
            $data = Neon::decode($neonContent);

            $level = $data['parameters']['level'] ?? $defaultConfig['level'];
            $paths = $data['parameters']['paths'] ?? $defaultConfig['paths'];
            $editorUrl = $data['parameters']['editorUrl'] ?? $defaultConfig['editorUrl'];
            $hostProjectRoot = $data['parameters']['phpstanHub']['hostProjectRoot'] ?? $defaultConfig['hostProjectRoot'];

            $pathsArray = is_array($paths) ? $paths : [$paths];

            return [
                'level' => $level,
                'paths' => $pathsArray,
                'availablePaths' => $defaultPaths,
                'editorUrl' => $editorUrl,
                'projectRoot' => $projectRoot,
                'hostProjectRoot' => $hostProjectRoot,
            ];
        } catch (\Exception $e) {
            return $defaultConfig;
        }
    }

    private function getDefaultPaths(string $projectRoot): array
    {
        // Prima cerca di leggere da composer.json
        $composerPath = $projectRoot . '/composer.json';
        if (file_exists($composerPath)) {
            try {
                $composerContent = file_get_contents($composerPath);
                $composerData = json_decode($composerContent, true, 512, JSON_THROW_ON_ERROR);

                $paths = [];

                // Estrai i path da autoload psr-4
                if (isset($composerData['autoload']['psr-4'])) {
                    foreach ($composerData['autoload']['psr-4'] as $namespace => $path) {
                        if (is_array($path)) {
                            foreach ($path as $p) {
                                $paths[] = rtrim($p, '/');
                            }
                        } else {
                            $paths[] = rtrim($path, '/');
                        }
                    }
                }

                // Estrai i path da autoload-dev psr-4
                if (isset($composerData['autoload-dev']['psr-4'])) {
                    foreach ($composerData['autoload-dev']['psr-4'] as $namespace => $path) {
                        if (is_array($path)) {
                            foreach ($path as $p) {
                                $paths[] = rtrim($p, '/');
                            }
                        } else {
                            $paths[] = rtrim($path, '/');
                        }
                    }
                }

                if (!empty($paths)) {
                    return array_values(array_unique($paths));
                }
            } catch (\Exception $e) {
                // Ignora errori e usa il fallback
            }
        }

        // Fallback predefinito
        return ['src'];
    }

    private function runAnalysis(PhpStanRunner $phpStanRunner, StatusHandler $statusHandler, $loop, string $paths, int $level, OutputInterface $output, bool $generateBaseline = false): void
    {
        $process = $phpStanRunner->run($paths, $level, $generateBaseline);
        $output->writeln(sprintf('Running command: %s', $process->getCommand()));
        $process->start($loop);

        $buffer = '';
        $process->stdout->on('data', function ($chunk) use (&$buffer) {
            $buffer .= $chunk;
        });

        $errorBuffer = '';
        $process->stderr->on('data', function ($chunk) use (&$errorBuffer) {
            $errorBuffer .= $chunk;
        });

        $process->on('exit', function ($exitCode) use (&$buffer, &$errorBuffer, $statusHandler, $output) {
            if ($exitCode !== 0 && !empty($errorBuffer)) {
                $output->writeln(sprintf('<error>PHPStan exited with code %d:</error>', $exitCode));
                $output->writeln(sprintf('<error>%s</error>', $errorBuffer));

                $errorPayload = json_encode([
                    'totals' => ['errors' => 1, 'file_errors' => 1],
                    'files' => [],
                    'errors' => [sprintf('PHPStan failed with exit code %d: %s', $exitCode, $errorBuffer)],
                ]);
                if ($errorPayload !== false) {
                    $statusHandler->broadcast($errorPayload);
                }
                return;
            }

            // Debug output
            $output->writeln('PHPStan Output:');
            $output->writeln($buffer);

            $statusHandler->broadcast($buffer);
        });
    }

    private function addErrorToPhpStanConfig(string $projectRoot, string $error, string $file): void
    {
        $neonPath = $projectRoot . '/phpstan.neon';

        // Se phpstan.neon non esiste, controlla phpstan.neon.dist
        if (!file_exists($neonPath)) {
            $distPath = $projectRoot . '/phpstan.neon.dist';
            if (file_exists($distPath)) {
                $neonPath = $distPath;
            }
            // Se nessuno esiste, neonPath rimane phpstan.neon e verrà creato
        }

        $data = [];
        if (file_exists($neonPath)) {
            $neonContent = file_get_contents($neonPath);
            $data = Neon::decode($neonContent);
        }

        if (!isset($data['parameters']['ignoreErrors'])) {
            $data['parameters']['ignoreErrors'] = [];
        }

        $data['parameters']['ignoreErrors'][] = [
            'message' => '#' . preg_quote($error, '#') . '#',
            'path' => $file,
        ];

        file_put_contents($neonPath, Neon::encode($data, Neon::BLOCK));
    }
}
