<?php

/*
 
Plugin Name: Obsidian Google Analytics 4
 
Description: Obsidian Google Analytics 4 from spreadsheet
 
Version: 1

*/

global $table_name;
global $wpdb;
include_once(ABSPATH . 'wp-includes/pluggable.php');
$table_name = $wpdb->prefix . 'obsidian_ga4';

if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
    global $table_name;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
  id mediumint(9) NOT NULL AUTO_INCREMENT,
  created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  tracking_id text NOT NULL,
  updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  PRIMARY KEY (id)
) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

function getInitialSheetData()
{
    require __DIR__ . '/vendor/autoload.php';
    //Initialize

    $client = new Google_Client();

    $client->setApplicationName('Google Sheets and PHP');

    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);

    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/key.json');

    $service = new Google_Service_Sheets($client);

    $spreadsheet_id = '1BWWH0dPY3q4xPqHE89nkP1obJbyK9mqixSEOOnGTPV4';

    $get_website_range = 'Sheet1!A3:A';
    $get_ga4_tracking_code = 'Sheet1!G3:G';

    //Request to get data from spreadsheet and server URI.

    $site_list = $service->spreadsheets_values->get($spreadsheet_id, $get_website_range);
    $ga4_list = $service->spreadsheets_values->get($spreadsheet_id, $get_ga4_tracking_code);

    $site_list_values = $site_list->getValues();
    $ga4_list_values = $ga4_list->getValues();

    $sites = call_user_func_array('array_merge', $site_list_values);
    $ga4 = call_user_func_array('array_merge', $ga4_list_values);

    $sites_ga_key_value = array();
    foreach ($sites as $key => $site) {
        $sites_ga_key_value[$key] = array($site, $ga4[$key]);
    }

    $server_uri = $_SERVER['HTTP_HOST'];

    foreach ($sites_ga_key_value as $site) {
        if ($site[0] == $server_uri) {
            global $wpdb;
            global $table_name;
            $wpdb->insert(
                $table_name,
                array(
                    'created_at' => current_time('mysql'),
                    'tracking_id' => $site[1],
                    'updated_at' => current_time('mysql'),
                )
            );
        }
    }
}

function updateSheetData()
{
    require __DIR__ . '/vendor/autoload.php';
    //Initialize

    $client = new Google_Client();

    $client->setApplicationName('Google Sheets and PHP');

    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);

    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/key.json');

    $service = new Google_Service_Sheets($client);

    $spreadsheet_id = '1BWWH0dPY3q4xPqHE89nkP1obJbyK9mqixSEOOnGTPV4';

    $get_website_range = 'Sheet1!A3:A';
    $get_ga4_tracking_code = 'Sheet1!G3:G';

    //Request to get data from spreadsheet and server URI.

    $site_list = $service->spreadsheets_values->get($spreadsheet_id, $get_website_range);
    $ga4_list = $service->spreadsheets_values->get($spreadsheet_id, $get_ga4_tracking_code);

    $site_list_values = $site_list->getValues();
    $ga4_list_values = $ga4_list->getValues();

    $sites = call_user_func_array('array_merge', $site_list_values);
    $ga4 = call_user_func_array('array_merge', $ga4_list_values);

    $sites_ga_key_value = array();
    foreach ($sites as $key => $site) {
        $sites_ga_key_value[$key] = array($site, $ga4[$key]);
    }

    $server_uri = $_SERVER['SERVER_NAME'];

    foreach ($sites_ga_key_value as $site) {

        if ($site[0] == $server_uri) {
            global $wpdb;
            global $table_name;
            $ga_key = $wpdb->get_var("SELECT tracking_id FROM {$wpdb->prefix}obsidian_ga4");
            $wpdb->query($wpdb->prepare("UPDATE $table_name 
                SET tracking_id='%s', updated_at='%s' 
                WHERE tracking_id = %s", $site[1], current_time('mysql'), $ga_key));
        }
    }
}


$obga_check_data = $wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}obsidian_ga4");


if ($obga_check_data == 0) {
    getInitialSheetData();
} else {
    //$update_time = $wpdb->get_var("SELECT updated_at FROM {$wpdb->prefix}obsidian_ga4");

    //$current_time = current_time('mysql');
    //if ($current_time - $update_time > 86400 && is_user_logged_in() == true) {
    //updateSheetData();
    //} else {
    function createTrackingCode()
    {
        global $wpdb;

        $ga_key = $wpdb->get_var("SELECT tracking_id FROM {$wpdb->prefix}obsidian_ga4");
        if (!empty($ga_key)) { ?>
            <script async src='https://www.googletagmanager.com/gtag/js?id=<?php echo $ga_key ?>'></script>
            <script>
                window.dataLayer = window.dataLayer || [];

                function gtag() {
                    dataLayer.push(arguments);
                }
                gtag('js', new Date());
                gtag('config', '<?php echo $ga_key ?>');
            </script>
<?php     }
    }
    add_action('wp_head', 'createTrackingCode');
}



function obgaRegisterMenu()
{
    add_menu_page(
        __('Sync GA4 Key with Sheets', 'textdomain'),
        'Sync GA4 Key with Sheets',
        'manage_options',
        'update_ga4',
        'obgaGetData',
        plugins_url('obsidian_ga4/icon.png'),
        6
    );
}
add_action('admin_menu', 'obgaRegisterMenu');

/**
 * Display a custom menu page
 */
function obgaGetData()
{
    updateSheetData();
    global $wpdb;
    $ga_key = $wpdb->get_var("SELECT tracking_id FROM {$wpdb->prefix}obsidian_ga4");
    echo '<h1> New GA key is: ' . $ga_key . '</h1>';
}
