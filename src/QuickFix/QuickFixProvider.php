<?php

namespace PhpStanHub\QuickFix;

class QuickFixProvider
{
    public function getSuggestion(string $file, int $line, string $message): ?string
    {
        // Example: Suggest adding a PHPDoc for missing type hints
        if (str_contains($message, 'missing typehint')) {
            return $this->suggestTypehint($message);
        }

        return null;
    }

    private function suggestTypehint(string $message): ?string
    {
        // This is a simplified example. A real implementation would need
        // to parse the file and understand the context of the error.
        if (preg_match('/property \$(?P<property>\w+)/', $message, $matches)) {
            $propertyName = $matches['property'];
            return sprintf('/** @var string $%s */', $propertyName);
        }

        return null;
    }
}
