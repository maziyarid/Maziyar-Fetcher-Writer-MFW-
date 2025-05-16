<?php
/**
 * eBay Fetcher Class
 *
 * Handles fetching content from eBay using the Finding API and Browse API.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Ebay_Fetcher extends MFW_Abstract_Fetcher {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:08:41';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * eBay API endpoints
     */
    private const API_ENDPOINTS = [
        'production' => [
            'finding' => 'https://svcs.ebay.com/services/search/FindingService/v1',
            'browse' => 'https://api.ebay.com/buy/browse/v1'
        ],
        'sandbox' => [
            'finding' => 'https://svcs.sandbox.ebay.com/services/search/FindingService/v1',
            'browse' => 'https://api.sandbox.ebay.com/buy/browse/v1'
        ]
    ];

    /**
     * Get default configuration
     *
     * @return array Default configuration
     */
    protected function get_default_config() {
        return array_merge(parent::get_default_config(), [
            'app_id' => '',
            'cert_id' => '',
            'dev_id' => '',
            'auth_token' => '',
            'sandbox_mode' => false,
            'marketplace_id' => 'EBAY-US',
            'search_keywords' => '',
            'category_id' => '',
            'min_price' => '',
            'max_price' => '',
            'condition' => ['New', 'Used'], // New, Used, Unspecified
            'listing_type' => ['FixedPrice', 'Auction'], // FixedPrice, Auction, AuctionWithBIN
            'sort_order' => 'BestMatch', // BestMatch, CurrentPriceHighest, PricePlusShippingHighest, etc.
            'content_template' => '<div class="mfw-ebay-item">
                <h2>{title}</h2>
                <div class="item-image">
                    <a href="{url}" target="_blank">
                        <img src="{image}" alt="{title}" />
                    </a>
                </div>
                <div class="item-info">
                    <div class="price">{price}</div>
                    <div class="shipping">{shipping}</div>
                    <div class="condition">{condition}</div>
                    <div class="description">{description}</div>
                    <div class="seller">
                        {seller_info}
                    </div>
                    <a href="{url}" class="buy-button" target="_blank">
                        {buy_button_text}
                    </a>
                </div>
            </div>'
        ]);
    }

    /**
     * Fetch content from eBay
     *
     * @return array Fetch results
     */
    public function fetch() {
        if (empty($this->config['app_id'])) {
            throw new Exception(__('eBay API credentials are required.', 'mfw'));
        }

        if (empty($this->config['search_keywords'])) {
            throw new Exception(__('Search keywords are required.', 'mfw'));
        }

        try {
            // Get items using Finding API
            $finding_results = $this->search_items();
            
            $items = [];
            $processed_count = 0;
            $total_items = 0;

            if (!empty($finding_results['items'])) {
                $total_items = $finding_results['total'];

                foreach ($finding_results['items'] as $item) {
                    if (count($items) >= $this->config['items_per_fetch']) {
                        break;
                    }

                    // Get detailed item info using Browse API
                    $detailed_item = $this->get_item_details($item['itemId']);
                    if ($detailed_item) {
                        $processed_item = $this->process_item($detailed_item);
                        if ($processed_item && $this->validate_item($processed_item)) {
                            $items[] = $processed_item;
                            $processed_count++;
                        }
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
     * Search items using Finding API
     *
     * @return array Search results
     */
    private function search_items() {
        $endpoint = self::API_ENDPOINTS[
            $this->config['sandbox_mode'] ? 'sandbox' : 'production'
        ]['finding'];

        // Build request parameters
        $params = [
            'OPERATION-NAME' => 'findItemsAdvanced',
            'SERVICE-VERSION' => '1.0.0',
            'SECURITY-APPNAME' => $this->config['app_id'],
            'RESPONSE-DATA-FORMAT' => 'JSON',
            'REST-PAYLOAD' => true,
            'keywords' => $this->config['search_keywords'],
            'paginationInput.entriesPerPage' => $this->config['items_per_fetch'],
            'sortOrder' => $this->config['sort_order'],
            'outputSelector' => ['AspectHistogram', 'SellerInfo', 'PictureURLSuperSize']
        ];

        // Add optional parameters
        if (!empty($this->config['category_id'])) {
            $params['categoryId'] = $this->config['category_id'];
        }

        if (!empty($this->config['min_price'])) {
            $params['itemFilter.name'] = 'MinPrice';
            $params['itemFilter.value'] = $this->config['min_price'];
        }

        if (!empty($this->config['max_price'])) {
            $params['itemFilter.name'] = 'MaxPrice';
            $params['itemFilter.value'] = $this->config['max_price'];
        }

        // Make API request
        $response = wp_remote_get(add_query_arg($params, $endpoint), [
            'timeout' => 30,
            'headers' => [
                'X-EBAY-SOA-GLOBAL-ID' => $this->config['marketplace_id']
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['findItemsAdvancedResponse'][0]['searchResult'][0])) {
            return ['items' => [], 'total' => 0];
        }

        $result = $data['findItemsAdvancedResponse'][0]['searchResult'][0];
        
        return [
            'items' => $result['item'] ?? [],
            'total' => (int)$result['@count']
        ];
    }

    /**
     * Get detailed item information using Browse API
     *
     * @param string $item_id eBay item ID
     * @return array|false Item details or false on failure
     */
    private function get_item_details($item_id) {
        $endpoint = self::API_ENDPOINTS[
            $this->config['sandbox_mode'] ? 'sandbox' : 'production'
        ]['browse'] . "/item/{$item_id}";

        // Make API request
        $response = wp_remote_get($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_access_token(),
                'X-EBAY-C-MARKETPLACE-ID' => $this->config['marketplace_id'],
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            MFW_Error_Logger::log(
                sprintf('Failed to get eBay item details: %s', $response->get_error_message()),
                'ebay'
            );
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Process eBay item
     *
     * @param array $item eBay item data
     * @return array|false Processed item or false on failure
     */
    private function process_item($item) {
        try {
            // Build content using template
            $content = $this->build_content([
                'title' => $item['title'],
                'url' => $item['itemWebUrl'],
                'image' => $item['image']['imageUrl'] ?? '',
                'price' => $this->format_price($item['price']),
                'shipping' => $this->format_shipping($item['shippingOptions'][0] ?? null),
                'condition' => $item['condition'] ?? __('Not specified', 'mfw'),
                'description' => $item['shortDescription'] ?? '',
                'seller_info' => $this->format_seller_info($item['seller'] ?? []),
                'buy_button_text' => sprintf(
                    __('Buy on eBay for %s', 'mfw'),
                    $this->format_price($item['price'])
                )
            ]);

            return [
                'title' => $item['title'],
                'content' => $content,
                'source_url' => $item['itemWebUrl'],
                'publish_date' => date('Y-m-d H:i:s', strtotime($this->current_time)),
                'author' => __('eBay Item', 'mfw'),
                'image_url' => $item['image']['imageUrl'] ?? '',
                'product_data' => [
                    'item_id' => $item['itemId'],
                    'price' => $item['price'],
                    'condition' => $item['condition'] ?? '',
                    'seller' => $item['seller']['username'] ?? ''
                ]
            ];

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to process eBay item: %s', $e->getMessage()),
                'ebay'
            );
            return false;
        }
    }

    /**
     * Format price display
     *
     * @param array $price Price data
     * @return string Formatted price
     */
    private function format_price($price) {
        if (!$price) {
            return __('Price not available', 'mfw');
        }

        return sprintf(
            '%s %s',
            $price['currency'],
            number_format($price['value'], 2)
        );
    }

    /**
     * Format shipping information
     *
     * @param array|null $shipping Shipping data
     * @return string Formatted shipping info
     */
    private function format_shipping($shipping) {
        if (!$shipping) {
            return __('Shipping not specified', 'mfw');
        }

        if ($shipping['shippingCost']['value'] === 0) {
            return __('Free Shipping', 'mfw');
        }

        return sprintf(
            __('Shipping: %s %s', 'mfw'),
            $shipping['shippingCost']['currency'],
            number_format($shipping['shippingCost']['value'], 2)
        );
    }

    /**
     * Format seller information
     *
     * @param array $seller Seller data
     * @return string Formatted seller info
     */
    private function format_seller_info($seller) {
        if (empty($seller)) {
            return '';
        }

        return sprintf(
            '<div class="seller-info">
                <p>%s: %s</p>
                <p>%s: %s%%</p>
                <p>%s: %s</p>
            </div>',
            __('Seller', 'mfw'),
            esc_html($seller['username']),
            __('Feedback Score', 'mfw'),
            esc_html($seller['feedbackPercentage']),
            __('Feedback Rating', 'mfw'),
            esc_html($seller['feedbackScore'])
        );
    }

    /**
     * Get OAuth access token
     *
     * @return string Access token
     */
    private function get_access_token() {
        // Check cache first
        $cached_token = get_transient('mfw_ebay_access_token');
        if ($cached_token) {
            return $cached_token;
        }

        // Get new token
        $response = wp_remote_post('https://api.ebay.com/identity/v1/oauth2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode(
                    $this->config['app_id'] . ':' . $this->config['cert_id']
                )
            ],
            'body' => [
                'grant_type' => 'client_credentials',
                'scope' => 'https://api.ebay.com/oauth/api_scope'
            ]
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            throw new Exception(__('Failed to get eBay access token.', 'mfw'));
        }

        // Cache token
        set_transient('mfw_ebay_access_token', $data['access_token'], $data['expires_in'] - 60);

        return $data['access_token'];
    }
}