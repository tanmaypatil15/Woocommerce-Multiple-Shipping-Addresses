<?php
/*
Plugin Name: Woocommerce Multiple Shipping Addresses Plugin
Description: Fetch, update and display data from wp_dsabafw_billingadress table using GET, POST & PATCH Methods.
Version: 1.0
Author: Tanmay Patil
*/

add_action('rest_api_init', function(){
    // Endpoint for retrieving shipping addresses
    register_rest_route(
        'wc/v3', 
        '/customers/multiple-shipping-address', 
        array(
            'methods' => 'GET', 
            'callback' => 'get_shipping_address_data',
            'args' => array(
                'user_id' => array(
                    'description' => 'User ID for whom to retrieve shipping addresses.',
                    'type' => 'string',
                    'required' => false,
                ),
                'type' => array(
                    'description' => 'type for whom to retrieve shipping addresses.',
                    'type' => 'string',
                    'required' => false,
                ),
            )
        )
    );

    // Endpoint for creating a new shipping address
    register_rest_route(
        'wc/v3', 
        '/customers/multiple-shipping-address', 
        array(
            'methods' => 'POST', 
            'callback' => 'create_shipping_address_data',
            'args' => array(
                'userid' => array(
                    'description' => 'User ID for whom to create shipping addresses.',
                    'type' => 'integer',
                    'required' => false,
                ),
                'type' => array(
                    'description' => 'type for whom to create shipping addresses.',
                    'type' => 'string',
                    'required' => false,
                ),
                'userdata' => array(
                    'description' => 'userdata for whom to create shipping addresses.',
                    'type' => 'text',
                    'required' => false,
                ),
            )
        )
    );

    register_rest_route(
        'wc/v3', 
        '/customers/multiple-shipping-address/(?P<id>\d+)', 
        array(
        'methods' => 'PATCH',
        'callback' => 'update_shipping_address_data',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }),
            'userdata' => array(
                'required' => true,
                'type' => 'text',
                'description' => 'User data for updating the shipping address.',
                'validate_callback' => function($param, $request, $key) {
                    // Validate user data as needed
                    return true; // Validation logic here
                }),
            )
        )
    );
});


function get_shipping_address_data($request) {
    global $wpdb;

    $user_id = $request->get_param('user_id');
    $type = $request->get_param('type');

    $table_name = $wpdb->prefix . 'dsabafw_billingadress';

    // Base SQL query
    $sql = "SELECT * FROM $table_name WHERE type = %s";

    
    // Add user_id condition if provided
    if ($user_id) {
        $sql .= " AND userid = %d";
    }

    // Prepare SQL query
    $sql = $wpdb->prepare($sql, $type, $user_id);
    //var_dump($sql);
    // Execute SQL query
    $results = $wpdb->get_results($sql);
    //var_dump($results);
    $shipping_addresses = array();

    // Process each result and add it to the response array
    foreach ($results as $result) {
        // Unserialize user data from the database
        $user_data = unserialize($result->userdata);

        // Separate serialized data into individual fields
        $shipping_address = array(
            'id' => $result->id,
            'user_id' => $result->userid,
            'type' => $result->type,
            'user_data' => array(
                'reference_field' => $user_data['reference_field'],
                'shipping_first_name' => $user_data['shipping_first_name'],
                'shipping_last_name' => $user_data['shipping_last_name'],
                'shipping_company' => $user_data['shipping_company'],
                'shipping_country' => $user_data['shipping_country'],
                'shipping_address_1' => $user_data['shipping_address_1'],
                'shipping_address_2' => $user_data['shipping_address_2'],
                'shipping_city' => $user_data['shipping_city'],
                'shipping_state' => $user_data['shipping_state'],
                'shipping_postcode' => $user_data['shipping_postcode'],
                // 'shipping_phone' => $user_data['shipping_phone'],
                // 'shipping_email' => $user_data['shipping_email']
            )
        );

        // Add the separated shipping address to the response array
        $shipping_addresses[] = $shipping_address;
    }

    // Return the shipping address data
    return rest_ensure_response($shipping_addresses);
    
}



// Callback function for POST method to create a new shipping address
function create_shipping_address_data($request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'dsabafw_billingadress';

    //var_dump($get_params);

    // Get request parameters
    $params = $request->get_params();

    //var_dump($params);
    // Validate required parameters
    if (empty($params['userid']) || empty($params['type']) || empty($params['userdata'])) {
        return new WP_Error('invalid_params', 'User ID, type, and user data are required.', array('status' => 400));
    }

    // Prepare and insert data into the database
    $wpdb->insert(
        $table_name,
        array(
            'userid' => $params['userid'],
            'type' => $params['type'],
            'userdata' => serialize($params['userdata'])
        )
    );

    // Check if the insertion was successful
    if ($wpdb->insert_id) {
        // Return success response
        return new WP_REST_Response(array('message' => 'Shipping address created successfully'), 200);
    } else {
        // Return error response
        return new WP_Error('insert_failed', 'Failed to create shipping address.', array('status' => 500));
    }

}


function update_shipping_address_data($request) {
    global $wpdb;

    $shipping_address_id = $request->get_param('id');

    $table_name = $wpdb->prefix . 'dsabafw_billingadress';
    
    // Retrieve the existing shipping address data
    $existing_shipping_address = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $shipping_address_id));

    if (!$existing_shipping_address) {
        return new WP_Error('shipping_address_not_found', 'Shipping address not found.', array('status' => 404));
    }

    // Get request parameters
    $params = $request->get_params();
    
    
    // Validate required parameters
    if (empty($params['userdata'])) {
        return new WP_Error('invalid_params', 'User data is required.', array('status' => 400));
    }
    

    //var_dump($params['userdata']);
    
    // Decode JSON data
    $user_data_json = $params['userdata']; // Get the JSON data from the request parameters
    $user_data_serialize = serialize($user_data_json);
    //var_dump($user_data_serialize);

    // Prepare and update data in the database
    $updated = $wpdb->update(
        $table_name,
        array(
            'userdata' => $user_data_serialize
        ),
        array('id' => $shipping_address_id)
    );

    // Check if the update was successful
    if ($updated) {
        // Return success response
        return new WP_REST_Response(array('message' => 'Shipping address updated successfully'), 200);
    } else {
        // Return error response
        return new WP_Error('update_failed', 'Failed to update shipping address.', array('status' => 500));
    }
}


