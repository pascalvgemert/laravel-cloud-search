# laravel-cloud-search
An Eloquent way to use CloudSearch within Laravel

### Requires Laravel 5.5 or higher and the Laravel AWS package!

## Installation

You can install the package via composer:

``` bash
composer require pascalvgemert/laravel-cloud-search
```

To install the AWS package follow the steps in the README.md on:
[AWS Service Provider for Laravel 5 & 6](https://github.com/aws/aws-sdk-php-laravel)

## Usage

Instead of using Models, you create Documents. They almost work the same as a Model Class.

Example:

```php
/**
 * Define your CloudSearch index fields here, this will help to define default values in your document result:
 *
 * @property-read int $id
 * @property-read string $title
 * @property-read string $description
 * @property-read string $country_code
 * @property-read array $images
 * @property-read int $stock
 * @property-read bool $pre_order
 */
class Product extends \LaravelCloudSearch\Document
{
    /** @var string */
    protected $domain = 'http://your-domain-url-for-cloudsearch.eu-west-1.cloudsearch.amazonaws.com';

    /** @var array */
    protected $casts = [
        'images' => 'array',
        'pre_order' => 'bool',
        'searchable' => 'bool',
    ];
}
```

Now you can use this Document to query it like you would query an Eloquent model.

Example:

```php
/** @var \LaravelCloudSearch\DocumentCollection|\LaravelCloudSearch\Document[] **/
$products = Product::select('id')
    ->where('country_code', 'NL')
    ->where(function ($query) {
        $query
            ->where('stock', '>', 0)
            ->orWhere('pre_order', 1);
    })
    ->orderBy('price', 'asc')
    ->take(10)
    ->get();
```

## Debugging

To debug your build query, you can use the `toQuery()` method just like Eloquent.

## Searching

To fuzzy search you can use the `phrase(string $searchPhrase, int|float $fuzziness = null, bool $lookForAnyWord = false)` method.

The `$fuzziness` is a decimal percentage 0 to 1 where the default is 0.25.
The `$lookForAnyWord` is a boolean to search for all or any words, default is all words.

## Facets

A much used functionality of CloudSearch is the use of Facets / Buckets.
This can easily be taking in account while making your query:

```php
/** @var \LaravelCloudSearch\DocumentCollection **/
$products = Product::facet('country_code', ['size' => 10])->get();

/** @var \Illuminate\Support\Collection|\LaravelCloudSearch\Facet[] **/
$productFacets = $products->getFacets();
```

## Statistics (stats)

The same goes for Statistics or stats as they're called in AWS CloudSearch:

```php
/** @var \LaravelCloudSearch\DocumentCollection **/
$products = Product::statistics('country_code')->get();

/** @var \Illuminate\Support\Collection **/
$productStatistics = $products->getStatistics();
```
