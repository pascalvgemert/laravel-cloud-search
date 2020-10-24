<?php

namespace LaravelCloudSearch\Contracts;

interface FieldType {
    const INT = 'int';
    const INTEGER = 'integer';
    const REAL = 'real';
    const FLOAT = 'float';
    const DOUBLE = 'double';
    const DECIMAL = 'decimal';
    const STRING = 'string';
    const BOOL = 'bool';
    const BOOLEAN = 'boolean';
    const OBJECT = 'object';
    const JSON = 'json';
    const ARRAY = 'array';
    const STRING_ARRAY = 'string_array';
    const LITERAL_ARRAY = 'literal_array';
    const INT_ARRAY = 'int_array';
    const INTEGER_ARRAY = 'integer_array';
    const FLOAT_ARRAY = 'float_array';
    const COLLECTION = 'collection';
    const DATE = 'date';
    const DATETIME = 'datetime';
    const CUSTOM_DATETIME = 'custom_datetime';
    const TIMESTAMP = 'timestamp';
}
