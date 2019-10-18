<?php

class ProductCatalog extends Document
{
    /** @var string */
    protected $domain = 'http://search-startselect-products-s2trfdvft22yds7q3aon4wqjwa.eu-west-1.cloudsearch.amazonaws.com';
}

$builder = ProductCatalog::where('prices_available', 1)
    ->where('searchable_in_app', 1)
    ->where(function ($query) {
        /** @var \App\Models\Product[]|\Illuminate\Database\Eloquent\Collection $disallowedProductTypes */
        $disallowedProductTypes = ProductType::lookupManyFromCache([
            ProductType::GAME, ProductType::IN_GAME_CREDIT, ProductType::DLC, ProductType::SUBSCRIPTION,
        ]);

        $query
            ->whereNotIn('product_type', $disallowedProductTypes->pluck('id'))
            ->orWhere('key_provider', 40);
    });

dd($builder->toQuery());
