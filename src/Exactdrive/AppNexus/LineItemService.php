<?php

namespace Exactdrive\AppNexus;

//-----------------------------------------------------------------------------
// LineItemService.php
//-----------------------------------------------------------------------------

/**
 * AppNexus Line Item Api service.
 *
 * @package AppNexus
 * @author Moiz Merchant <moiz@exactdrive.com>
 * @version $Id$
 */
class LineItemService extends Api
{

    //-------------------------------------------------------------------------
    // static fields
    //-------------------------------------------------------------------------

    /**
     * Line item properties which can be updated with AppNexus server.
     *   https://wiki.appnexus.com/pages/viewpage.action?title=Line+Item+Service&spaceKey=api#LineItemService-JSONFields
     *
     * @var array
     */
    public static $fields = array(
        'name',                             // name of the line item
        'advertiser_id',                    // id of the advertiser which the line item belongs
        'currency',                         // currency used for this line item
        'code',                             // custom code for the line item.
        'state',                            // 'active' / 'inactive'
        'pixels',                           // conversion pixels being used for CPA revenue type
        'enable_pacing',                    // if true, daily budgeted spend is spread out evenly throughout a day
        'lifetime_pacing',                  // if true, line item will attempt to spend its overall lifetime budget evenly over line item flight dates
        'lifetime_pacing_span',             // in event of an underspend event, this indicates the number of days across which underspent amount will be distributed
        'allow_safety_pacing',              // if true, spend per minute is limited to a maximum of 1% of lifetime budget and 5% of daily budget
        'start_date',                       // date and time when the line item should start serving
        'end_date',                         // date and time when the line item should stop serving
        'lifetime_budget',                  // lifetime budget in revenue
        'lifetime_budget_imps',             // lifetime budget in impressions
        'daily_budget',                     // daily budget in revenue
        'daily_budget_imps',                // daily budget in impressions
        'revenue_type',                     // way the advertiser has agreed to pay: 'none', 'cpm', 'cpc', 'cpa', 'cost_plus_cpm', 'cost_plus_margin', 'flat_fee'
        'revenue_value',                    // amount paid to the network by the advertiser
        'timezone',                         // timezone by which budget and spend are counted
        'goal_type',                        // the way that the advertiser would like to measure campaign optimization: 'none', 'cpc', 'cpa', 'ctr'
        'goal_pixels',                      // pixels used for conversion tracking, as well as the post-view and post-click revenue
        'valuation',                        // the performance goal threshold
        'creatives',                        // creatives associated with line item
        'manage_creative',                  // if true, creatives are managed at the line item level. if false, creatives are managed at the campaign level
        'payout_margin',                    // payout margin on performance offer line items
        'click_url',                        // click URL to apply at line item level
        'require_cookie_for_tracking',      // true, a cookie is required for conversion tracking
        'labels',                           // optional labels applied to the line item: 'Trafficker', 'Sales Rep', 'Line Item Type'
        'broker_fees',                      // commissions that the network must pass to brokers when serving an ad
        'profile_id',                       // associate an optional profile_id with line item
        'insertion_order_id',               // id of the current active insertion order
        'comments',                         // comments about the line item
        'is_malicious',                     // if true, then the line item's status will be set to inactive
        'insertion_orders',                 // objects containing metadata for the insertion orders
        'flat_fee',                         // flat fees associated with line items
        'flat_fee.flat_fee_status',         // status of flat fee disbursement
        'flat_fee.flat_fee_allocation_date' // date when the flat fee revenue is scheduled to be allocated to publishers
    );

    //-------------------------------------------------------------------------
    // static methods
    //-------------------------------------------------------------------------

    /**
     * AppNexus line item service url.
     */
    public static function getBaseUrl()
    {
        $url = Api::getBaseUrl() . '/line-item';
        return $url;
    }

    //-------------------------------------------------------------------------

    /**
     * Add a new line-item.
     *
     * @param  int  $advertiserId => Advertiser id of line item.
     * @param  hash $lineItem     => Only valid fields will be passed to api.
     * @return hash $lineItem     => Newly created appnexus line item.
     */
    public static function addLineItem($advertiserId, $lineItem)
    {
        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'advertiser_id' => $advertiserId
        ));

        // package up the data, don't bother running query on invalid data
        $data = self::_createLineItemHash($lineItem);
        if ($data == null) {
            return null;
        }

        // query app nexus server
        $response = self::makeRequest($url, Api::POST, $data);

        return new AppNexusObject($response, AppNexusObject::MODE_READ_WRITE);
    }

    //-------------------------------------------------------------------------

    /**
     * Update an existing line item.
     *
     * @param  int  $id           => Id of line item.
     * @param  int  $advertiserId => Advertiser id of line item.
     * @param  hash $lineItem     => Only valid fields will be passed to api.
     * @return hash $lineItem     => Updated appnexus line item.
     */
    public static function updateLineItem($id, $advertiserId, $lineItem)
    {
        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'id'            => $id,
            'advertiser_id' => $advertiserId
        ));

        // package up the data, don't bother running query on invalid data
        $data = self::_createLineItemHash($lineItem);
        if ($data == null) {
            return null;
        }

        // query app nexus server
        $response = self::makeRequest($url, Api::PUT, $data);

        return new AppNexusObject($response, AppNexusObject::MODE_READ_WRITE);
    }

    //-------------------------------------------------------------------------

    /**
     * View all line items for an advertiser, results are paged.
     *
     * @param  int   $advertiserId
     * @return array $lineItems
     */
    public static function getAllLineItems($advertiserId,
        $start_element = 0, $num_elements = 100)
    {
        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'advertiser_id' => $advertiserId,
            'start_element' => $start_element,
            'num_elements'  => $num_elements
        ));

        // query app nexus server
        $response = self::makeRequest($url, Api::GET);

        // wrap response with app nexus object
        return new AppNexusArray($response, AppNexusObject::MODE_READ_WRITE);
    }

    //-------------------------------------------------------------------------

    /**
     * View line items speficied by ids, results are paged.
     *
     * @param  array(int) $ids
     * @return array      $lineItems
     */
    public static function getLineItems($ids)
    {
        // [moiz] need to fix this...

        // shortcut if only single id is specified
        if (count($ids) == 1) {
            return array(self::getLineItem($ids[0]));
        }

        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'id' => implode(',', $ids)
        ));

        // query app nexus server
        $response = self::makeRequest($url, Api::GET);

        // wrap response with app nexus object
        return new AppNexusArray($response, AppNexusObject::MODE_READ_WRITE);
    }

    //-------------------------------------------------------------------------

    /**
     * View a specific line item.
     *
     * @param  int  $id
     * @return hash $lineItem
     */
    public static function getLineItem($id)
    {
        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'id' => $id
        ));

        // query app nexus server
        $response = self::makeRequest($url, Api::GET);

        // wrap response with app nexus object
        return new AppNexusObject($response, AppNexusObject::MODE_READ_WRITE);
    }

    //-------------------------------------------------------------------------

    /**
     * Search for line items with ids or names containing certain characters,
     *  results are paged.
     *
     * @param  string $term
     * @return array  $lineItems
     */
    public static function searchLineItems($term,
        $start_element = 0, $num_elements = 100)
    {
        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'search'        => $term,
            'start_element' => $start_element,
            'num_elements'  => $num_elements
        ));

        // query app nexus server
        $response = self::makeRequest($url, Api::GET);

        // wrap response with app nexus object
        return new AppNexusArray($response, AppNexusObject::MODE_READ_WRITE);
    }

    //-------------------------------------------------------------------------

    /**
     * Delete a line item.
     *
     * @param  int  $id           => Id of line item.
     * @param  int  $advertiserId => Advertiser id of line item.
     * @return bool $status
     */
    public static function deleteLineItem($id, $advertiserId)
    {
        // construct url
        $url = self::getBaseUrl() . '?' . http_build_query(array(
            'id'            => $id,
            'advertiser_id' => $advertiserId
        ));

        // query app nexus server
        $response = self::makeRequest($url, Api::DELETE);

        return true;
    }

    //-------------------------------------------------------------------------
    // internal methods
    //-------------------------------------------------------------------------

    /**
     * Returns a line item hash containing only the fields which are allowed
     *  to be updated in the format accepted by AppNexus.
     *
     * @param  hash $lineItem
     * @return hash $lineItem
     */
    private static function _createLineItemHash($lineItem)
    {
        if (is_object($lineItem)) {
            $pruned = new stdClass();
            foreach (self::$fields as $key) {
                if (property_exists($lineItem, $key)) {
                    $pruned->$key = $lineItem->$key;
                }
            }

            // return null if no valid fields found
            return empty($pruned) ? null : (object) array('line-item' => $pruned);
        } else {
            $pruned = array();
            foreach (self::$fields as $key) {
                if (array_key_exists($key, $lineItem)) {
                    $pruned[$key] = $lineItem[$key];
                }
            }

            // return null if no valid fields found
            return empty($pruned) ? null : array('line-item' => $pruned);
        }
    }

}
