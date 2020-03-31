<?php

namespace LaravelCloudSearch\Traits;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait HasFields
{
    /**
     * The document's attributes.
     *
     * @var array
     */
    protected $fields = [];

    /**
     * The storage format of the document's date columns.
     *
     * @var string
     */
    protected $dateFormat;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * @param mixed $fields
     *
     * @param array $defaultFields
     *
     * @return $this
     */
    public function setFields($fields, array $defaultFields = []): self
    {
        foreach ($fields as $field => $value) {
            $defaultTypes = $defaultFields[$field] ?? [];

            // Handle casted fields
            if ($this->hasCast($field)) {
                $castType = $this->casts[$field] ?? '';

                $this->fields[$field] = Str::contains($castType, 'array') ? $value : current($value);

                continue;
            }

            // Determine field type
            $fieldType = $this->determineFieldType($value, $defaultTypes);

            // store are type
            if (!$fieldType) {
                $this->fields[$field] =  current($value);

                continue;
            }

            $fieldValue = Str::contains($fieldType, 'array') ? $value : current($value);

            settype($fieldValue, $fieldType);

            $this->fields[$field] = $fieldValue;
        }

        // Set missing default properties, because CloudSearch only returns fields available in the document
        $this->setMissingDefaultProperties($defaultFields);

        // @todo Sort fields for readiblity or not because of performance
        // ksort($this->fields);

        return $this;
    }

    /**
     * @param mixed $value
     * @param array $defaultFields
     *
     * @return string|null
     */
    private function determineFieldType($value, array $defaultFields): ?string
    {
        if (in_array('null', $defaultFields)) {
            return 'null';
        }

        if (in_array('array', $defaultFields)) {
            return 'array';
        }

        if (count($defaultFields) === 1) {
            return current($defaultFields);
        }

        return is_array($value) && count($value) === 1 ? null : 'array';
    }

    /**
     * @param array $defaultProperties
     */
    private function setMissingDefaultProperties(array $defaultProperties)
    {
        foreach ($defaultProperties as $field => $types) {
            if (Arr::has($this->fields, $field)) {
                continue;
            }

            $type = count($types) === 1 ? current($types) : 'null';

            $fieldValue = null;

            settype($fieldValue, $type);

            $this->fields[$field] = $fieldValue;
        }
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return $this
     */
    public function setField(string $field, $value): self
    {
        $this->fields[$field] = $value;

        return $this;
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return array_map(function ($field) {
            return $this->getField($field);
        }, array_keys($this->fields));
    }

    /**
     * @param string $field
     *
     * @return mixed
     */
    public function getField(string $field)
    {
        if (!$field) {
            return null;
        }

        $value = $this->fields[$field] ?? null;

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($field)) {
            return $this->castAttribute($field, $value);
        }

        return $value;
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    protected function hasCast(string $field): bool
    {
        return Arr::has($this->casts ?? [], $field);
    }

    /**
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castAttribute(string $field, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($this->casts[$field] ?? null) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->casts[$field], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'string_array':
            case 'literal_array':
                return is_array($value) ? $value : (array)$value;
            case 'json':
                return $this->fromJson($value);
            case 'int_array':
            case 'integer_array':
                $array = is_array($value) ? $value : (array)$value;

                return array_map('intval', $array);
            case 'float_array':
                $array = is_array($value) ? $value : (array)$value;

                return array_map('floatval', $array);
            case 'collection':
                return new Collection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimestamp($value);
            default:
                return $value;
        }
    }

    /**
     * Encode the given value as JSON.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function asJson($value): string
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param  string  $value
     * @param  bool  $asObject
     * @return mixed
     */
    public function fromJson($value, $asObject = false)
    {
        return json_decode($value, !$asObject);
    }

    /**
     * Decode the given float.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function fromFloat($value)
    {
        switch ((string) $value) {
            case 'Infinity':
                return INF;
            case '-Infinity':
                return -INF;
            case 'NaN':
                return NAN;
            default:
                return (float) $value;
        }
    }

    /**
     * Return a decimal as string.
     *
     * @param  float $value
     * @param  int $decimals
     * @return string
     */
    protected function asDecimal($value, $decimals): string
    {
        return number_format($value, $decimals, '.', '');
    }

    /**
     * Return a timestamp as DateTime object with time set to 00:00:00.
     *
     * @param mixed $value
     *
     * @return Carbon
     */
    protected function asDate($value): Carbon
    {
        return $this->asDateTime($value)->startOfDay();
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param mixed $value
     *
     * @return Carbon
     */
    protected function asDateTime($value): Carbon
    {
        // If this value is already a Carbon instance, we shall just return it as is.
        // This prevents us having to re-instantiate a Carbon instance when we know
        // it already is one, which wouldn't be fulfilled by the DateTime check.
        if ($value instanceof Carbon || $value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        if ($this->isStandardDateFormat($value)) {
            return Carbon::instance(Carbon::createFromFormat('Y-m-d', $value)->startOfDay());
        }

        $format = $this->getDateFormat();

        // https://bugs.php.net/bug.php?id=75577
        if (version_compare(PHP_VERSION, '7.3.0-dev', '<')) {
            $format = str_replace('.v', '.u', $format);
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        return Carbon::createFromFormat($format, $value);
    }

    /**
     * Determine if the given value is a standard date format.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isStandardDateFormat($value): bool
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  mixed  $value
     * @return string|null
     */
    public function fromDateTime($value): ?string
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Return a timestamp as unix timestamp.
     *
     * @param  mixed  $value
     * @return int
     */
    protected function asTimestamp($value): int
    {
        return $this->asDateTime($value)->getTimestamp();
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }
}
