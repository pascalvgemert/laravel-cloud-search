<?php

namespace LaravelCloudSearch;

use Illuminate\Support\Collection;

class DocumentCollection extends Collection
{
    /** @var int */
    protected $rowsFound = 0;

    /** @var Collection */
    protected $facets;

    /** @var Collection */
    protected $statistics;

    /**
     * @param int $rowsFound
     *
     * @return $this
     */
    public function setRowsFound(int $rowsFound): self
    {
        $this->rowsFound = $rowsFound;

        return $this;
    }

    /**
     * @param array $facets
     *
     * @return $this
     */
    public function setFacets(array $facets): self
    {
        if (!is_array($facets)) {
            return $this;
        }

        $mappedFacets = [];

        foreach ($facets as $name => $result) {
            $mappedFacets[] = new Facet($name, $result['buckets'] ?? []);
        }

        $this->facets = collect($mappedFacets);

        return $this;
    }

    /**
     * @param array $facets
     *
     * @return $this
     */
    public function setStatistics(array $statistics): self
    {
        $this->statistics = collect($statistics);

        return $this;
    }
}
