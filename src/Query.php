<?php

namespace LaravelCloudSearch;

use Aws\CloudSearchDomain\CloudSearchDomainClient;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use LaravelCloudSearch\Exceptions\QueryException;

class Query
{
    /** @var string */
    const MATCH_AND = 'and';

    /** @var string */
    const MATCH_OR = 'or';

    /**
     * Default fuzziness factor (1/4)
     *
     * @var float
     */
    const DEFAULT_FUZZINESS = 1 / 4;

    /** @var string */
    protected $boolean = self::MATCH_AND;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        'literal', 'LITERAL', '=', '<', '>', '<=', '>=', '<>', '!=',
    ];

    /** @var \Aws\CloudSearchDomain\CloudSearchDomainClient|null */
    protected $client;

    /** @var array */
    protected $statements = [];

    /** @var array */
    protected $columns = [];

    /** @var string|null */
    protected $cursor;

    /** @var string|null */
    protected $phrase;

    /** @var int */
    protected $take = 0;

    /** @var int */
    protected $offset = 0;

    /** @var array */
    protected $orderBy = [];

    /** @var array */
    protected $facets = [];

    /** @var array */
    protected $statistics = [];

    /** @var array */
    protected $expressions = [];

    /**
     * @param \Aws\CloudSearchDomain\CloudSearchDomainClient $client
     *
     * @return $this
     */
    public function setClient(CloudSearchDomainClient $client): self
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return \Aws\Result
     * @throws \LaravelCloudSearch\Exceptions\QueryException
     */
    public function run(): \Aws\Result
    {
        if (!$this->client) {
            throw new QueryException("Query failed, no client connection found.");
        }

        $result = $this->client->search($this->getArguments());

        // Update cursor if needed
        $this->updateCursorFromResult($result);

        return $result;
    }

    /**
     * Add where statement
     *
     * @param string|\Closure $column
     * @param string|null $operator
     * @param mixed|null $value
     *
     * @return $this
     */
    public function where($column, ?string $operator = null, $value = null, $boolean = self::MATCH_AND): self
    {
        // Match boolean may turn into OR or stay AND, but never turn from OR to AND.
        if ($this->boolean !== self::MATCH_OR) {
            $this->boolean = $boolean;
        }

        // Handle closure
        if ($column instanceof Closure) {
            $subQuery = new Builder(new self);

            call_user_func($column, $subQuery);

            $this->statements[] = $subQuery->getQuery();

            return $this;
        }

        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        switch ($operator) {
            case '=':
                $this->statements[] = "{$column}:{$value}";
                break;
            case 'literal':
            case 'LITERAL':
                $this->statements[] = "{$column}:'{$value}'";
                break;
            case '!=':
            case '<>':
                $this->statements[] = "(not {$column}:{$value})";
                break;
            case '>':
                $this->statements[] = sprintf('(range field=%s {%s,})', $column, $value);
                break;
            case '>=':
                $this->statements[] = sprintf('(range field=%s [%s,})', $column, $value);
                break;
            case '<':
                $this->statements[] = sprintf('(range field=%s {,%s})', $column, $value);
                break;
            case '<=':
                $this->statements[] = sprintf('(range field=%s {,%s])', $column, $value);
                break;
        }

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $values
     *
     * @return $this
     */
    public function whereIn(string $column, $values): self
    {
        $values = $values instanceof Arrayable ? $values->toArray() : $values;

        $this->where(function (Builder $query) use ($column, $values) {
            foreach ((array)$values as $value) {
                $query->orWhere($column, $value);
            }
        });

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $values
     *
     * @return $this
     */
    public function whereNotIn(string $column, $values): self
    {
        $values = $values instanceof Arrayable ? $values->toArray() : $values;

        $this->where(function (Builder $query) use ($column, $values) {
            foreach ((array)$values as $value) {
                $query->where($column, '!=', $value);
            }
        });

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     *
     * @return $this
     */
    public function whereBetween(string $column, $min, $max): self
    {
        $this->addStatement(sprintf('(range field=%s [%s,%s])', $column, $min, $max));

        return $this;
    }

    /**
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     *
     * @return $this
     */
    public function whereNotBetween(string $column, $min, $max): self
    {
        $this->where(function (Builder $query) use ($column, $min, $max) {
            $query->where($column, '<', $min)
                ->where($column, '>', $max);
        });

        return $this;
    }

    /**
     * Add custom statement
     *
     * @param string $statement
     *
     * @return $this
     */
    public function addStatement(string $statement): self
    {
        $this->statements[] = $statement;

        return $this;
    }

    /**
     * The (fuzzy) search phrase
     *
     * @param string $phrase
     * @param int|float $fuzziness Allowed typo percentage per word
     * @param bool $lookForAnyWord
     *
     * @return $this
     */
    public function phrase(string $phrase, $fuzziness = null, bool $lookForAnyWord = false): self
    {
        // Do not search for empty string
        if (!$phrase) {
            return $this;
        }

        $searches = [];
        $fuzziness = $fuzziness ?: self::DEFAULT_FUZZINESS;
        $phraseParts = explode(' ', $phrase);

        $searches[] = "({$phrase})";
        $searches[] = "({$phrase}*)";

        if ($fuzziness) {
            $this->convertToFuzzyPhrase($phrase, $fuzziness);

            $searches[] = "({$phrase})";
        }

        // Look for any word in the given phrase
        if ($lookForAnyWord) {
            foreach ($phraseParts as $phrasePart) {
                $searches[] = "{$phrasePart}*";

                if ($fuzziness) {
                    $this->convertToFuzzyPhrase($phrasePart, $fuzziness);
                }

                $searches[] = $phrasePart;
            }
        }

        // Add prefix and fuzziness phrease (cloudSearch is case insensitive)
        $this->phrase = implode('|', array_filter(array_unique($searches)));

        return $this;
    }

    /**
     * Convert a given search phrase to a fuzzy compatible
     * phrase based on the supplied level of 'fuzziness'
     *
     * @param string $phrase
     * @param int|float $fuzziness Allowed typo percentage per word
     */
    private function convertToFuzzyPhrase(string &$phrase, $fuzziness)
    {
        // Remove duplicate spaces and explode
        $words = explode(' ', preg_replace('/\s+/', ' ', $phrase));
        $phrase = '';

        foreach ($words as $word) {
            $wordFuzzyness = floor(strlen($word) * $fuzziness) ?: 1;
            $phrase .= " {$word}~{$wordFuzzyness}";
        }

        $phrase = trim($phrase);
    }

    /**
     * Set a single facet which need to be returned in the result
     *
     * @param string|array $facet
     * @param array $options
     *
     * @return $this
     */
    public function facet($facet, array $options = []): self
    {
        if (is_array($facet)) {
            foreach ($facet as $name => $options) {
                $this->facet($name, $options);
            }

            return $this;
        }

        $this->facets[$facet] = (object)$options;

        return $this;
    }

    /**
     * Set a single stat which need to be returned in the result
     *
     * @param string|array $field
     *
     * @return $this
     */
    public function statistic($field): self
    {
        if (is_array($field)) {
            foreach ($field as $fieldName) {
                $this->statistic($fieldName);
            }

            return $this;
        }

        $this->statistics[$field] = new \stdClass;

        return $this;
    }

    /**
     * Set a expression
     *
     * @param string|array $name
     * @param string $expression
     *
     * @return $this
     */
    public function expression($name, string $expression): self
    {
        if (is_array($name)) {
            foreach ($name as $expressionName => $expressionValue) {
                $this->expression($expressionName, $expressionValue);
            }

            return $this;
        }

        $this->expressions[$expressionName] = $expressionValue;

        return $this;
    }

    /**
     * Return query as string
     *
     * @return string
     */
    public function getQuery(): string
    {
        $statementsAsString = implode(' ', $this->statements);

        return count($this->statements) > 1 || $this->boolean === self::MATCH_AND
            ? "({$this->boolean} {$statementsAsString})"
            : $statementsAsString;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        $orderBy = $this->getOrderBy();

        if ($this->phrase && !in_array('_score desc', $this->getOrderBy())) {
            $orderBy = array_merge(['_score desc'], $orderBy);
        }

        // We use array filter to 'unset' keys that don't have a value (required by CloudSearch)
        $arguments = array_filter([
            // Set query + query parser
            'query' => $this->phrase ?: 'matchall',
            'queryParser' => $this->phrase ? null : 'structured',

            // Sort by any given tags and relevance score. CloudSearch does this is reverse order!
            'sort' => implode(',', $orderBy),

            // Add pagination arguments
            'start' => $this->getOffset() ?: null,
            'size' => $this->getTake() ?: null,
            'cursor' => $this->getCursor(),

            // Add facets or statistics
            'facet' => $this->facets ? json_encode($this->facets) : null,
            'stats' => $this->statistics ? json_encode($this->statistics) : null,
            'expr' => $this->expressions ? json_encode($this->expressions) : null,

            // Add filter arguments
            'filterQuery' => $this->hasStatements() ? $this->getQuery() : null,

            // Set columns to return
            'return' => $this->getColumns() ? implode(',', $this->getColumns()) : null,
        ]);

        return $arguments;
    }

    /**
     * @return int
     */
    public function getTake(): int
    {
        return $this->take;
    }

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @return int|string
     */
    public function getCursor()
    {
        return $this->cursor;
    }

    /**
     * @return array
     */
    public function getColumns(): array
    {
        // Remove * as all selector, just return empty array
        return array_filter($this->columns, function ($column) {
            return $column !== '*';
        });
    }

    /**
     * @return array
     */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    /**
     * @param array|mixed $columns
     *
     * @return $this
     */
    public function select($columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Take a given maximum amount of results
     *
     * @param int $take
     *
     * @return $this
     */
    public function take(int $take): self
    {
        $this->take = $take;

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param int $offset
     *
     * @return $this
     * @static
     */
    public function skip(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Utilize a cursor for deep page stuff (use for more than 10k results)
     *
     * @param string $cursor
     *
     * @return $this
     */
    public function cursor(string $cursor): self
    {
        $this->cursor = $cursor;

        return $this;
    }

    /**
     * Makes sure results are sorted in a given manner
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction) == 'asc' ? 'asc' : 'desc';
        $this->orderBy[] = "{$column} {$direction}";

        return $this;
    }

    /**
     * Clears all order by statements for the current query
     *
     * @return $this
     */
    public function clearOrderBy(): self
    {
        $this->orderBy = [];

        return $this;
    }

    /**
     * @return bool
     */
    public function hasStatements(): bool
    {
        return !!$this->statements;
    }

    /**
     * Update cursor based on the result response
     *
     * @param \Aws\Result $result
     */
    private function updateCursorFromResult(\Aws\Result $result)
    {
        $newCursor = Arr::get($result->get('hits'), 'cursor');

        // Reset cursor when no cursor is set or cursor is the same as the previous cursors
        if (!$newCursor || $newCursor == $this->cursor) {
            $this->cursor = null;

            return;
        }

        $hits = Arr::get($result->get('hits'), 'hit', []);

        // The result count should be equal to the take size, else reset the cursor
        $this->cursor = count($hits) == intval($this->getTake()) ? $newCursor : null;
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param string $value
     * @param string $operator
     * @param bool $useDefault
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function prepareValueAndOperator($value, $operator, $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param string $operator
     * @param mixed $value
     *
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) &&
            !in_array($operator, ['literal', 'LITERAL', '=', '<>', '!=']);
    }
}
