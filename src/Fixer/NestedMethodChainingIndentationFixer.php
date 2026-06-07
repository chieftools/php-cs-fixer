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

PHP,
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

            $anchorIndex = $this->chainIndentAnchor($tokens, $index);

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

    private function chainIndentAnchor(Tokens $tokens, int $index): ?int
    {
        $anchorIndex = $this->firstChainObjectOperatorOnPreviousMeaningfulLine($tokens, $index);

        if ($anchorIndex !== null) {
            return $anchorIndex;
        }

        return $this->firstChainObjectOperatorBeforePreviousCall($tokens, $index);
    }

    private function firstChainObjectOperatorOnPreviousMeaningfulLine(Tokens $tokens, int $index): ?int
    {
        $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($index);

        if ($previousMeaningfulIndex === null) {
            return null;
        }

        return $this->firstChainObjectOperatorOnLineContinuingTo($tokens, $previousMeaningfulIndex, $previousMeaningfulIndex);
    }

    private function firstChainObjectOperatorBeforePreviousCall(Tokens $tokens, int $index): ?int
    {
        $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($index);

        if ($previousMeaningfulIndex === null || !$tokens[$previousMeaningfulIndex]->equals(')')) {
            return null;
        }

        $openingParenthesisIndex = $tokens->findBlockStart(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $previousMeaningfulIndex);
        $methodNameIndex         = $tokens->getPrevMeaningfulToken($openingParenthesisIndex);

        if ($methodNameIndex === null) {
            return null;
        }

        $objectOperatorIndex = $tokens->getPrevMeaningfulToken($methodNameIndex);

        if ($objectOperatorIndex === null || !$tokens[$objectOperatorIndex]->isObjectOperator()) {
            return null;
        }

        return $this->firstChainObjectOperatorOnLineContinuingTo($tokens, $objectOperatorIndex, $previousMeaningfulIndex);
    }

    private function firstChainObjectOperatorOnLineContinuingTo(Tokens $tokens, int $lineIndex, int $endIndex): ?int
    {
        $lineStartIndex = $this->lineStartIndex($tokens, $lineIndex);

        for ($i = $lineStartIndex; $i <= $lineIndex; $i++) {
            if ($tokens[$i]->isObjectOperator() && $this->chainContinuesTo($tokens, $i, $endIndex)) {
                return $i;
            }
        }

        return null;
    }

    private function chainContinuesTo(Tokens $tokens, int $objectOperatorIndex, int $endIndex): bool
    {
        $cursor = $objectOperatorIndex;

        while (true) {
            $segmentEndIndex = $this->chainSegmentEnd($tokens, $cursor);

            if ($segmentEndIndex === null || $segmentEndIndex > $endIndex) {
                return false;
            }

            if ($segmentEndIndex === $endIndex) {
                return true;
            }

            $nextMeaningfulIndex = $tokens->getNextMeaningfulToken($segmentEndIndex);

            if ($nextMeaningfulIndex === null || $nextMeaningfulIndex > $endIndex || !$tokens[$nextMeaningfulIndex]->isObjectOperator()) {
                return false;
            }

            $cursor = $nextMeaningfulIndex;
        }
    }

    private function chainSegmentEnd(Tokens $tokens, int $objectOperatorIndex): ?int
    {
        $memberIndex = $tokens->getNextMeaningfulToken($objectOperatorIndex);

        if ($memberIndex === null) {
            return null;
        }

        $segmentEndIndex     = $memberIndex;
        $nextMeaningfulIndex = $tokens->getNextMeaningfulToken($segmentEndIndex);

        if ($nextMeaningfulIndex !== null && $tokens[$nextMeaningfulIndex]->equals('(')) {
            return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $nextMeaningfulIndex);
        }

        return $segmentEndIndex;
    }

    private function isNestedArgumentExpression(Tokens $tokens, int $anchorIndex): bool
    {
        $firstMeaningfulIndex = $this->firstMeaningfulTokenOnLine($tokens, $anchorIndex);

        if ($this->lineStartsWithStatementKeyword($tokens, $firstMeaningfulIndex)) {
            return false;
        }

        if ($this->lineContainsAssignmentBefore($tokens, $firstMeaningfulIndex, $anchorIndex)) {
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

    private function lineContainsAssignmentBefore(Tokens $tokens, int $lineStartIndex, int $index): bool
    {
        for ($i = $lineStartIndex; $i < $index; $i++) {
            if ($tokens[$i]->equals('=')) {
                return true;
            }

            if ($tokens[$i]->isGivenKind([
                T_AND_EQUAL,
                T_COALESCE_EQUAL,
                T_CONCAT_EQUAL,
                T_DIV_EQUAL,
                T_MINUS_EQUAL,
                T_MOD_EQUAL,
                T_MUL_EQUAL,
                T_OR_EQUAL,
                T_PLUS_EQUAL,
                T_POW_EQUAL,
                T_SL_EQUAL,
                T_SR_EQUAL,
                T_XOR_EQUAL,
            ])) {
                return true;
            }
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
