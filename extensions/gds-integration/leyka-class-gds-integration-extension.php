<?php if( !defined('WPINC') ) die;
/**
 * Leyka Extension: Google Data Studio extension
 * Version: 1.0
 * Author: Teplitsa of social technologies
 * Author URI: https://te-st.ru
 **/

class Leyka_Gds_Integration_Extension extends Leyka_Extension {

    protected static $_instance;

    protected static $_max_gds_allowed_data_lines = 100000;

    protected function _set_attributes() {

        $this->_id = 'gds_integration';
        $this->_title = __('Google Data Studio');

        // A human-readable short description (for backoffice extensions list page):
        $this->_description = __('Integration of your donations data with Google Data Studio web data visualization service (via MySQL data connector).', 'leyka');

        // A human-readable full description (for backoffice extensions list page):
        $this->_full_description = ''; // 150-300 chars

        global $wpdb;

        // A human-readable description (for backoffice extension settings page):
        $this->_settings_description = __('<p>This extension will help you convert the Leyka donations data to the export format. After this data conversion you may use them to create dashboards, charts and data tables in Google Data Studio.</p>', 'leyka')
        .'<h3>'.__('Set up the Cron job in your hosting Dashboard', 'leyka').'</h3>'
        .'<ul>'
            .'<li>'
                .__('Copy your procedure absolute address:', 'leyka')
                .'<br><code>'.str_replace(basename(__FILE__), 'leyka-gds-data-preparation.php', realpath(__FILE__)).'</code>'
            .'</li>'
            .'<li>'.sprintf(__('Set the Cron job to call the procedure nightly (<a href="%s" target="_blank" class="leyka-outer-link">user manual for setting up Cron jobs</a>)', 'leyka'), 'https://leyka.te-st.ru/docs/cronjob/').'</li>'
        .'</ul>'
        .'<h3>'.__('When the Cron job is done at least one time, there will be a new data table in your website database', 'leyka').'</h3>'
        .'<ul>'
            .'<li>'.__('The GDS data table name:', 'leyka').'<br><code>'.$wpdb->prefix.'leyka_gds_integration_donations_data</code></li>'
            .'<li>'.sprintf(__('Connect the table and GDS using a MySQL data connector (<a href="%s" target="_blank" class="leyka-outer-link">user manual for creating a GDS data connection</a>)', 'leyka'), 'https://leyka.te-st.ru/docs/gds/#gds-donations-data-connector').'</li>'
            .'<li>'.sprintf(__('Set up the data visualization in GDS (<a href="%s" target="_blank" class="leyka-outer-link">dashboards & charts setup examples</a>)', 'leyka'), 'https://leyka.te-st.ru/docs/gds/#gds-donations-visualizations').'</li>'
        .'</ul>';

//            '<p>Это приложение поможет вам выгрузить данные из Лейки в нужно формате.
//После выгрузки данные можно использовать для построения дашбордов, графиков, отчетных таблиц.</p>
//<ul>
//    <li><strong>Путь до скрипта процедуры:</strong> '.$path_to_cron_procedure.'</li>
//    <li><strong>Хост для подключения к БД:</strong> '.DB_HOST.'</li>
//    <li><strong>Юзер для подключения к БД:</strong> '.DB_USER.'</li>
//    <li><strong>Таблица для экспорта данных в GDS:</strong> '.$wpdb->prefix.'leyka_gds_integration_donations_data</li>
//    <li><strong>Дата последней подготовки данных для экспорта в GDS:</strong> '.$last_procedure_run_date.'</li>
//</ul>';

        // A human-readable description of how to enable the main feature (for backoffice extension settings page):
        $this->_connection_description = '';

        $this->_user_docs_link = 'https://leyka.te-st.ru/docs/gds/';
        $this->_has_wizard = false;
        $this->_has_color_options = false;

    }

    protected function _set_options_defaults() {

        $this->_options = apply_filters('leyka_'.$this->_id.'_extension_options', array(
            $this->_id.'_donations_date_period' => array(
                'type' => 'select',
                'title' => __('Donations dates period', 'leyka'),
                'description' => '', //__('Choose a donations dates period from which your donations will be prepared to export to Google Data Studio. WARNING: the donations data to export will be refreshed only at the closest call of your special data preparing procedure.', 'leyka'),
                'field_classes' => array('leyka-option-field-width-half'),
                'default' => '2_years',
                'list_entries' => array(
                    '2_months' => __('Last two months', 'leyka'),
                    '6_months' => __('Last six months', 'leyka'),
                    '1_year' => __('Last one year', 'leyka'),
                    '2_years' => __('Last two years', 'leyka'),
                    'all' => __('For all time', 'leyka'),
                ),
            ),
            $this->_id.'_data_info' => array(
                'type' => 'custom_gds_integration_data_info', // Special option type
//                'title' => __('Extensions available', 'leyka'),
            ),
        ));

    }

    /** Will be called only if the Extension is active. */
    protected function _initialize_active() {

        // Add the data preparing procedure to Leyka (to make it's browser calling possible):
        add_filter('leyka_procedure_address', function($procedure_absolute_address, $procedure_id, $params){

            if($procedure_id !== 'gds-data-preparation') {
                return $procedure_absolute_address;
            }

            return LEYKA_PLUGIN_DIR.'/extensions/gds-integration/leyka-gds-data-preparation.php';

        }, 10, 3);

    }

    public function activate() { // Create the special DB table for the GDS-prpared Donations data, if needed

        if( !$this->_gds_data_table_exists() ) {
            $this->_gds_data_table_create();
        }

    }

    public function deactivate() { // Remove the special DB table
        $this->_gds_data_table_delete();
    }

    public function get_max_gds_allowed_lines() {
        return self::$_max_gds_allowed_data_lines;
    }

    public function _gds_data_table_exists() {

        global $wpdb;
        return $wpdb->get_row("SHOW TABLES LIKE '{$wpdb->prefix}leyka_gds_integration_donations_data'");

    }

    public function _gds_data_table_create() {

        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}leyka_gds_integration_donations_data`;");
        $wpdb->query("CREATE TABLE `{$wpdb->prefix}leyka_gds_integration_donations_data` (
          `ID` bigint(20) UNSIGNED NOT NULL,
          `donation_date` datetime NOT NULL,
          `payment_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
          `gateway_title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
          `pm_title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
          `currency_label` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
          `amount` float NOT NULL,
          `amount_total` float NOT NULL,
          `status` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
          `campaign_title` text COLLATE utf8mb4_unicode_ci NOT NULL,
          `donor_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
          `donor_email` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
          `donor_has_account` BOOLEAN NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    }

    public function _gds_data_table_insert(Leyka_Donation $donation) {

        global $wpdb;

        return $wpdb->insert(
            "{$wpdb->prefix}leyka_gds_integration_donations_data",
            array(
                'ID' => $donation->ID,
                'donation_date' => date('Y-m-d H:i:s', $donation->date_timestamp),
                'payment_type' => $donation->type_label,
                'gateway_title' => $donation->gateway_label,
                'pm_title' => $donation->pm_label,
                'currency_label' => $donation->currency_label,
                'amount' => $donation->amount,
                'amount_total' => $donation->amount_total,
                'status' => $donation->status_label,
                'campaign_title' => $donation->campaign_title,
                'donor_name' => $donation->donor_name,
                'donor_email' => $donation->donor_email,
                'donor_has_account' => absint($donation->donor_id) ? 1 : 0,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d',)
        );

    }

    public function _gds_data_table_delete() {

        global $wpdb;
        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}leyka_gds_integration_donations_data`;");

    }

    public function _gds_data_table_clear() {

        global $wpdb;
        $wpdb->query("TRUNCATE `{$wpdb->prefix}leyka_gds_integration_donations_data`");

    }

    public function get_donations_to_convert() {

        switch(leyka_options()->opt($this->_id.'_donations_date_period')) {
            case '2_months':
                $date_query = date('Y-m-d 00:00:00', strtotime('-2 month')); break;
            case '6_months':
                $date_query = date('Y-m-d 00:00:00', strtotime('-6 month')); break;
            case '1_year':
                $date_query = date('Y-m-d 00:00:00', strtotime('-1 year')); break;
            case 'all':
                $date_query = ''; break;
            case '2_years':
            default:
                $date_query = date('Y-m-d 00:00:00', strtotime('-2 year')); break;
        }

        $params = apply_filters('leyka_gds_integration_donation_query_params', array(
            'post_type' => Leyka_Donation_Management::$post_type,
            'nopaging' => true,
            'post_status' => 'any',
            'date_query' => $date_query,
        ));

        $result = array();
        foreach(get_posts($params) as $donation) {
            $result[] = new Leyka_Donation($donation);
        }

        return $result;

    }

    public function get_donations_to_convert_count() {

        switch(leyka_options()->opt($this->_id.'_donations_date_period')) {
            case '2_months':
                $date_query = date('Y-m-d 00:00:00', strtotime('-2 month')); break;
            case '6_months':
                $date_query = date('Y-m-d 00:00:00', strtotime('-6 month')); break;
            case '1_year':
                $date_query = date('Y-m-d 00:00:00', strtotime('-1 year')); break;
            case 'all':
                $date_query = ''; break;
            case '2_years':
            default:
                $date_query = date('Y-m-d 00:00:00', strtotime('-2 year')); break;
        }

        $params = apply_filters('leyka_gds_integration_donation_query_params', array(
            'post_type' => Leyka_Donation_Management::$post_type,
            'nopaging' => true,
            'post_status' => 'any',
            'date_query' => $date_query,
        ));

        $query = new WP_Query($params);

        return $query->found_posts;

    }

}

function leyka_add_extension_gds_integration() { // Use named function to leave a possibility to remove/replace it on the hook
    leyka()->add_extension(Leyka_Gds_Integration_Extension::get_instance());
}

add_action('leyka_init_actions', 'leyka_add_extension_gds_integration');