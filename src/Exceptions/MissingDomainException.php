<?php

namespace LaravelCloudSearch\Exceptions;

use Exception;
use LaravelCloudSearch\Document;

class MissingDomainException extends Exception
{
    /**
     * MissingDomainException constructor.
     *
     * @param Document $document
     */
    public function __construct(Document $document)
    {
        $documentClassName = get_class($document);

        parent::__construct("No domain specified for document: {$documentClassName}.");
    }
}