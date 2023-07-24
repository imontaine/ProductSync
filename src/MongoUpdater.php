<?php

class MongoUpdater
{

    public static $EXCLUDE_FIELDS = [
        "additional_image_labels", "bundle_price_type", "bundle_price_view", "bundle_shipment_type", "bundle_sku_type", "bundle_values", "bundle_weight_type", "country_of_manufacture", "crosssell_position", "crosssell_skus", "custom_design", "custom_design_from", "custom_design_to", "custom_layout_update", "custom_options", "deferred_stock_update", "display_product_options_in", "downloadable_links", "downloadable_samples", "enable_qty_increments", "gift_message_available", "giftcard_allow_message", "giftcard_allow_open_amount", "giftcard_amount", "giftcard_email_template", "giftcard_is_redeemable", "giftcard_lifetime", "giftcard_open_amount_max", "giftcard_open_amount_min", "giftcard_type", "hide_from_product_page", "is_decimal_divided", "is_qty_decimal", "max_cart_qty", "notify_on_stock_below", "out_of_stock_qty", "product_online", "qty_increments", "related_position", "related_skus", "upsell_position", "upsell_skus", "use_config_allow_message", "use_config_backorders", "use_config_deferred_stock_update", "use_config_email_template", "use_config_enable_qty_inc", "use_config_is_redeemable", "use_config_lifetime", "use_config_manage_stock", "use_config_max_sale_qty", "use_config_min_qty", "use_config_min_sale_qty", "use_config_notify_stock_qty", "use_config_qty_increments", "visibility",
        // Attributes from additional_attributes
        "amxnotif_hide_alert", "aw_os_category_text", "aw_os_product_text", "canonical_cross_domain", "disable_amazonpayments", "featured_product_image", "gift_wrapping_price", "price_promo_text_grid", "shop_options", "show_in_crosssell_result"
    ];

    public static $DATE_FIELDS = [
        ['created_at', 'm/d/y, g:i A'],
        ['updated_at', 'm/d/y, g:i A'],
        ['new_from_date', 'm/d/y'],
        ['new_to_date', 'm/d/y']
    ];

    public function __construct(
        protected readonly string $uri,
        protected readonly string $namespace
    ) {
    }

    public function run(array $documents): array
    {
        $manager = new MongoDB\Driver\Manager($this->uri);
        $bulk = new MongoDB\Driver\BulkWrite(['ordered' => false]);

        foreach ($documents as $document) {
            $attr = &$document[1]['$set'];

            // Convert date fields
            foreach (self::$DATE_FIELDS as $dateField) {
                $attrName = $dateField[0];
                if (isset($attr[$attrName]) && !empty($attr[$attrName])) {
                    $attr[$attrName] = $this->convertToMongoDate($attr[$attrName], $dateField[1]);
                }
            }
            // Exclude fields
            foreach (self::$EXCLUDE_FIELDS as $field) {
                if (isset($attr[$field]) && !empty($attr[$field])) {
                    unset($attr[$field]);
                }
            }

            // Add 'mongo_updated_at' timestamp
            $attr['mongo_updated_at'] = new MongoDB\BSON\UTCDateTime();

            $bulk->update($document[0], $document[1], $document[2]);
        }

        $execute = $manager->executeBulkWrite($this->namespace, $bulk);
        $result = [
            'inserted' => $execute->getInsertedCount(),
            'matched' => $execute->getMatchedCount(),
            'modified' => $execute->getModifiedCount(),
            'upserted' => $execute->getUpsertedCount(),
            'deleted' => $execute->getDeletedCount()
        ];

        return $result;
    }

    protected function convertToMongoDate($date, $format) {
        return new MongoDB\BSON\UTCDateTime(DateTime::createFromFormat($format, $date)->getTimestamp() * 1000);
    }
}