<?php

namespace LaravelCloudSearch\Exceptions;

use Exception;
use Illuminate\Support\Arr;

class DocumentNotFoundException extends Exception
{
    /**
     * Name of the affected Eloquent model.
     *
     * @var string
     */
    protected $document;

    /**
     * The affected model IDs.
     *
     * @var int|array
     */
    protected $ids;

    /**
     * Set the affected CloudSearch Document and instance ids.
     *
     * @param string document
     * @param int|array $ids
     * @return $this
     */
    public function setModel($document, $ids = [])
    {
        $this->document = $document;
        $this->ids = Arr::wrap($ids);

        $this->message = "No query results for model [{document}]";

        if (count($this->ids) > 0) {
            $this->message .= ' '.implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected CloudSearch document.
     *
     * @return string
     */
    public function getDocument()
    {
        return $this->document;
    }

    /**
     * Get the affected CloudSearch document IDs.
     *
     * @return int|array
     */
    public function getIds()
    {
        return $this->ids;
    }
}