<?php

namespace ChiefTools\PhpCsFixer\Fixer;

use SplFileInfo;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

class PhpdocFullyQualifiedClassNamesFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'ChiefTools/phpdoc_fqcn';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'PHPDoc annotations must use fully qualified class names while code may use imports.',
            [
                new CodeSample(
                    <<<'PHP'
<?php

use App\Models\Domain;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @return HasMany<Domain> */
function domains(): HasMany
{
}

PHP
                ),
            ],
        );
    }

    public function getPriority(): int
    {
        return -41;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_DOC_COMMENT) && $tokens->isTokenKindFound(T_USE);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $imports = $this->imports($tokens->generateCode());

        if ($imports === []) {
            return;
        }

        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            $content      = $token->getContent();
            $fixedContent = $this->fixPhpDoc($content, $imports);

            if ($fixedContent !== $content) {
                $tokens[$index] = new Token([T_DOC_COMMENT, $fixedContent]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function imports(string $code): array
    {
        $declarations = $this->importDeclarations($code);
        $imports      = [];

        foreach ($declarations as $declaration) {
            if (str_contains($declaration, '{')) {
                $this->addGroupedImports($imports, $declaration);

                continue;
            }

            $this->addImport($imports, $declaration);
        }

        return $imports;
    }

    /**
     * @return list<string>
     */
    private function importDeclarations(string $code): array
    {
        $boundary = preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)?(?:class|interface|trait|enum)\s+/m', $code, $matches, PREG_OFFSET_CAPTURE)
            ? $matches[0][1]
            : strlen($code);

        preg_match_all('/^use\s+(?!function\b|const\b)([^;]+);/m', substr($code, 0, $boundary), $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * @param array<string, string> $imports
     */
    private function addGroupedImports(array &$imports, string $declaration): void
    {
        if (!preg_match('/^(?<prefix>[^{}]+)\\\\\{(?<classes>.+)}$/', $declaration, $matches)) {
            return;
        }

        foreach (explode(',', $matches['classes']) as $class) {
            $this->addImport($imports, trim($matches['prefix']) . '\\' . trim($class));
        }
    }

    /**
     * @param array<string, string> $imports
     */
    private function addImport(array &$imports, string $declaration): void
    {
        $declaration = ltrim($declaration, '\\');

        if ($declaration === '') {
            return;
        }

        if (preg_match('/^(?<class>.+)\s+as\s+(?<alias>[^\\\\\s]+)$/i', $declaration, $matches)) {
            $imports[$matches['alias']] = '\\' . ltrim($matches['class'], '\\');

            return;
        }

        $alias = basename(str_replace('\\', '/', $declaration));

        if ($alias === '') {
            return;
        }

        $imports[$alias] = '\\' . $declaration;
    }

    /**
     * @param array<string, string> $imports
     */
    private function fixPhpDoc(string $content, array $imports): string
    {
        return preg_replace_callback(
            '/@(return|param|var|property|property-read|property-write|method|throws|extends|implements|mixin|phpstan-return|phpstan-param|phpstan-var)\b[^\r\n]*/',
            fn (array $matches): string => $this->expandImportedNames($matches[0], $imports),
            $content,
        ) ?? $content;
    }

    /**
     * @param array<string, string> $imports
     */
    private function expandImportedNames(string $annotation, array $imports): string
    {
        foreach ($imports as $alias => $fullyQualifiedClassName) {
            $annotation = preg_replace(
                '/(?<![\\\\A-Za-z0-9_])' . preg_quote($alias, '/') . '(?![A-Za-z0-9_])/',
                str_replace('\\', '\\\\', $fullyQualifiedClassName),
                $annotation,
            ) ?? $annotation;
        }

        return $annotation;
    }
}
