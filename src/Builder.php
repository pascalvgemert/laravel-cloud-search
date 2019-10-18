<?php

namespace LaravelCloudSearch;

use Aws\CloudSearchDomain\CloudSearchDomainClient;
use Aws\Sdk;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use LaravelCloudSearch\Exceptions\DocumentNotFoundException;

/**
 * Class Builder
 *
 * @mixin Query
 */
class Builder
{
    /** @var \LaravelCloudSearch\Document */
    protected $document;

    /** @var \LaravelCloudSearch\Query; */
    protected $query;

    /**
     * @param \LaravelCloudSearch\Query $query;
     */
    public function __construct(Query $query)
    {
        $this->query = $query;
    }

    /**
     * @param Document $document
     *
     * @return $this
     */
    public function setDocument(Document $document): self
    {
        $this->document = $document;

        $this->query->setClient($this->getClient());

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param string|array|\Closure $column
     * @param mixed $operator
     * @param mixed $value
     * @param string $boolean
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = Query::MATCH_AND): self
    {
        $this->query->where(...func_get_args());

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param \Closure|array|string $column
     * @param mixed $operator
     * @param mixed $value
     *
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null): self
    {
        list($value, $operator) = $this->query->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($column, $operator, $value, Query::MATCH_OR);
    }

    /**
     * @param mixed $id
     * @param array|string|null $columns
     *
     * @return \LaravelCloudSearch\Document|null
     * @throws Exceptions\QueryException
     */
    public function find($id, $columns = null): ?Document
    {
        return $this->where($this->document->getKeyName(), $id)->select($columns)->first();
    }

    /**
     * @param array $ids
     * @param array|string|null $columns
     *
     * @return \LaravelCloudSearch\DocumentCollection
     * @throws Exceptions\QueryException
     */
    public function findMany(array $ids, $columns = null): DocumentCollection
    {
        return $this->whereIn($this->document->getKeyName(), $ids)->select($columns)->get();
    }

    /**
     * @param mixed $id
     * @param array|string|null $columns
     *
     * @return \LaravelCloudSearch\Document
     * @throws \LaravelCloudSearch\Exceptions\DocumentNotFoundException
     */
    public function findOrFail($id, $columns = null): Document
    {
        $result = $this->find($id, $columns);

        if ($result) {
            return $result;
        }

        throw (new DocumentNotFoundException)->setDocument(
            get_class($this->document), $id
        );
    }

    /**
     * @param array|string|null $columns
     *
     * @return \LaravelCloudSearch\DocumentCollection
     */
    public function get($columns = null): DocumentCollection
    {
        // Map result to Document Collection
        return $this->select($columns)->processResult(
            $this->query->run()
        );
    }

    /**
     * @return \LaravelCloudSearch\Document|null
     */
    public function first(): ?Document
    {
        return $this->get()->first();
    }

    /**
     * @return \LaravelCloudSearch\Document
     * @throws \LaravelCloudSearch\Exceptions\DocumentNotFoundException
     */
    public function firstOrFail(): Document
    {
        $result = $this->get()->first();

        if ($result) {
            return $result;
        }

        throw (new DocumentNotFoundException)->setDocument(
            get_class($this->document)
        );
    }

    /**
     * @return \Aws\CloudSearchDomain\CloudSearchDomainClient
     */
    protected function getClient(): CloudSearchDomainClient
    {
        return App::make(Sdk::class)->createClient('CloudSearchDomain', [
            'endpoint' => $this->document->getDomain(),
        ]);
    }

    /**
     * @param \Aws\Result $result
     *
     * @return \LaravelCloudSearch\DocumentCollection
     */
    protected function processResult(\Aws\Result $result): DocumentCollection
    {
        $hits = Arr::get($result->get('hits'), 'hit', []);
        $rowsFound = Arr::get($result->get('hits'), 'found', 0);

        /** @var DocumentCollection $collection */
        $collection = $this->hydrateDocuments($hits, $this->query->getColumns());

        $collection->setRowsFound($rowsFound);

        if ($facets = $result->get('facets')) {
            $collection->setFacets($facets);
        }

        if ($statistics = $result->get('stats')) {
            $collection->setStatistics($statistics);
        }

        return $collection;
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param array $items
     * @param array $columns
     *
     * @return \LaravelCloudSearch\DocumentCollection
     */
    public function hydrateDocuments(array $items, array $columns): DocumentCollection
    {
        // Get default document properties from the PHP docblock
        $defaultProperties = $this->getDocumentDefaultProperties();

        return new DocumentCollection(array_map(function ($item) use ($columns, $defaultProperties) {
            return $this->document
                ->newInstance()
                ->setId(Arr::get($item, 'id'))
                ->setFields(Arr::get($item, 'fields'), $defaultProperties);
        }, $items));
    }

    /**
     * @return array
     */
    protected function getDocumentDefaultProperties(): array
    {
        $defaultProperties = [];

        try {
            $reflectionClass = new \ReflectionClass($this->document);

            preg_match_all('/@(property|property-read)\s+([\w\\|]+)\s+\$(\w+)/', $reflectionClass->getDocComment(), $matches, PREG_SET_ORDER);
        } catch (\ReflectionException $exception) {
            return $defaultProperties;
        }

        foreach ($matches as $match) {
            list($property, $propertyTag, $propertyType, $propertyName) = $match;

            $propertyTypes = explode('|', $propertyType);

            $defaultProperties[$propertyName] = $propertyTypes;
        }

        return $defaultProperties;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query->getQuery();
    }

    /**
     * Dynamically handle calls into the query instance.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $this->query->{$method}(...$parameters);

        return $this;
    }
}
