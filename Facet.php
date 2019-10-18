<?php

namespace LaravelCloudSearch;

class Facet
{
    /** @var string */
    protected $name;

    /** @var array */
    protected $buckets = [];

    /**
     * Facet constructor.
     *
     * @param string $name
     * @param array $buckets
     */
    public function __construct(string $name, array $buckets)
    {
        $this->name = $name;
        $this->buckets = $buckets;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getBuckets(): Collection
    {
        return collect($this->buckets);
    }
}
