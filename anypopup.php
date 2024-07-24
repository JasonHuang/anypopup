<?php
/**
 * Plugin Name: AnyPopup
 * Plugin URI: http://example.com/anypopup
 * Description: A plugin to manage multiple popup windows on your WordPress site
 * Version: 2.0
 * Author: Your Name
 * Author URI: http://example.com
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class AnyPopup {
    private $popups;

    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_footer', array($this, 'display_popups'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_anypopup_save', array($this, 'ajax_save_popup'));
        add_action('wp_ajax_anypopup_get', array($this, 'ajax_get_popup'));
        add_action('wp_ajax_anypopup_delete', array($this, 'ajax_delete_popup'));
    }

    public function init() {
        $this->popups = get_option('anypopup_popups', array());
    }

    public function enqueue_scripts() {
        wp_enqueue_script('anypopup-script', plugin_dir_url(__FILE__) . 'assets/js/anypopup.js', array('jquery'), '2.0', true);
        wp_enqueue_style('anypopup-style', plugin_dir_url(__FILE__) . 'assets/css/anypopup.css');
        
        wp_localize_script('anypopup-script', 'anypopup_settings', array(
            'popups' => $this->get_active_popups()
        ));
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_anypopup-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);


        wp_enqueue_style('anypopup-admin-style', plugin_dir_url(__FILE__) . 'assets/css/anypopup-admin.css');
        wp_enqueue_script('anypopup-admin', plugin_dir_url(__FILE__) . 'assets/js/anypopup-admin.js', array('jquery'), '2.0', true);
        wp_localize_script('anypopup-admin', 'anypopup_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anypopup_ajax_nonce')
        ));
    }

    public function display_popups() {
        foreach ($this->get_active_popups() as $popup) {
            echo $this->render_popup($popup);
        }
    }

    private function render_popup($popup) {
        ob_start();
        ?>
        <div id="anypopup-<?php echo esc_attr($popup['id']); ?>" class="anypopup-overlay" style="display:none;">
            <div class="anypopup-container">
                <div class="anypopup-close">X</div>
                <div class="anypopup-content">
                    <?php echo wp_kses_post($popup['content']); ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_active_popups() {
        $active_popups = array();
        foreach ($this->popups as $popup) {
            if ($this->should_display_popup($popup)) {
                $active_popups[] = $popup;
            }
        }
        return $active_popups;
    }

    private function should_display_popup($popup) {
        if (!$popup['is_active']) {
            return false;
        }

        $current_page_id = get_the_ID();
        if (!empty($popup['display_pages']) && !in_array($current_page_id, $popup['display_pages'])) {
            return false;
        }

        return true;
    }

    public function add_admin_menu() {
        add_menu_page('AnyPopup Settings', 'AnyPopup', 'manage_options', 'anypopup-settings', array($this, 'settings_page'), 'dashicons-admin-generic');
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>AnyPopup Settings</h1>
            <div style="margin: 20px 0;">
                <button id="add-new-popup" class="button button-primary">Add New Popup</button>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Display Pages</th>
                        <th>Frequency</th>
                        <th>Delay Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $this->render_popup_rows(); ?>
                </tbody>
            </table>
        </div>
        <?php
        $this->render_popup_form();
    }

    private function render_popup_rows() {
        foreach ($this->popups as $popup) {
            ?>
            <tr>
                <td><?php echo esc_html($popup['name']); ?></td>
                <td><?php echo $popup['is_active'] ? 'Active' : 'Inactive'; ?></td>
                <td><?php echo implode(', ', array_map('get_the_title', $popup['display_pages'])); ?></td>
                <td><?php echo esc_html($popup['display_frequency']); ?></td>
                <td><?php echo esc_html($popup['delay_time']); ?> seconds</td>
                <td>
                    <button class="edit-popup button button-secondary" data-id="<?php echo esc_attr($popup['id']); ?>">Edit</button>
                    <button class="delete-popup button button-secondary" data-id="<?php echo esc_attr($popup['id']); ?>">Delete</button>
                </td>
            </tr>
            <?php
        }
    }

    private function render_popup_form() {
        ?>
        <div id="popup-form" style="display:none;">
            <h2>Popup Details</h2>
            <form id="anypopup-form">
                <input type="hidden" id="popup-id" name="id" value="">
                <table class="form-table">
                    <tr>
                        <th><label for="popup-name">Name</label></th>
                        <td><input type="text" id="popup-name" name="name" required></td>
                    </tr>
                    <tr>
                        <th><label for="popup-is-active">Active</label></th>
                        <td><input type="checkbox" id="popup-is-active" name="is_active"></td>
                    </tr>
                    <tr>
                        <th><label for="popup-content">Content</label></th>
                        <td><textarea id="popup-content" name="content" rows="5" cols="50"></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="popup-display-pages">Display Pages</label></th>
                        <td>
                            <select id="popup-display-pages" name="display_pages[]" multiple="multiple" style="width: 70%;">
                                <option value="select-all">Select All</option>
                                <?php
                                $pages = get_pages();
                                foreach ($pages as $page) {
                                    echo '<option value="'.$page->ID.'">'.$page->post_title.'</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="popup-frequency">Display Frequency</label></th>
                        <td>
                            <select id="popup-frequency" name="display_frequency">
                                <option value="every_time">Every time</option>
                                <option value="once_per_session">Once per session</option>
                                <option value="once_per_day">Once per day</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="popup-delay">Delay Time (seconds)</label></th>
                        <td><input type="number" id="popup-delay" name="delay_time" min="0"></td>
                    </tr>
                    <tr>
                        <th><label for="popup-closed-delay">Closed Display Delay (hours)</label></th>
                        <td><input type="number" id="popup-closed-delay" name="closed_display_delay" min="0"></td>
                    </tr>
                </table>
                <button type="submit" class="button button-primary">Save Popup</button>
                <button type="button" id="cancel-popup" class="button button-secondary">Cancel</button>
            </form>
        </div>
        <?php
    }

    public function ajax_save_popup() {
        check_ajax_referer('anypopup_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $popup = $this->sanitize_popup($_POST);
        
        if (empty($popup['id'])) {
            $popup['id'] = uniqid('popup_');
            $this->popups[] = $popup;
        } else {
            $index = array_search($popup['id'], array_column($this->popups, 'id'));
            if ($index !== false) {
                $this->popups[$index] = $popup;
            }
        }

        update_option('anypopup_popups', $this->popups);
        
        wp_send_json_success();
    }

    public function ajax_get_popup() {
        check_ajax_referer('anypopup_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $popup_id = $_GET['id'];
        $popup = null;

        foreach ($this->popups as $p) {
            if ($p['id'] === $popup_id) {
                $popup = $p;
                break;
            }
        }

        if ($popup) {
            wp_send_json_success($popup);
        } else {
            wp_send_json_error('Popup not found');
        }
    }

    public function ajax_delete_popup() {
        check_ajax_referer('anypopup_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $popup_id = $_POST['id'];
        $index = array_search($popup_id, array_column($this->popups, 'id'));

        if ($index !== false) {
            array_splice($this->popups, $index, 1);
            update_option('anypopup_popups', $this->popups);
            wp_send_json_success();
        } else {
            wp_send_json_error('Popup not found');
        }
    }

    private function sanitize_popup($input) {
        return array(
            'id' => sanitize_key($input['id']),
            'name' => sanitize_text_field($input['name']),
            'content' => wp_kses_post($input['content']),
            'is_active' => isset($input['is_active']) ? (bool)$input['is_active'] : false,
            'display_pages' => isset($input['display_pages']) ? array_map('intval', $input['display_pages']) : array(),
            'display_frequency' => sanitize_text_field($input['display_frequency']),
            'delay_time' => intval($input['delay_time']),
            'closed_display_delay' => intval($input['closed_display_delay'])
        );
    }
}

// Initialize the plugin
$anyPopup = new AnyPopup();