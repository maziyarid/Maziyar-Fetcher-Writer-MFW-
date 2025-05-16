<?php
// Affiliate Manager (Amazon, eBay, generic)

class MFW_Affiliate_Manager {

    public static function inject_affiliate_links($content, $source = '') {
        // Placeholder logic, customize with real API/keyword matching
        if ($source === 'amazon') {
            $content .= "\n<p><a href='https://www.amazon.com/dp/B000123456?tag=yourtag'>Check it on Amazon</a></p>";
        } elseif ($source === 'ebay') {
            $content .= "\n<p><a href='https://www.ebay.com/itm/1234567890'>See deal on eBay</a></p>";
        }
        return $content;
    }
}
