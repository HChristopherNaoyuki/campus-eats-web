<?php
/**
 * Fallback restaurant and menu data for Campus Eats
 *
 * Used when the Fake Restaurant API is unreachable (for example after
 * CURLE_SSL_CACERT). Data mirrors the public shape of the live API so
 * the home page and menu previews can render without fabricating
 * random values at request time.
 *
 * CORRECTIONS (Version 2.0 - Menu fallback):
 *
 * - Added campus_eats_load_fallback_menu($restaurantId) so menu
 *   previews work during API failure pause.
 * - Restaurant list still loads from data/fallback_restaurants.json
 *   when that file is readable; otherwise a small built-in list is used.
 * - Menu items are built-in only (no separate JSON required) and match
 *   the Fake Restaurant API field names (itemID, itemName, itemPrice,
 *   restaurantID, restaurantName, itemDescription, imageUrl).
 *
 * SOURCE: Error log – Menu preview unavailable, no fallback available.
 * SOURCE: Fake Restaurant API response shape.
 * SOURCE: Clean Code, Robert C. Martin.
 *
 * @version 2.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('campus_eats_builtin_restaurants'))
{
    /**
     * Built-in restaurant catalogue used when the JSON file is missing.
     *
     * @return array
     */
    function campus_eats_builtin_restaurants()
    {
        return array(
            array(
                'restaurantID'   => 1,
                'restaurantName' => 'Campus Corner Kitchen',
                'address'        => 'Student Union Building, Ground Floor',
                'type'           => 'South African',
                'parkingLot'     => true
            ),
            array(
                'restaurantID'   => 2,
                'restaurantName' => 'Braai Brothers',
                'address'        => 'Engineering Quad, Stall 4',
                'type'           => 'Grill',
                'parkingLot'     => false
            ),
            array(
                'restaurantID'   => 3,
                'restaurantName' => 'Coffee and Koeksisters',
                'address'        => 'Library Walkway, Stall 2',
                'type'           => 'Cafe',
                'parkingLot'     => false
            )
        );
    }
}

if (!function_exists('campus_eats_builtin_menus'))
{
    /**
     * Built-in menus keyed by restaurantID.
     *
     * @return array
     */
    function campus_eats_builtin_menus()
    {
        return array(
            1 => array(
                array(
                    'itemID'          => 101,
                    'itemName'        => 'Bunny Chow',
                    'itemDescription' => 'Half loaf filled with mild curry',
                    'itemPrice'       => 45.00,
                    'restaurantID'    => 1,
                    'restaurantName'  => 'Campus Corner Kitchen',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 102,
                    'itemName'        => 'Boerewors Roll',
                    'itemDescription' => 'Grilled sausage with relish',
                    'itemPrice'       => 35.00,
                    'restaurantID'    => 1,
                    'restaurantName'  => 'Campus Corner Kitchen',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 103,
                    'itemName'        => 'Pap and Stew',
                    'itemDescription' => 'Maize porridge with beef stew',
                    'itemPrice'       => 40.00,
                    'restaurantID'    => 1,
                    'restaurantName'  => 'Campus Corner Kitchen',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 104,
                    'itemName'        => 'Vetkoek with Mince',
                    'itemDescription' => 'Fried dough filled with spiced mince',
                    'itemPrice'       => 30.00,
                    'restaurantID'    => 1,
                    'restaurantName'  => 'Campus Corner Kitchen',
                    'imageUrl'        => ''
                )
            ),
            2 => array(
                array(
                    'itemID'          => 201,
                    'itemName'        => 'Mixed Grill Plate',
                    'itemDescription' => 'Chicken, boerewors and steak strips',
                    'itemPrice'       => 75.00,
                    'restaurantID'    => 2,
                    'restaurantName'  => 'Braai Brothers',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 202,
                    'itemName'        => 'Chicken Wrap',
                    'itemDescription' => 'Grilled chicken with salad',
                    'itemPrice'       => 48.00,
                    'restaurantID'    => 2,
                    'restaurantName'  => 'Braai Brothers',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 203,
                    'itemName'        => 'Chip Packet',
                    'itemDescription' => 'Portion of fried chips',
                    'itemPrice'       => 25.00,
                    'restaurantID'    => 2,
                    'restaurantName'  => 'Braai Brothers',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 204,
                    'itemName'        => 'Beef Burger',
                    'itemDescription' => 'Flame-grilled beef patty on a bun',
                    'itemPrice'       => 55.00,
                    'restaurantID'    => 2,
                    'restaurantName'  => 'Braai Brothers',
                    'imageUrl'        => ''
                )
            ),
            3 => array(
                array(
                    'itemID'          => 301,
                    'itemName'        => 'Filter Coffee',
                    'itemDescription' => 'Freshly brewed filter coffee',
                    'itemPrice'       => 22.00,
                    'restaurantID'    => 3,
                    'restaurantName'  => 'Coffee and Koeksisters',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 302,
                    'itemName'        => 'Koeksister',
                    'itemDescription' => 'Syrup-coated twisted doughnut',
                    'itemPrice'       => 18.00,
                    'restaurantID'    => 3,
                    'restaurantName'  => 'Coffee and Koeksisters',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 303,
                    'itemName'        => 'Cappuccino',
                    'itemDescription' => 'Espresso with steamed milk foam',
                    'itemPrice'       => 28.00,
                    'restaurantID'    => 3,
                    'restaurantName'  => 'Coffee and Koeksisters',
                    'imageUrl'        => ''
                ),
                array(
                    'itemID'          => 304,
                    'itemName'        => 'Scone with Jam',
                    'itemDescription' => 'Warm scone with jam and cream',
                    'itemPrice'       => 24.00,
                    'restaurantID'    => 3,
                    'restaurantName'  => 'Coffee and Koeksisters',
                    'imageUrl'        => ''
                )
            )
        );
    }
}

if (!function_exists('campus_eats_load_fallback_restaurants'))
{
    /**
     * Returns the fallback restaurant list.
     *
     * Prefers data/fallback_restaurants.json when readable and valid.
     * Falls back to the built-in list so the home page always has data
     * during an API outage.
     *
     * @return array
     */
    function campus_eats_load_fallback_restaurants()
    {
        static $cached = null;

        if ($cached !== null)
        {
            return $cached;
        }

        $path = BASE_PATH . '/data/fallback_restaurants.json';

        if (is_readable($path))
        {
            $content = file_get_contents($path);

            if ($content !== false)
            {
                $decoded = json_decode($content, true);

                if (is_array($decoded) && !empty($decoded))
                {
                    $cached = $decoded;
                    return $cached;
                }
            }
        }

        $cached = campus_eats_builtin_restaurants();
        return $cached;
    }
}

if (!function_exists('campus_eats_load_fallback_menu'))
{
    /**
     * Returns the fallback menu for a restaurant, or an empty array.
     *
     * @param int|string $restaurantId
     * @return array
     */
    function campus_eats_load_fallback_menu($restaurantId)
    {
        static $menus = null;

        if ($menus === null)
        {
            $menus = campus_eats_builtin_menus();
        }

        $id = (int)$restaurantId;

        if (isset($menus[$id]) && is_array($menus[$id]))
        {
            return $menus[$id];
        }

        return array();
    }
}