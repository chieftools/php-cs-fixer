<?php

namespace ChiefTools\PhpCsFixer\Fixer;

use SplFileInfo;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;

class BinaryOperatorAlignmentFixer extends AbstractFixer
{
    private const COMPOUND_ASSIGNMENT_OPERATOR_KINDS = [
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
    ];

    public function getName(): string
    {
        return 'ChiefTools/binary_operator_alignment';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Consecutive assignment and array-pair operators should be minimally aligned until a blank line separates the group.',
            [
                new CodeSample(
                    <<<'PHP'
<?php

$data = [];
$otherDate = $date
    ->addDays(5);
$commonDate = now();

PHP,
                ),
            ],
        );
    }

    public function getPriority(): int
    {
        return -33;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return true;
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        $code                       = $tokens->generateCode();
        $lines                      = $this->lines($code);
        $records                    = $this->operatorRecords($tokens);
        $nonTargetAssignmentRecords = $this->nonTargetAssignmentRecords($tokens);

        $lines = $this->alignOperator($lines, $records, '=');
        $lines = $this->alignOperator($lines, $records, '=>');
        $lines = $this->normalizeAssignmentOperatorSpacing($lines, $nonTargetAssignmentRecords);

        $fixedCode = implode('', array_map(
            static fn (array $line): string => $line['content'] . $line['ending'],
            $lines,
        ));

        if ($fixedCode !== $code) {
            $tokens->setCode($fixedCode);
        }
    }

    /** @return list<array{content: string, ending: string}> */
    private function lines(string $code): array
    {
        $parts = preg_split('/(\R)/', $code, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [['content' => $code, 'ending' => '']];
        }

        $lines = [];

        for ($i = 0, $count = count($parts); $i < $count; $i += 2) {
            $lines[] = [
                'content' => $parts[$i],
                'ending'  => $parts[$i + 1] ?? '',
            ];
        }

        return $lines;
    }

    /** @return array<int, array<string, array{column: int, equalOffset: int, group: string}>> */
    private function operatorRecords(Tokens $tokens): array
    {
        $analyzer = new TokensAnalyzer($tokens);
        $records  = [];
        $line     = 0;
        $column   = 0;

        foreach ($tokens as $index => $token) {
            $content = $token->getContent();

            if ($this->isTargetOperator($tokens, $analyzer, $index)) {
                $operator = $token->isGivenKind(T_DOUBLE_ARROW) ? '=>' : '=';

                $records[$line][$operator] ??= [
                    'column'      => $column,
                    'equalOffset' => $this->operatorEqualOffset($tokens, $index),
                    'group'       => $this->operatorGroup($tokens, $index, $operator),
                ];
            }

            $parts = preg_split('/\R/', $content);

            if ($parts === false || count($parts) === 1) {
                $column += strlen($content);

                continue;
            }

            $line   += count($parts) - 1;
            $column  = strlen($parts[array_key_last($parts)]);
        }

        return $records;
    }

    /** @return array<int, list<int>> */
    private function nonTargetAssignmentRecords(Tokens $tokens): array
    {
        $analyzer = new TokensAnalyzer($tokens);
        $records  = [];
        $line     = 0;
        $column   = 0;

        foreach ($tokens as $index => $token) {
            $content = $token->getContent();

            if (
                $tokens[$index]->equals('=')
                && $analyzer->isBinaryOperator($index)
                && !$this->isAssignmentTarget($tokens, $index)
            ) {
                $records[$line][] = $column;
            }

            $parts = preg_split('/\R/', $content);

            if ($parts === false || count($parts) === 1) {
                $column += strlen($content);

                continue;
            }

            $line   += count($parts) - 1;
            $column  = strlen($parts[array_key_last($parts)]);
        }

        return $records;
    }

    private function operatorGroup(Tokens $tokens, int $index, string $operator): string
    {
        if ($operator === '=') {
            $blockStartIndex = $this->nearestContainingBlockStart($tokens, $index);

            return '=' . ($blockStartIndex ?? $this->lineIndentAt($tokens->generateCode(), $this->lineNumberAt($tokens, $index)));
        }

        $blockStartIndex = $this->nearestContainingBlockStart($tokens, $index);

        return '=>' . ($blockStartIndex ?? $this->lineIndentAt($tokens->generateCode(), $this->lineNumberAt($tokens, $index)));
    }

    private function lineNumberAt(Tokens $tokens, int $index): int
    {
        $line = 0;

        for ($i = 0; $i < $index; $i++) {
            $line += preg_match_all('/\R/', $tokens[$i]->getContent()) ?: 0;
        }

        return $line;
    }

    private function nearestContainingBlockStart(Tokens $tokens, int $index): ?int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $block = Tokens::detectBlockType($tokens[$i]);

            if ($block === null) {
                continue;
            }

            if (!$block['isStart']) {
                $i = $tokens->findBlockStart($block['type'], $i);

                continue;
            }

            return $i;
        }

        return null;
    }

    private function isTargetOperator(Tokens $tokens, TokensAnalyzer $analyzer, int $index): bool
    {
        if ($tokens[$index]->isGivenKind(T_DOUBLE_ARROW)) {
            return !$this->isArrowFunctionArrow($tokens, $index);
        }

        if (!$this->isAssignmentOperator($tokens, $index)) {
            return false;
        }

        if ($tokens[$index]->equals('=') && !$analyzer->isBinaryOperator($index)) {
            return false;
        }

        return $this->isAssignmentTarget($tokens, $index);
    }

    private function isAssignmentOperator(Tokens $tokens, int $index): bool
    {
        return $tokens[$index]->equals('=')
            || $tokens[$index]->isGivenKind(self::COMPOUND_ASSIGNMENT_OPERATOR_KINDS);
    }

    private function operatorEqualOffset(Tokens $tokens, int $index): int
    {
        if ($tokens[$index]->isGivenKind(T_DOUBLE_ARROW)) {
            return 0;
        }

        return strlen($tokens[$index]->getContent()) - 1;
    }

    private function isAssignmentTarget(Tokens $tokens, int $index): bool
    {
        if ($this->isFunctionLikeParameterDefault($tokens, $index)) {
            return false;
        }

        return $this->isAssignmentTargetLinePrefix($this->linePrefixBeforeToken($tokens, $index));
    }

    private function isFunctionLikeParameterDefault(Tokens $tokens, int $index): bool
    {
        $blockStartIndex = $this->nearestContainingBlockStart($tokens, $index);

        if ($blockStartIndex === null || !$tokens[$blockStartIndex]->equals('(')) {
            return false;
        }

        $previousMeaningfulIndex = $tokens->getPrevMeaningfulToken($blockStartIndex);

        if ($previousMeaningfulIndex === null) {
            return false;
        }

        if ($tokens[$previousMeaningfulIndex]->isGivenKind([T_FUNCTION, T_FN])) {
            return true;
        }

        $beforeNameIndex = $tokens->getPrevMeaningfulToken($previousMeaningfulIndex);

        return $beforeNameIndex !== null && $tokens[$beforeNameIndex]->isGivenKind(T_FUNCTION);
    }

    private function isAssignmentTargetLinePrefix(string $linePrefix): bool
    {
        if (preg_match('/^\h*\$[A-Za-z_]\w*\h*$/', $linePrefix) === 1) {
            return true;
        }

        if (preg_match('/^\h*\$[A-Za-z_]\w*(?:(?:->(?:\w+|\{.*\}))|(?:\[[^\r\n]*\]))+\h*$/', $linePrefix) === 1) {
            return true;
        }

        return preg_match(
            '/^\h*(?:(?:public|protected|private|static|readonly|var)\h+)+(?:[?\\\\\w|&<>,\[\]\h]+\h+)?\$[A-Za-z_]\w*\h*$/',
            $linePrefix,
        ) === 1 || preg_match(
            '/^\h*(?:(?:public|protected|private|final)\h+)*const\h+(?:[?\\\\\w|&<>,\[\]\h]+\h+)?[A-Za-z_]\w*\h*$/',
            $linePrefix,
        ) === 1 || preg_match(
            '/^\h*case\h+[A-Za-z_]\w*\h*$/',
            $linePrefix,
        ) === 1;
    }

    private function isArrowFunctionArrow(Tokens $tokens, int $index): bool
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (preg_match('/\R/', $tokens[$i]->getContent())) {
                return false;
            }

            if ($tokens[$i]->isGivenKind(T_FN)) {
                return true;
            }
        }

        return false;
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

    private function lineIndentAt(string $code, int $line): string
    {
        $lines = preg_split('/\R/', $code);

        if ($lines === false || !isset($lines[$line])) {
            return '';
        }

        preg_match('/^\h*/', $lines[$line], $matches);

        return $matches[0] ?? '';
    }

    /**
     * @param list<array{content: string, ending: string}>                                   $lines
     * @param array<int, array<string, array{column: int, equalOffset: int, group: string}>> $records
     *
     * @return list<array{content: string, ending: string}>
     */
    private function alignOperator(array $lines, array $records, string $operator): array
    {
        $groups = [];

        foreach ($records as $line => $operators) {
            if (!isset($operators[$operator])) {
                continue;
            }

            $record                               = $operators[$operator];
            $segment                              = $this->segmentAt($lines, $line);
            $groups[$segment][$record['group']][] = [
                'line'        => $line,
                'column'      => $record['column'],
                'equalOffset' => $record['equalOffset'],
            ];
        }

        foreach ($groups as $segmentGroups) {
            foreach ($segmentGroups as $group) {
                $lines = $this->alignGroup($lines, $group);
            }
        }

        return $lines;
    }

    /**
     * @param list<array{content: string, ending: string}> $lines
     * @param array<int, list<int>>                        $records
     *
     * @return list<array{content: string, ending: string}>
     */
    private function normalizeAssignmentOperatorSpacing(array $lines, array $records): array
    {
        krsort($records);

        foreach ($records as $line => $columns) {
            rsort($columns);

            foreach ($columns as $column) {
                if (!isset($lines[$line])) {
                    continue;
                }

                $content = $lines[$line]['content'];

                if (($content[$column] ?? null) !== '=') {
                    continue;
                }

                $before = substr($content, 0, $column);
                $after  = substr($content, $column + 1);
                $value  = ltrim($after);

                if ($value === '' && $this->canInlineAssignmentContinuation($lines, $line + 1)) {
                    $lines[$line]['content'] = rtrim($before) . ' = ' . ltrim($lines[$line + 1]['content']);

                    array_splice($lines, $line + 1, 1);

                    continue;
                }

                $lines[$line]['content'] = rtrim($before) . ($value === '' ? ' =' : ' = ' . $value);
            }
        }

        return $lines;
    }

    /** @param list<array{content: string, ending: string}> $lines */
    private function canInlineAssignmentContinuation(array $lines, int $line): bool
    {
        if (!isset($lines[$line])) {
            return false;
        }

        $content = trim($lines[$line]['content']);

        return $content !== '' && str_ends_with($content, ';');
    }

    /** @param list<array{content: string, ending: string}> $lines */
    private function segmentAt(array $lines, int $line): int
    {
        $segment = 0;

        for ($i = 0; $i < $line; $i++) {
            if ($this->isSegmentBoundaryLine($lines[$i]['content'])) {
                $segment++;
            }
        }

        return $segment;
    }

    private function isSegmentBoundaryLine(string $line): bool
    {
        $content = trim($line);

        return $content === ''
            || str_starts_with($content, '//')
            || str_starts_with($content, '#')
            || str_starts_with($content, '/*')
            || str_starts_with($content, '*');
    }

    /**
     * @param list<array{content: string, ending: string}>                    $lines
     * @param non-empty-list<array{line: int, column: int, equalOffset: int}> $group
     *
     * @return list<array{content: string, ending: string}>
     */
    private function alignGroup(array $lines, array $group): array
    {
        $targetColumn      = 0;
        $targetEqualOffset = 0;

        foreach ($group as $record) {
            $before = substr($lines[$record['line']]['content'], 0, $record['column']);

            $targetColumn      = max($targetColumn, strlen(rtrim($before)) + 1);
            $targetEqualOffset = max($targetEqualOffset, $record['equalOffset']);
        }

        foreach ($group as $record) {
            $line             = $lines[$record['line']]['content'];
            $before           = substr($line, 0, $record['column']);
            $after            = substr($line, $record['column']);
            $left             = rtrim($before);
            $targetLineColumn = $targetColumn + $targetEqualOffset - $record['equalOffset'];

            $lines[$record['line']]['content'] = $left . str_repeat(' ', max(1, $targetLineColumn - strlen($left))) . $after;
        }

        return $lines;
    }
}
