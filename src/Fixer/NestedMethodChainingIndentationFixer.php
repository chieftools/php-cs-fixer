<?php

namespace ChiefTools\PhpCsFixer\Fixer;

use SplFileInfo;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

class NestedMethodChainingIndentationFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
    public function getName(): string
    {
        return 'ChiefTools/nested_method_chaining_indentation';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Nested argument method chains should align with the first object operator on their expression line.',
            [
                new CodeSample(
                    <<<'PHP'
<?php

return call(
    $service->items()
        ->filter()
);

PHP
                ),
            ],
        );
    }

    public function getPriority(): int
    {
        return -4;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(Token::getObjectOperatorKinds());
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $lineEnding = $this->whitespacesConfig->getLineEnding();

        for ($index = 1, $count = count($tokens); $index < $count; $index++) {
            if (!$tokens[$index]->isObjectOperator()) {
                continue;
            }

            $currentIndent = $this->getIndentAt($tokens, $index - 1);

            if ($currentIndent === null) {
                continue;
            }

            $anchorIndex = $this->firstObjectOperatorOnPreviousMeaningfulLine($tokens, $index);

            if ($anchorIndex === null || !$this->isNestedArgumentExpression($tokens, $anchorIndex)) {
                continue;
            }

            $expectedIndent = $this->indentBeforeToken($tokens, $anchorIndex);

            if ($currentIndent === $expectedIndent) {
                continue;
            }

            $tokens[$index - 1] = new Token([T_WHITESPACE, $lineEnding . $expectedIndent]);
            $this->fixNestedCallIndent($tokens, $index, $currentIndent, $expectedIndent);
        }
    }

    private function firstObjectOperatorOnPreviousMeaningfulLine(Tokens $tokens, int $index): ?int
    {
        $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($index);

        if ($previousMeaningfulIndex === null) {
            return null;
        }

        $lineStartIndex = $this->lineStartIndex($tokens, $previousMeaningfulIndex);

        for ($i = $lineStartIndex; $i <= $previousMeaningfulIndex; $i++) {
            if ($tokens[$i]->isObjectOperator()) {
                return $i;
            }
        }

        return null;
    }

    private function isNestedArgumentExpression(Tokens $tokens, int $anchorIndex): bool
    {
        $firstMeaningfulIndex = $this->firstMeaningfulTokenOnLine($tokens, $anchorIndex);

        if ($this->lineStartsWithStatementKeyword($tokens, $firstMeaningfulIndex)) {
            return false;
        }

        for ($i = $firstMeaningfulIndex - 1; $i >= 0; $i--) {
            if ($tokens[$i]->equals(')')) {
                $i = $tokens->findBlockStart(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $i);

                continue;
            }

            if (!$tokens[$i]->equals('(')) {
                continue;
            }

            $blockEndIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $i);

            if ($blockEndIndex <= $firstMeaningfulIndex || $this->isOnSameLine($tokens, $i, $firstMeaningfulIndex)) {
                continue;
            }

            return $this->isCallLikeParenthesis($tokens, $i);
        }

        return false;
    }

    private function lineStartsWithStatementKeyword(Tokens $tokens, int $index): bool
    {
        return $tokens[$index]->isGivenKind([
            T_BREAK,
            T_CONTINUE,
            T_DO,
            T_ECHO,
            T_ELSE,
            T_ELSEIF,
            T_FOR,
            T_FOREACH,
            T_GOTO,
            T_IF,
            T_RETURN,
            T_SWITCH,
            T_THROW,
            T_TRY,
            T_WHILE,
            T_YIELD,
            T_YIELD_FROM,
        ]);
    }

    private function isCallLikeParenthesis(Tokens $tokens, int $index): bool
    {
        $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($index);

        if ($previousMeaningfulIndex === null) {
            return false;
        }

        return !$tokens[$previousMeaningfulIndex]->isGivenKind([
            T_CATCH,
            T_DECLARE,
            T_ELSEIF,
            T_FOR,
            T_FOREACH,
            T_FUNCTION,
            T_IF,
            T_MATCH,
            T_SWITCH,
            T_WHILE,
        ]);
    }

    private function fixNestedCallIndent(Tokens $tokens, int $objectOperatorIndex, string $currentIndent, string $expectedIndent): void
    {
        $methodNameIndex = $tokens->getNextMeaningfulToken($objectOperatorIndex);

        if ($methodNameIndex === null) {
            return;
        }

        $openingParenthesisIndex = $tokens->getNextMeaningfulToken($methodNameIndex);

        if ($openingParenthesisIndex === null || !$tokens[$openingParenthesisIndex]->equals('(')) {
            return;
        }

        $closingParenthesisIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openingParenthesisIndex);
        $pattern                 = '/(\R)' . preg_quote($currentIndent, '/') . '(\h*)$/D';
        $replacement             = '$1' . $expectedIndent . '$2';

        for ($i = $objectOperatorIndex + 1; $i < $closingParenthesisIndex; $i++) {
            if (!$tokens[$i]->isWhitespace()) {
                continue;
            }

            $content = $tokens[$i]->getContent();

            if (!preg_match('/\R/', $content)) {
                continue;
            }

            $fixedContent = preg_replace($pattern, $replacement, $content) ?? $content;

            if ($fixedContent !== $content) {
                $tokens[$i] = new Token([T_WHITESPACE, $fixedContent]);
            }
        }
    }

    private function getIndentAt(Tokens $tokens, int $index): ?string
    {
        if (!$tokens[$index]->isWhitespace()) {
            return null;
        }

        if (preg_match('/\R(\h*)$/', $tokens[$index]->getContent(), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function indentBeforeToken(Tokens $tokens, int $index): string
    {
        return preg_replace('/[^\t ]/', ' ', $this->linePrefixBeforeToken($tokens, $index)) ?? '';
    }

    private function linePrefixBeforeToken(Tokens $tokens, int $index): string
    {
        $prefix = '';

        for ($i = $index - 1; $i >= 0; $i--) {
            $content = $tokens[$i]->getContent();

            if (preg_match('/\R([^\r\n]*)$/', $content, $matches)) {
                return $matches[1] . $prefix;
            }

            $prefix = $content . $prefix;
        }

        return $prefix;
    }

    private function firstMeaningfulTokenOnLine(Tokens $tokens, int $index): int
    {
        $lineStartIndex = $this->lineStartIndex($tokens, $index);

        for ($i = $lineStartIndex; $i <= $index; $i++) {
            if (!$tokens[$i]->isWhitespace() && !$tokens[$i]->isComment()) {
                return $i;
            }
        }

        return $index;
    }

    private function lineStartIndex(Tokens $tokens, int $index): int
    {
        for ($i = $index; $i >= 0; $i--) {
            if (preg_match('/\R/', $tokens[$i]->getContent())) {
                return $i;
            }
        }

        return 0;
    }

    private function isOnSameLine(Tokens $tokens, int $leftIndex, int $rightIndex): bool
    {
        for ($i = $leftIndex; $i < $rightIndex; $i++) {
            if (preg_match('/\R/', $tokens[$i]->getContent())) {
                return false;
            }
        }

        return true;
    }
}
