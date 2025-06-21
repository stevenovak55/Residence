<?php
/**
 * Plugin Name: Bridge MLS Listings
 * Plugin URI: https://your-website.com/
 * Description: Display MLS listings from BridgeDataOutput API
 * Version: 1.0.1
 * Author: Your Name
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BRIDGE_MLS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BRIDGE_MLS_PLUGIN_PATH', plugin_dir_path(__FILE__));

// API Configuration
define('BRIDGE_API_URL', 'https://api.bridgedataoutput.com/api/v2/OData/shared_mlspin_41854c5');
define('BRIDGE_SERVER_TOKEN', '1c69fed3083478d187d4ce8deb8788ed');

/**
 * Enqueue plugin styles and scripts
 */
function bridge_mls_enqueue_scripts() {
    wp_enqueue_style('bridge-mls-style', BRIDGE_MLS_PLUGIN_URL . 'assets/style.css', array(), '1.0.1');
    
    // Enqueue Select2 for searchable multi-select
    wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');
    wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
    
    wp_enqueue_script('bridge-mls-script', BRIDGE_MLS_PLUGIN_URL . 'assets/script.js', array('jquery', 'select2'), '1.0.1', true);
    
    // Localize script for AJAX
    wp_localize_script('bridge-mls-script', 'bridge_mls_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bridge_mls_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'bridge_mls_enqueue_scripts');

/**
 * Fetch properties from Bridge API
 */
function bridge_mls_fetch_properties($params = array()) {
    $default_params = array(
        'top' => 10,
        'select' => 'ListingId,ListingKey,ListPrice,BedroomsTotal,BathroomsTotalInteger,LivingArea,PropertyType,PropertySubType,ArchitecturalStyle,ParkingFeatures,Basement,StreetNumber,StreetName,City,StateOrProvince,PostalCode,PublicRemarks,PhotosCount,Media',
        'filter' => 'StandardStatus eq \'Active\'',
        'orderby' => 'ListPrice desc'
    );
    
    $params = wp_parse_args($params, $default_params);
    
    // Build query string
    $query_params = array(
        'access_token' => BRIDGE_SERVER_TOKEN,
        '$top' => $params['top'],
        '$select' => $params['select'],
        '$filter' => $params['filter'],
        '$orderby' => $params['orderby']
    );
    
    if (!empty($params['skip'])) {
        $query_params['$skip'] = $params['skip'];
    }
    
    $url = BRIDGE_API_URL . '/Property?' . http_build_query($query_params);
    
    $response = wp_remote_get($url, array(
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    return $data;
}

/**
 * Get available cities - using hardcoded list for reliability
 */
function bridge_mls_get_cities() {
    $cities = array(
        'Acton', 'Acushnet', 'Adams', 'Agawam', 'Alford', 'Amesbury', 'Amherst', 'Andover', 'Arlington', 'Ashburnham',
        'Ashby', 'Ashfield', 'Ashland', 'Athol', 'Attleboro', 'Auburn', 'Avon', 'Ayer', 'Barnstable', 'Barre',
        'Becket', 'Bedford', 'Belchertown', 'Bellingham', 'Belmont', 'Berkley', 'Berlin', 'Bernardston', 'Beverly', 'Billerica',
        'Blackstone', 'Blandford', 'Bolton', 'Boston', 'Bourne', 'Boxborough', 'Boxford', 'Boylston', 'Braintree', 'Brewster',
        'Bridgewater', 'Brimfield', 'Brockton', 'Brookfield', 'Brookline', 'Buckland', 'Burlington', 'Cambridge', 'Canton', 'Carlisle',
        'Carver', 'Charlemont', 'Charlton', 'Chatham', 'Chelmsford', 'Chelsea', 'Cheshire', 'Chester', 'Chesterfield', 'Chicopee',
        'Chilmark', 'Clarksburg', 'Clinton', 'Cohasset', 'Colrain', 'Concord', 'Conway', 'Cummington', 'Dalton', 'Danvers',
        'Dartmouth', 'Dedham', 'Deerfield', 'Dennis', 'Dighton', 'Douglas', 'Dover', 'Dracut', 'Dudley', 'Dunstable',
        'Duxbury', 'East Bridgewater', 'East Brookfield', 'East Longmeadow', 'Eastham', 'Easthampton', 'Easton', 'Edgartown', 'Egremont', 'Erving',
        'Essex', 'Everett', 'Fairhaven', 'Fall River', 'Falmouth', 'Fitchburg', 'Florida', 'Foxborough', 'Framingham', 'Franklin',
        'Freetown', 'Gardner', 'Aquinnah', 'Georgetown', 'Gill', 'Gloucester', 'Goshen', 'Gosnold', 'Grafton', 'Granby',
        'Granville', 'Great Barrington', 'Greenfield', 'Groton', 'Groveland', 'Hadley', 'Halifax', 'Hamilton', 'Hampden', 'Hancock',
        'Hanover', 'Hanson', 'Hardwick', 'Harvard', 'Harwich', 'Hatfield', 'Haverhill', 'Hawley', 'Heath', 'Hingham',
        'Hinsdale', 'Holbrook', 'Holden', 'Holland', 'Holliston', 'Holyoke', 'Hopedale', 'Hopkinton', 'Hubbardston', 'Hudson',
        'Hull', 'Huntington', 'Ipswich', 'Kingston', 'Lakeville', 'Lancaster', 'Lanesborough', 'Lawrence', 'Lee', 'Leicester',
        'Lenox', 'Leominster', 'Leverett', 'Lexington', 'Leyden', 'Lincoln', 'Littleton', 'Longmeadow', 'Lowell', 'Ludlow',
        'Lunenburg', 'Lynn', 'Lynnfield', 'Malden', 'Manchester-by-the-Sea', 'Mansfield', 'Marblehead', 'Marion', 'Marlborough', 'Marshfield',
        'Marston Mills', 'Mashpee', 'Mattapoisett', 'Maynard', 'Medfield', 'Medford', 'Medway', 'Melrose', 'Mendon', 'Merrimac',
        'Methuen', 'Middleborough', 'Middlefield', 'Middleton', 'Milford', 'Millbury', 'Millis', 'Millville', 'Milton', 'Monroe',
        'Monson', 'Montague', 'Monterey', 'Montgomery', 'Mount Washington', 'Nahant', 'Nantucket', 'Natick', 'Needham', 'New Ashford',
        'New Bedford', 'New Braintree', 'New Marlborough', 'New Salem', 'Newbury', 'Newburyport', 'Newton', 'Norfolk', 'North Adams', 'North Andover',
        'North Attleborough', 'North Brookfield', 'North Reading', 'Northampton', 'Northborough', 'Northbridge', 'Northfield', 'Norton', 'Norwell', 'Norwood',
        'Oak Bluffs', 'Oakham', 'Orange', 'Orleans', 'Otis', 'Oxford', 'Palmer', 'Paxton', 'Peabody', 'Pelham',
        'Pembroke', 'Pepperell', 'Peru', 'Petersham', 'Phillipston', 'Pittsfield', 'Plainfield', 'Plainville', 'Plymouth', 'Plympton',
        'Princeton', 'Provincetown', 'Quincy', 'Randolph', 'Raynham', 'Reading', 'Rehoboth', 'Revere', 'Richmond', 'Rochester',
        'Rockland', 'Rockport', 'Rowe', 'Rowley', 'Royalston', 'Russell', 'Rutland', 'Salem', 'Salisbury', 'Sandisfield',
        'Sandwich', 'Saugus', 'Savoy', 'Scituate', 'Seekonk', 'Sharon', 'Sheffield', 'Shelburne', 'Sherborn', 'Shirley',
        'Shrewsbury', 'Shutesbury', 'Somerset', 'Somerville', 'South Hadley', 'Southampton', 'Southborough', 'Southbridge', 'Southwick', 'Spencer',
        'Springfield', 'Sterling', 'Stockbridge', 'Stoneham', 'Stoughton', 'Stow', 'Sturbridge', 'Sudbury', 'Sunderland', 'Sutton',
        'Swampscott', 'Swansea', 'Taunton', 'Templeton', 'Tewksbury', 'Tisbury', 'Tolland', 'Topsfield', 'Townsend', 'Truro',
        'Tyngsborough', 'Tyringham', 'Upton', 'Uxbridge', 'Wakefield', 'Wales', 'Walpole', 'Waltham', 'Ware', 'Wareham',
        'Warren', 'Warwick', 'Washington', 'Watertown', 'Wayland', 'Webster', 'Wellesley', 'Wellfleet', 'Wendell', 'Wenham',
        'West Boylston', 'West Bridgewater', 'West Brookfield', 'West Newbury', 'West Springfield', 'West Stockbridge', 'West Tisbury', 'Westborough', 'Westfield', 'Westford',
        'Weston', 'Westport', 'Westwood', 'Weymouth', 'Whately', 'Whitman', 'Wilbraham', 'Williamsburg', 'Williamstown', 'Wilmington',
        'Winchendon', 'Winchester', 'Windsor', 'Winthrop', 'Woburn', 'Worcester', 'Worthington', 'Wrentham', 'Yarmouth'
    );
    
    return $cities;
}

/**
 * Shortcode to display property listings
 */
function bridge_mls_listings_shortcode($atts) {
    // Get URL parameters to merge with shortcode attributes
    $url_params = array(
        'cities' => isset($_GET['cities']) ? array_map('sanitize_text_field', (array)$_GET['cities']) : array(),
        'min_price' => isset($_GET['min_price']) ? sanitize_text_field($_GET['min_price']) : '',
        'max_price' => isset($_GET['max_price']) ? sanitize_text_field($_GET['max_price']) : '',
        'property_type' => isset($_GET['property_type']) ? sanitize_text_field($_GET['property_type']) : '',
        'property_subtype' => isset($_GET['property_subtype']) ? sanitize_text_field($_GET['property_subtype']) : '',
        'bedrooms' => isset($_GET['bedrooms']) ? sanitize_text_field($_GET['bedrooms']) : '',
        'bathrooms' => isset($_GET['bathrooms']) ? sanitize_text_field($_GET['bathrooms']) : '',
        'mls_number' => isset($_GET['mls_number']) ? sanitize_text_field($_GET['mls_number']) : '',
        'style' => isset($_GET['style']) ? sanitize_text_field($_GET['style']) : '',
        'parking' => isset($_GET['parking']) ? sanitize_text_field($_GET['parking']) : '',
        'basement' => isset($_GET['basement']) ? sanitize_text_field($_GET['basement']) : ''
    );
    
    $atts = shortcode_atts(array(
        'limit' => 10,
        'city' => '',
        'cities' => array(),
        'min_price' => '',
        'max_price' => '',
        'property_type' => '',
        'property_subtype' => '',
        'bedrooms' => '',
        'bathrooms' => '',
        'mls_number' => '',
        'style' => '',
        'parking' => '',
        'basement' => '',
        'orderby' => 'ListPrice desc'
    ), $atts, 'bridge_mls_listings');
    
    // URL parameters take precedence over shortcode attributes
    if (!empty($url_params['cities'])) {
        $atts['cities'] = $url_params['cities'];
    }
    foreach (array('min_price', 'max_price', 'property_type', 'property_subtype', 'bedrooms', 'bathrooms', 'mls_number', 'style', 'parking', 'basement') as $key) {
        if (!empty($url_params[$key])) {
            $atts[$key] = $url_params[$key];
        }
    }
    
    // Build filter
    $filters = array();
    
    // Always filter by Active status
    $filters[] = "StandardStatus eq 'Active'";
    
    // MLS Number search (exact match)
    if (!empty($atts['mls_number'])) {
        $filters[] = "ListingId eq '" . esc_attr($atts['mls_number']) . "'";
    }
    
    // Handle multiple cities
    if (!empty($atts['cities'])) {
        $city_filters = array();
        foreach ($atts['cities'] as $city) {
            $city_filters[] = "City eq '" . esc_attr($city) . "'";
        }
        if (!empty($city_filters)) {
            $filters[] = "(" . implode(' or ', $city_filters) . ")";
        }
    } elseif (!empty($atts['city'])) {
        // Fallback to single city if provided
        $filters[] = "City eq '" . esc_attr($atts['city']) . "'";
    }
    
    if (!empty($atts['min_price'])) {
        $filters[] = "ListPrice ge " . intval($atts['min_price']);
    }
    
    if (!empty($atts['max_price'])) {
        $filters[] = "ListPrice le " . intval($atts['max_price']);
    }
    
    // Handle property type (direct value, no mapping needed)
    if (!empty($atts['property_type'])) {
        $filters[] = "PropertyType eq '" . esc_attr($atts['property_type']) . "'";
    }
    
    if (!empty($atts['property_subtype'])) {
        $filters[] = "PropertySubType eq '" . esc_attr($atts['property_subtype']) . "'";
    }
    
    if (!empty($atts['style'])) {
        // ArchitecturalStyle is the RESO standard field name
        $filters[] = "ArchitecturalStyle eq '" . esc_attr($atts['style']) . "'";
    }
    
    if (!empty($atts['parking'])) {
        // ParkingFeatures is typically a multi-value field, so we use contains
        $filters[] = "contains(ParkingFeatures, '" . esc_attr($atts['parking']) . "')";
    }
    
    if (!empty($atts['basement'])) {
        // Basement field - check if it contains the value
        $filters[] = "contains(Basement, '" . esc_attr($atts['basement']) . "')";
    }
    
    if (!empty($atts['bedrooms'])) {
        $filters[] = "BedroomsTotal ge " . intval($atts['bedrooms']);
    }
    
    if (!empty($atts['bathrooms'])) {
        $filters[] = "BathroomsTotalInteger ge " . intval($atts['bathrooms']);
    }
    
    $params = array(
        'top' => intval($atts['limit']),
        'filter' => implode(' and ', $filters),
        'orderby' => esc_attr($atts['orderby'])
    );
    
    $properties = bridge_mls_fetch_properties($params);
    
    if (!$properties || !isset($properties['value'])) {
        return '<p>No properties found or error retrieving data.</p>';
    }
    
    ob_start();
    ?>
    <div class="bridge-mls-listings">
        <div class="mls-results-count">
            <p>Found <strong><?php echo count($properties['value']); ?></strong> properties matching your criteria</p>
        </div>
        <div class="mls-grid">
            <?php foreach ($properties['value'] as $property): ?>
                <div class="mls-property-card">
                    <?php if (!empty($property['Media']) && is_array($property['Media'])): ?>
                        <?php $first_image = $property['Media'][0]; ?>
                        <?php if (!empty($first_image['MediaURL'])): ?>
                            <div class="property-image">
                                <img data-src="<?php echo esc_url($first_image['MediaURL']); ?>" 
                                     src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 400 300'%3E%3Crect width='400' height='300' fill='%23f0f0f0'/%3E%3C/svg%3E"
                                     alt="<?php echo esc_attr($property['StreetNumber'] . ' ' . $property['StreetName']); ?>"
                                     loading="lazy">
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="property-image no-image">
                            <div class="placeholder">
                                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <p>No Image Available</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="property-details">
                        <h3 class="property-price">$<?php echo number_format($property['ListPrice']); ?></h3>
                        <p class="property-address">
                            <?php echo esc_html($property['StreetNumber'] . ' ' . $property['StreetName']); ?><br>
                            <?php echo esc_html($property['City'] . ', ' . $property['StateOrProvince'] . ' ' . $property['PostalCode']); ?>
                        </p>
                        <div class="property-features">
                            <?php if (!empty($property['BedroomsTotal'])): ?>
                                <span class="feature">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                    <strong><?php echo intval($property['BedroomsTotal']); ?></strong> Beds
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($property['BathroomsTotalInteger'])): ?>
                                <span class="feature">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M5 12V7a5 5 0 0 1 10 0v5"></path>
                                        <path d="M3 12h18v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6z"></path>
                                    </svg>
                                    <strong><?php echo intval($property['BathroomsTotalInteger']); ?></strong> Baths
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($property['LivingArea'])): ?>
                                <span class="feature">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="9" y1="3" x2="9" y2="21"></line>
                                        <line x1="3" y1="9" x2="21" y2="9"></line>
                                    </svg>
                                    <strong><?php echo number_format($property['LivingArea']); ?></strong> sq ft
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($property['PropertyType'])): ?>
                            <p class="property-type"><?php echo esc_html($property['PropertyType']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($property['PropertySubType'])): ?>
                            <p class="property-subtype"><?php echo esc_html($property['PropertySubType']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($property['PublicRemarks'])): ?>
                            <p class="property-remarks"><?php echo wp_trim_words(esc_html($property['PublicRemarks']), 25); ?></p>
                        <?php endif; ?>
                        <a href="#" class="btn-view-details" data-listing-key="<?php echo esc_attr($property['ListingKey']); ?>">
                            View Details →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($properties['value'])): ?>
            <div class="mls-error">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <p>No properties found matching your search criteria.</p>
                <p>Try adjusting your filters or search in different areas.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode('bridge_mls_listings', 'bridge_mls_listings_shortcode');

/**
 * Search form shortcode
 */
function bridge_mls_search_form_shortcode($atts) {
    // Get current search values from URL
    $current_cities = isset($_GET['cities']) ? array_map('sanitize_text_field', (array)$_GET['cities']) : array();
    $current_min_price = isset($_GET['min_price']) ? sanitize_text_field($_GET['min_price']) : '';
    $current_max_price = isset($_GET['max_price']) ? sanitize_text_field($_GET['max_price']) : '';
    $current_bedrooms = isset($_GET['bedrooms']) ? sanitize_text_field($_GET['bedrooms']) : '';
    $current_bathrooms = isset($_GET['bathrooms']) ? sanitize_text_field($_GET['bathrooms']) : '';
    $current_property_type = isset($_GET['property_type']) ? sanitize_text_field($_GET['property_type']) : '';
    $current_property_subtype = isset($_GET['property_subtype']) ? sanitize_text_field($_GET['property_subtype']) : '';
    $current_mls_number = isset($_GET['mls_number']) ? sanitize_text_field($_GET['mls_number']) : '';
    $current_style = isset($_GET['style']) ? sanitize_text_field($_GET['style']) : '';
    $current_parking = isset($_GET['parking']) ? sanitize_text_field($_GET['parking']) : '';
    $current_basement = isset($_GET['basement']) ? sanitize_text_field($_GET['basement']) : '';
    
    // Get available options
    $available_cities = bridge_mls_get_cities();
    $property_styles = bridge_mls_get_property_styles();
    $parking_options = bridge_mls_get_parking_options();
    $basement_options = bridge_mls_get_basement_options();
    
    // Get the current page URL properly
    global $wp;
    $current_url = home_url($wp->request);
    
    ob_start();
    ?>
    <form class="bridge-mls-search-form" method="get" action="<?php echo esc_url($current_url); ?>">
        <?php
        // If using pretty permalinks, we might need to preserve the page
        if (!get_option('permalink_structure')) {
            // Preserve page_id for default permalinks
            if (isset($_GET['page_id'])) {
                echo '<input type="hidden" name="page_id" value="' . esc_attr($_GET['page_id']) . '">';
            }
            if (isset($_GET['p'])) {
                echo '<input type="hidden" name="p" value="' . esc_attr($_GET['p']) . '">';
            }
        }
        ?>
        
        <!-- MLS Number Search Row -->
        <div class="search-row">
            <div class="search-field search-field-wide">
                <label for="mls-number">Search by MLS Number</label>
                <input type="text" id="mls-number" name="mls_number" placeholder="Enter MLS number..." value="<?php echo esc_attr($current_mls_number); ?>">
                <small class="field-help">Enter an exact MLS number to find a specific property</small>
            </div>
        </div>
        
        <!-- Cities Multi-Select -->
        <div class="search-row">
            <div class="search-field search-field-wide">
                <label for="mls-cities">Cities (Type to search, select multiple)</label>
                <select id="mls-cities" name="cities[]" class="mls-city-select" multiple="multiple" style="width: 100%;">
                    <?php foreach ($available_cities as $city): ?>
                        <option value="<?php echo esc_attr($city); ?>" <?php echo in_array($city, $current_cities) ? 'selected' : ''; ?>>
                            <?php echo esc_html($city); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Price and Property Type Row -->
        <div class="search-row">
            <div class="search-field">
                <label for="mls-min-price">Min Price</label>
                <input type="number" id="mls-min-price" name="min_price" placeholder="Min price" value="<?php echo esc_attr($current_min_price); ?>">
            </div>
            <div class="search-field">
                <label for="mls-max-price">Max Price</label>
                <input type="number" id="mls-max-price" name="max_price" placeholder="Max price" value="<?php echo esc_attr($current_max_price); ?>">
            </div>
            <div class="search-field">
                <label for="mls-property-type">Property Type</label>
                <select id="mls-property-type" name="property_type">
                    <option value="">All Types</option>
                    <option value="Residential" <?php selected($current_property_type, 'Residential'); ?>>Residential</option>
                    <option value="Residential Income" <?php selected($current_property_type, 'Residential Income'); ?>>Residential Income</option>
                    <option value="Residential Lease" <?php selected($current_property_type, 'Residential Lease'); ?>>Residential Lease</option>
                    <option value="Land" <?php selected($current_property_type, 'Land'); ?>>Land</option>
                    <option value="Commercial Sale" <?php selected($current_property_type, 'Commercial Sale'); ?>>Commercial Sale</option>
                    <option value="Commercial Lease" <?php selected($current_property_type, 'Commercial Lease'); ?>>Commercial Lease</option>
                    <option value="Business Opportunity" <?php selected($current_property_type, 'Business Opportunity'); ?>>Business Opportunity</option>
                </select>
            </div>
        </div>
        
        <!-- Property SubType and Style Row -->
        <div class="search-row">
            <div class="search-field">
                <label for="mls-property-subtype">Property SubType</label>
                <select id="mls-property-subtype" name="property_subtype">
                    <option value="">All SubTypes</option>
                    <?php if ($current_property_type): ?>
                        <?php
                        $subtypes_map = bridge_mls_get_property_subtypes_mapping();
                        $subtypes = isset($subtypes_map[$current_property_type]) ? $subtypes_map[$current_property_type] : array();
                        foreach ($subtypes as $subtype):
                        ?>
                            <option value="<?php echo esc_attr($subtype); ?>" <?php selected($current_property_subtype, $subtype); ?>>
                                <?php echo esc_html($subtype); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <small class="field-help">Select a property type first</small>
            </div>
            <div class="search-field">
                <label for="mls-style">Style</label>
                <select id="mls-style" name="style">
                    <option value="">Any Style</option>
                    <?php foreach ($property_styles as $style): ?>
                        <option value="<?php echo esc_attr($style); ?>" <?php selected($current_style, $style); ?>>
                            <?php echo esc_html($style); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-field">
                <label for="mls-parking">Parking</label>
                <select id="mls-parking" name="parking">
                    <option value="">Any Parking</option>
                    <?php foreach ($parking_options as $parking): ?>
                        <option value="<?php echo esc_attr($parking); ?>" <?php selected($current_parking, $parking); ?>>
                            <?php echo esc_html($parking); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Bedrooms, Bathrooms, and Basement Row -->
        <div class="search-row">
            <div class="search-field">
                <label for="mls-bedrooms">Min Bedrooms</label>
                <select id="mls-bedrooms" name="bedrooms">
                    <option value="">Any</option>
                    <option value="1" <?php selected($current_bedrooms, '1'); ?>>1+</option>
                    <option value="2" <?php selected($current_bedrooms, '2'); ?>>2+</option>
                    <option value="3" <?php selected($current_bedrooms, '3'); ?>>3+</option>
                    <option value="4" <?php selected($current_bedrooms, '4'); ?>>4+</option>
                    <option value="5" <?php selected($current_bedrooms, '5'); ?>>5+</option>
                </select>
            </div>
            <div class="search-field">
                <label for="mls-bathrooms">Min Bathrooms</label>
                <select id="mls-bathrooms" name="bathrooms">
                    <option value="">Any</option>
                    <option value="1" <?php selected($current_bathrooms, '1'); ?>>1+</option>
                    <option value="2" <?php selected($current_bathrooms, '2'); ?>>2+</option>
                    <option value="3" <?php selected($current_bathrooms, '3'); ?>>3+</option>
                    <option value="4" <?php selected($current_bathrooms, '4'); ?>>4+</option>
                </select>
            </div>
            <div class="search-field">
                <label for="mls-basement">Basement</label>
                <select id="mls-basement" name="basement">
                    <option value="">Any</option>
                    <?php foreach ($basement_options as $basement): ?>
                        <option value="<?php echo esc_attr($basement); ?>" <?php selected($current_basement, $basement); ?>>
                            <?php echo esc_html($basement); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- Search Buttons -->
        <div class="search-row">
            <div class="search-field search-field-buttons">
                <label>&nbsp;</label>
                <div class="button-group">
                    <button type="submit" class="btn-search">Search Properties</button>
                    <?php if (!empty($_GET)): ?>
                        <a href="<?php echo esc_url(strtok($_SERVER['REQUEST_URI'], '?')); ?>" class="btn-clear">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('bridge_mls_search', 'bridge_mls_search_form_shortcode');

/**
 * AJAX handler for property details
 */
function bridge_mls_get_property_details() {
    check_ajax_referer('bridge_mls_nonce', 'nonce');
    
    $listing_key = sanitize_text_field($_POST['listing_key']);
    
    if (empty($listing_key)) {
        wp_die('Invalid listing key');
    }
    
    // Build URL for single property request
    $url = BRIDGE_API_URL . '/Property?access_token=' . BRIDGE_SERVER_TOKEN . '&$filter=ListingKey eq \'' . $listing_key . '\'';
    
    $response = wp_remote_get($url, array(
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        wp_die('Error fetching property details');
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || !isset($data['value']) || empty($data['value'])) {
        wp_die('Property not found');
    }
    
    $property_data = $data['value'][0];
    
    // Return detailed property info
    ob_start();
    ?>
    <div class="property-detail-modal">
        <h2><?php echo esc_html($property_data['StreetNumber'] . ' ' . $property_data['StreetName']); ?></h2>
        <p><?php echo esc_html($property_data['City'] . ', ' . $property_data['StateOrProvince'] . ' ' . $property_data['PostalCode']); ?></p>
        
        <?php if (!empty($property_data['Media']) && is_array($property_data['Media'])): ?>
            <div class="property-images">
                <?php foreach ($property_data['Media'] as $media): ?>
                    <?php if (!empty($media['MediaURL'])): ?>
                        <img src="<?php echo esc_url($media['MediaURL']); ?>" alt="Property Image">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="property-info">
            <h3>Property Details</h3>
            <p><strong>MLS #:</strong> <?php echo esc_html($property_data['ListingId']); ?></p>
            <p><strong>Price:</strong> $<?php echo number_format($property_data['ListPrice']); ?></p>
            <?php if (!empty($property_data['PropertyType'])): ?>
                <p><strong>Type:</strong> <?php echo esc_html($property_data['PropertyType']); ?></p>
            <?php endif; ?>
            <?php if (!empty($property_data['PropertySubType'])): ?>
                <p><strong>Sub-Type:</strong> <?php echo esc_html($property_data['PropertySubType']); ?></p>
            <?php endif; ?>
            <?php if (!empty($property_data['BedroomsTotal'])): ?>
                <p><strong>Bedrooms:</strong> <?php echo intval($property_data['BedroomsTotal']); ?></p>
            <?php endif; ?>
            <?php if (!empty($property_data['BathroomsTotalInteger'])): ?>
                <p><strong>Bathrooms:</strong> <?php echo intval($property_data['BathroomsTotalInteger']); ?></p>
            <?php endif; ?>
            <?php if (!empty($property_data['LivingArea'])): ?>
                <p><strong>Living Area:</strong> <?php echo number_format($property_data['LivingArea']); ?> sq ft</p>
            <?php endif; ?>
            <?php if (!empty($property_data['YearBuilt'])): ?>
                <p><strong>Year Built:</strong> <?php echo intval($property_data['YearBuilt']); ?></p>
            <?php endif; ?>
            <?php if (!empty($property_data['StandardStatus'])): ?>
                <p><strong>Status:</strong> <?php echo esc_html($property_data['StandardStatus']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($property_data['PublicRemarks'])): ?>
            <div class="property-description">
                <h3>Description</h3>
                <p><?php echo nl2br(esc_html($property_data['PublicRemarks'])); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_bridge_mls_property_details', 'bridge_mls_get_property_details');
add_action('wp_ajax_nopriv_bridge_mls_property_details', 'bridge_mls_get_property_details');

/**
 * AJAX handler to get available property types
 */
function bridge_mls_get_property_types() {
    check_ajax_referer('bridge_mls_nonce', 'nonce');
    
    $cache_key = 'bridge_mls_property_types';
    $cached_types = get_transient($cache_key);
    
    if ($cached_types !== false) {
        wp_send_json_success($cached_types);
    }
    
    // Fetch a sample of properties to get property types
    $url = BRIDGE_API_URL . '/Property?' . http_build_query(array(
        'access_token' => BRIDGE_SERVER_TOKEN,
        '$select' => 'PropertyType',
        '$filter' => 'StandardStatus eq \'Active\' and PropertyType ne null',
        '$top' => 500
    ));
    
    $response = wp_remote_get($url, array(
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error('Error fetching property types');
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || !isset($data['value'])) {
        wp_send_json_error('No data found');
    }
    
    // Extract unique property types
    $types = array();
    foreach ($data['value'] as $property) {
        if (!empty($property['PropertyType']) && !in_array($property['PropertyType'], $types)) {
            $types[] = $property['PropertyType'];
        }
    }
    
    // Remove duplicates and sort
    $types = array_unique($types);
    sort($types);
    
    // Cache for 24 hours
    set_transient($cache_key, $types, DAY_IN_SECONDS);
    
    wp_send_json_success($types);
}
add_action('wp_ajax_bridge_mls_property_types', 'bridge_mls_get_property_types');
add_action('wp_ajax_nopriv_bridge_mls_property_types', 'bridge_mls_get_property_types');

/**
 * Get property subtypes based on property type
 * These mappings follow RESO standards used by Bridge API
 */
function bridge_mls_get_property_subtypes_mapping() {
    // Based on RESO standard property types and subtypes
    $subtypes_map = array(
        'Residential' => array(
            'Single Family Residence',
            'Condominium',
            'Townhouse',
            'Manufactured Home',
            'Mobile Home',
            'Farm',
            'Ranch',
            'High Rise',
            'Garden',
            'Patio Home',
            'Villa',
            'Loft',
            'Stock Cooperative',
            'Detached',
            'Attached'
        ),
        'Residential Income' => array(
            'Duplex',
            'Triplex',
            'Quadruplex',
            'Five Or More',
            'Apartment/Complex',
            'Mobile Home Park',
            'Assisted Living',
            'Boarding House'
        ),
        'Residential Lease' => array(
            'Single Family Residence',
            'Condominium',
            'Townhouse',
            'Apartment',
            'Loft',
            'Mobile Home',
            'Furnished',
            'Unfurnished',
            'Room/Shared'
        ),
        'Land' => array(
            'Agricultural',
            'Commercial',
            'Industrial',
            'Mixed Use',
            'Recreational',
            'Residential',
            'Unimproved',
            'Other'
        ),
        'Commercial Sale' => array(
            'Office Building',
            'Retail',
            'Industrial',
            'Mixed Use',
            'Warehouse',
            'Manufacturing',
            'Cold Storage',
            'Flex Space',
            'Research & Development',
            'Medical',
            'Restaurant',
            'Auto Related',
            'Hotel/Motel',
            'Mini Storage',
            'Mobile Home Park',
            'Parking Garage/Lot',
            'Unimproved Commercial',
            'Other'
        ),
        'Commercial Lease' => array(
            'Office',
            'Retail',
            'Industrial',
            'Mixed Use',
            'Warehouse',
            'Manufacturing',
            'Flex Space',
            'Medical',
            'Restaurant',
            'Auto Related',
            'Other'
        ),
        'Business Opportunity' => array(
            'Aeronautic',
            'Agriculture',
            'Arts and Entertainment',
            'Assembly',
            'Automotive',
            'Bar/Tavern/Lounge',
            'Beauty/Barber',
            'Bed & Breakfast',
            'Child Care',
            'Construction/Trade',
            'Distributorship',
            'Dry Cleaner',
            'Education/School',
            'Fashion/Specialty',
            'Financial',
            'Fitness',
            'Food & Beverage',
            'Franchise',
            'Furniture',
            'Gas Station',
            'Grocery',
            'Health Services',
            'Home Improvement',
            'Hotel/Motel',
            'Laundromat',
            'Liquor Store',
            'Manufacturing',
            'Marine',
            'Medical',
            'Mixed',
            'Other',
            'Personal Service',
            'Pet Related',
            'Professional Service',
            'Professional/Office',
            'Recreation',
            'Religious',
            'Rental',
            'Restaurant',
            'Retail',
            'Sports',
            'Technology',
            'Transportation',
            'Travel',
            'Wholesale'
        )
    );
    
    return $subtypes_map;
}

/**
 * Get available property styles (ArchitecturalStyle in RESO)
 */
function bridge_mls_get_property_styles() {
    return array(
        'A-Frame',
        'Bungalow',
        'Cape Cod',
        'Colonial',
        'Contemporary',
        'Cottage',
        'Craftsman',
        'Dutch Colonial',
        'Farmhouse',
        'French Provincial',
        'Georgian',
        'High Rise',
        'Historical',
        'Log Cabin',
        'Mediterranean',
        'Mid-Century Modern',
        'Modern',
        'Prairie',
        'Queen Anne',
        'Ranch',
        'Spanish',
        'Split Level',
        'Traditional',
        'Tudor',
        'Victorian',
        'Other'
    );
}

/**
 * Get parking options (ParkingFeatures in RESO)
 */
function bridge_mls_get_parking_options() {
    return array(
        'Attached Garage',
        'Detached Garage',
        'Carport',
        'Driveway',
        'Garage',
        'Off Street',
        'On Street',
        'Covered',
        'Parking Lot',
        'Parking Space(s)',
        'Private',
        'RV Parking',
        'Tandem',
        'Valet',
        'None'
    );
}

/**
 * Get basement options (Basement in RESO)
 */
function bridge_mls_get_basement_options() {
    return array(
        'Yes',
        'No',
        'Finished',
        'Full',
        'Partially Finished',
        'Unfinished',
        'Walk-Out',
        'Daylight',
        'Crawl Space',
        'Slab',
        'Unknown'
    );
}

/**
 * AJAX handler to get property subtypes
 */
function bridge_mls_get_property_subtypes() {
    check_ajax_referer('bridge_mls_nonce', 'nonce');
    
    $property_type = isset($_POST['property_type']) ? sanitize_text_field($_POST['property_type']) : '';
    
    $subtypes_map = bridge_mls_get_property_subtypes_mapping();
    
    $subtypes = isset($subtypes_map[$property_type]) ? $subtypes_map[$property_type] : array();
    
    wp_send_json_success($subtypes);
}
add_action('wp_ajax_bridge_mls_get_subtypes', 'bridge_mls_get_property_subtypes');
add_action('wp_ajax_nopriv_bridge_mls_get_subtypes', 'bridge_mls_get_property_subtypes');

/**
 * Create plugin tables on activation
 */
function bridge_mls_activate() {
    // Create assets directory
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/bridge-mls';
    
    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }
}
register_activation_hook(__FILE__, 'bridge_mls_activate');