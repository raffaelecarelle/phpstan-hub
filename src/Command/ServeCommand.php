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

                        // Tokenize PHP code for syntax highlighting
                        $tokens = $this->tokenizePhpCode($content);

                        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                            'content' => $content,
                            'tokens' => $tokens
                        ]));
                    } catch (\Throwable $e) {
                        return new Response(500, ['Content-Type' => 'application/json'], json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]));
                    }
                }

                return new Response(404, ['Content-Type' => 'text/plain'], 'Not found');
            }
        );

        if ($input->getOption('watch')) {
            $config = $this->getPhpStanConfig($projectRoot);
            $pathsToWatch = $config['paths'];
            $levelToWatch = $config['level'];

            $fileWatcher = new FileWatcher($pathsToWatch);
            $configWatcher = new FileWatcher([$projectRoot], ['*.neon']);

            $loop?->addPeriodicTimer(1, function () use ($fileWatcher, $configWatcher, $phpStanRunner, $statusHandler, $loop, $pathsToWatch, $levelToWatch, $output) {
                if ($fileWatcher->hasChanges() || $configWatcher->hasChanges()) {
                    $this->runAnalysis($phpStanRunner, $statusHandler, $loop, implode(' ', $pathsToWatch), $levelToWatch, $output);
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
        // Broadcast "running" status
        $statusHandler->broadcast(json_encode(['status' => 'running']));

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

    /**
     * Tokenizes PHP code and assigns colors to each token
     *
     * @return array<int, array{text: string, color: string, line: int, type: string}>
     */
    private function tokenizePhpCode(string $content): array
    {
        $tokens = token_get_all($content);
        $result = [];
        $currentLine = 1;

        // Color mapping for token types
        $colorMap = [
            T_OPEN_TAG => '#9d174d', // PHP open tag (dark pink)
            T_CLOSE_TAG => '#9d174d', // PHP close tag
            T_VARIABLE => '#a78bfa', // Variables (purple)
            T_STRING => '#60a5fa', // Functions and classes (blue)
            T_CONSTANT_ENCAPSED_STRING => '#34d399', // Strings (green)
            T_LNUMBER => '#fbbf24', // Numbers (yellow)
            T_DNUMBER => '#fbbf24', // Float numbers
            T_COMMENT => '#6b7280', // Comments (gray)
            T_DOC_COMMENT => '#6b7280', // Doc comments
            T_WHITESPACE => '#d1d5db', // Whitespace
            T_ABSTRACT => '#c084fc', // Keywords (light purple)
            T_ARRAY => '#c084fc',
            T_AS => '#c084fc',
            T_BREAK => '#c084fc',
            T_CASE => '#c084fc',
            T_CATCH => '#c084fc',
            T_CLASS => '#c084fc',
            T_CONST => '#c084fc',
            T_CONTINUE => '#c084fc',
            T_DECLARE => '#c084fc',
            T_DEFAULT => '#c084fc',
            T_DO => '#c084fc',
            T_ECHO => '#c084fc',
            T_ELSE => '#c084fc',
            T_ELSEIF => '#c084fc',
            T_EMPTY => '#c084fc',
            T_ENDDECLARE => '#c084fc',
            T_ENDFOR => '#c084fc',
            T_ENDFOREACH => '#c084fc',
            T_ENDIF => '#c084fc',
            T_ENDSWITCH => '#c084fc',
            T_ENDWHILE => '#c084fc',
            T_EXTENDS => '#c084fc',
            T_FINAL => '#c084fc',
            T_FINALLY => '#c084fc',
            T_FOR => '#c084fc',
            T_FOREACH => '#c084fc',
            T_FUNCTION => '#c084fc',
            T_GLOBAL => '#c084fc',
            T_GOTO => '#c084fc',
            T_IF => '#c084fc',
            T_IMPLEMENTS => '#c084fc',
            T_INCLUDE => '#c084fc',
            T_INCLUDE_ONCE => '#c084fc',
            T_INSTANCEOF => '#c084fc',
            T_INTERFACE => '#c084fc',
            T_ISSET => '#c084fc',
            T_LIST => '#c084fc',
            T_NAMESPACE => '#c084fc',
            T_NEW => '#c084fc',
            T_PRIVATE => '#c084fc',
            T_PROTECTED => '#c084fc',
            T_PUBLIC => '#c084fc',
            T_REQUIRE => '#c084fc',
            T_REQUIRE_ONCE => '#c084fc',
            T_RETURN => '#c084fc',
            T_STATIC => '#c084fc',
            T_SWITCH => '#c084fc',
            T_THROW => '#c084fc',
            T_TRAIT => '#c084fc',
            T_TRY => '#c084fc',
            T_UNSET => '#c084fc',
            T_USE => '#c084fc',
            T_VAR => '#c084fc',
            T_WHILE => '#c084fc',
            T_YIELD => '#c084fc',
            T_ENUM => '#c084fc',
            T_READONLY => '#c084fc',
            T_MATCH => '#c084fc',
        ];

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenText = $token[1];
                $tokenLine = $token[2];

                $color = $colorMap[$tokenType] ?? '#d1d5db'; // Default gray

                // Handle tokens that span multiple lines
                if (str_contains($tokenText, "\n")) {
                    $lines = explode("\n", $tokenText);
                    foreach ($lines as $i => $lineText) {
                        $result[] = [
                            'text' => $lineText . ($i < count($lines) - 1 ? "\n" : ''),
                            'color' => $color,
                            'line' => $currentLine,
                            'type' => token_name($tokenType)
                        ];
                        if ($i < count($lines) - 1) {
                            $currentLine++;
                        }
                    }
                } else {
                    $result[] = [
                        'text' => $tokenText,
                        'color' => $color,
                        'line' => $currentLine,
                        'type' => token_name($tokenType)
                    ];
                }
            } else {
                // Single character token (punctuation)
                $result[] = [
                    'text' => $token,
                    'color' => '#f87171', // Punctuation in red
                    'line' => $currentLine,
                    'type' => 'PUNCTUATION'
                ];
            }
        }

        return $result;
    }
}
