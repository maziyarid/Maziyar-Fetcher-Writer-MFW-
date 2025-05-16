<?php
/**
 * Amazon Product Fetcher Class
 *
 * Handles fetching content from Amazon using the Product Advertising API 5.0.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Amazon_Fetcher extends MFW_Abstract_Fetcher {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:07:45';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Amazon API endpoints by region
     */
    private $endpoints = [
        'US' => 'webservices.amazon.com',
        'UK' => 'webservices.amazon.co.uk',
        'DE' => 'webservices.amazon.de',
        'FR' => 'webservices.amazon.fr',
        'JP' => 'webservices.amazon.co.jp',
        'CA' => 'webservices.amazon.ca',
        'IT' => 'webservices.amazon.it',
        'ES' => 'webservices.amazon.es',
        'IN' => 'webservices.amazon.in',
    ];

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array_merge(parent::get_default_config(), [
            'access_key' => '',
            'secret_key' => '',
            'associate_tag' => '',
            'region' => 'US',
            'marketplace' => 'www.amazon.com',
            'search_index' => 'All',
            'keywords' => '',
            'min_price' => '',
            'max_price' => '',
            'min_rating' => 4.0,
            'min_reviews' => 10,
            'sort' => 'featured', // featured, price-asc, price-desc, date
            'content_template' => '<div class="mfw-amazon-product">
                <h2>{title}</h2>
                <div class="product-image">
                    <a href="{url}" target="_blank">
                        <img src="{image}" alt="{title}" />
                    </a>
                </div>
                <div class="product-info">
                    <div class="price">{price}</div>
                    <div class="rating">★ {rating} ({reviews} reviews)</div>
                    <div class="description">{description}</div>
                    <div class="features">{features}</div>
                    <a href="{url}" class="buy-button" target="_blank">
                        {buy_button_text}
                    </a>
                </div>
            </div>'
        ]);
    }

    /**
     * Fetch content from Amazon
     *
     * @return array Fetch results
     */
    public function fetch() {
        if (empty($this->config['access_key']) || empty($this->config['secret_key'])) {
            throw new Exception(__('Amazon API credentials are required.', 'mfw'));
        }

        if (empty($this->config['keywords'])) {
            throw new Exception(__('Search keywords are required.', 'mfw'));
        }

        try {
            // Initialize PA-API client
            $client = $this->initialize_client();

            // Prepare search request
            $search_items_request = $this->prepare_search_request();

            // Execute search
            $response = $client->searchItems($search_items_request);
            
            // Process results
            $items = [];
            $processed_count = 0;
            $total_items = 0;

            if ($response->getSearchResult()) {
                $total_items = $response->getSearchResult()->getTotalResultCount();
                $result_items = $response->getSearchResult()->getItems();

                foreach ($result_items as $item) {
                    if (count($items) >= $this->config['items_per_fetch']) {
                        break;
                    }

                    $processed_item = $this->process_item($item);
                    if ($processed_item && $this->validate_item($processed_item)) {
                        $items[] = $processed_item;
                        $processed_count++;
                    }
                }
            }

            // Log successful fetch
            $this->log_fetch(
                $this->config['source_id'],
                'success',
                $total_items,
                $processed_count
            );

            return [
                'success' => true,
                'items' => $items,
                'total_found' => $total_items,
                'processed' => $processed_count
            ];

        } catch (Exception $e) {
            // Log failed fetch
            $this->log_fetch(
                $this->config['source_id'],
                'error',
                0,
                0,
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Initialize Amazon PA-API client
     *
     * @return \Amazon\ProductAdvertisingAPI\v1\ApiClient
     */
    private function initialize_client() {
        // Configure API client
        $config = new \Amazon\ProductAdvertisingAPI\v1\Configuration();
        
        $config->setAccessKey($this->config['access_key'])
               ->setSecretKey($this->config['secret_key'])
               ->setRegion($this->config['region'])
               ->setHost($this->endpoints[$this->config['region']])
               ->setMarketplace($this->config['marketplace']);

        return new \Amazon\ProductAdvertisingAPI\v1\ApiClient($config);
    }

    /**
     * Prepare search request
     *
     * @return \Amazon\ProductAdvertisingAPI\v1\SearchItemsRequest
     */
    private function prepare_search_request() {
        $request = new \Amazon\ProductAdvertisingAPI\v1\SearchItemsRequest();

        // Set partner tag
        $request->setPartnerTag($this->config['associate_tag']);

        // Set partner type
        $request->setPartnerType('Associates');

        // Set keywords
        $request->setKeywords($this->config['keywords']);

        // Set search index
        $request->setSearchIndex($this->config['search_index']);

        // Set resources to retrieve
        $request->setResources([
            'ItemInfo.Title',
            'ItemInfo.Features',
            'ItemInfo.ProductInfo',
            'ItemInfo.ByLineInfo',
            'Images.Primary.Large',
            'Images.Variants.Large',
            'Offers.Listings.Price',
            'CustomerReviews.Count',
            'CustomerReviews.StarRating'
        ]);

        // Add optional parameters
        if (!empty($this->config['min_price'])) {
            $request->setMinPrice($this->config['min_price']);
        }
        if (!empty($this->config['max_price'])) {
            $request->setMaxPrice($this->config['max_price']);
        }

        // Set sort method
        switch ($this->config['sort']) {
            case 'price-asc':
                $request->setSortBy('Price:LowToHigh');
                break;
            case 'price-desc':
                $request->setSortBy('Price:HighToLow');
                break;
            case 'date':
                $request->setSortBy('NewestArrivals');
                break;
            default:
                $request->setSortBy('Featured');
                break;
        }

        return $request;
    }

    /**
     * Process Amazon item
     *
     * @param \Amazon\ProductAdvertisingAPI\v1\Item $item Amazon product item
     * @return array|false Processed item or false on failure
     */
    private function process_item($item) {
        try {
            // Get basic item info
            $asin = $item->getASIN();
            $item_info = $item->getItemInfo();
            $title = $item_info->getTitle()->getDisplayValue();
            
            // Get price
            $offers = $item->getOffers();
            $price = '';
            if ($offers && $offers->getListings()) {
                $price = $offers->getListings()[0]->getPrice()->getDisplayAmount();
            }

            // Get reviews
            $reviews = $item->getCustomerReviews();
            $rating = 0;
            $review_count = 0;
            if ($reviews) {
                $rating = $reviews->getStarRating()->getValue();
                $review_count = $reviews->getCount();
            }

            // Skip if doesn't meet rating/review criteria
            if ($rating < $this->config['min_rating'] || $review_count < $this->config['min_reviews']) {
                return false;
            }

            // Get images
            $images = $item->getImages();
            $image_url = '';
            if ($images && $images->getPrimary()) {
                $image_url = $images->getPrimary()->getLarge()->getURL();
            }

            // Get features
            $features = [];
            if ($item_info->getFeatures()) {
                $features = $item_info->getFeatures()->getDisplayValues();
            }

            // Build product URL with affiliate tag
            $product_url = sprintf(
                'https://%s/dp/%s?tag=%s',
                $this->config['marketplace'],
                $asin,
                $this->config['associate_tag']
            );

            // Build content using template
            $content = $this->build_content([
                'title' => $title,
                'url' => $product_url,
                'image' => $image_url,
                'price' => $price,
                'rating' => $rating,
                'reviews' => $review_count,
                'description' => implode("\n", $features),
                'features' => $this->format_features($features),
                'buy_button_text' => sprintf(
                    __('Buy on Amazon for %s', 'mfw'),
                    $price
                )
            ]);

            return [
                'title' => $title,
                'content' => $content,
                'source_url' => $product_url,
                'publish_date' => date('Y-m-d H:i:s', strtotime($this->current_time)),
                'author' => __('Amazon Product', 'mfw'),
                'image_url' => $image_url,
                'product_data' => [
                    'asin' => $asin,
                    'price' => $price,
                    'rating' => $rating,
                    'reviews' => $review_count
                ]
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process Amazon item: %s', $e->getMessage()),
                'amazon'
            );
            return false;
        }
    }

    /**
     * Build content using template
     *
     * @param array $data Template data
     * @return string Formatted content
     */
    private function build_content($data) {
        $content = $this->config['content_template'];

        foreach ($data as $key => $value) {
            $content = str_replace(
                '{' . $key . '}',
                $value,
                $content
            );
        }

        return $content;
    }

    /**
     * Format product features
     *
     * @param array $features Product features
     * @return string Formatted features HTML
     */
    private function format_features($features) {
        if (empty($features)) {
            return '';
        }

        $html = '<ul class="product-features">';
        foreach ($features as $feature) {
            $html .= sprintf('<li>%s</li>', esc_html($feature));
        }
        $html .= '</ul>';

        return $html;
    }
}