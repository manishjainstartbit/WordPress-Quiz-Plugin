<?php
/**
 * Plugin Name: Multisite Quiz
 * Description: A multisite-compatible quiz plugin with popup questions and backend quiz management.
 * Version: 1.0
 * Author: SBIT
 * Text Domain: multisite-quiz
 */

defined('ABSPATH') || exit;

// Define paths
define('MSQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MSQ_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include files
require_once MSQ_PLUGIN_DIR . 'includes/msq-cpt.php';
require_once MSQ_PLUGIN_DIR . 'includes/msq-shortcode.php';
require_once MSQ_PLUGIN_DIR . 'includes/msq-save-result.php';
require_once MSQ_PLUGIN_DIR . 'includes/admin/class-msq-results-table.php';


register_activation_hook(__FILE__, 'msq_create_results_table');

function msq_create_results_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'msq_results';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_label VARCHAR(50) NOT NULL,
        quiz_id BIGINT UNSIGNED NOT NULL,
        score INT NOT NULL,
        total INT NOT NULL,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_ip VARCHAR(100),
        result_data LONGTEXT,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($sql);
}


// Enqueue assets
function msq_enqueue_assets() {
    wp_enqueue_style('msq-style', MSQ_PLUGIN_URL . 'assets/css/msq-quiz.css');

    // ✅ Enqueue script with a handle
    wp_enqueue_script('msq-script', MSQ_PLUGIN_URL . 'assets/js/msq-quiz.js', array('jquery'), null, true);

    // ✅ Localize *AFTER* enqueue
    $labels = [
        'quiz_title'   => get_option('msq_quiz_title', 'Interactive Quiz Title'),
        'true'     => get_option('msq_true_label', 'TRUE'),
        'false'    => get_option('msq_false_label', 'FALSE'),
        'next'     => get_option('msq_next_label', 'Next'),
        'print'    => get_option('msq_print_label', 'Print Results'),
        'score'    => get_option('msq_score_label', 'Your Score'),
        'question' => get_option('msq_question_label', 'Question'),
        'title'    => get_option('msq_main_title', 'True or False?'),
        'answer'   => get_option('msq_answer_label', 'Answer:'),
        'correct'      => get_option('msq_correct_label', 'Correct Answer'),
        'final_heading'=> get_option('msq_final_heading', 'Thanks for taking the quiz!'),
        'final_subtext'=> get_option('msq_final_subtext', 'Please print your results and share them with your doctor.'),
        ];

    wp_localize_script('msq-script', 'msq_i18n', $labels);

    wp_localize_script('msq-script', 'msq_ajax', [
    'ajax_url' => admin_url('admin-ajax.php')
]);
}
add_action('wp_enqueue_scripts', 'msq_enqueue_assets');


add_action('admin_menu', 'msq_add_settings_page');
function msq_add_settings_page() {
    add_options_page(
        'Quiz Labels',
        'Quiz Labels',
        'manage_options',
        'msq-label-settings',
        'msq_render_settings_page'
    );
}

function msq_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Quiz Text Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('msq_label_settings_group');
            do_settings_sections('msq-label-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'msq_register_settings');
function msq_register_settings() {
    add_settings_section('msq_main_section', '', null, 'msq-label-settings');

    $fields = [
        'quiz_title'   => 'Interactive Quiz Title',
        'true_label'   => 'TRUE Button Text',
        'false_label'  => 'FALSE Button Text',
        'next_label'   => 'Next Button Text',
        'print_label'  => 'Print Button Text',
        'score_label'  => 'Your Score Label',
        'question_label' => 'Question Label',
        'main_title'   => 'Question Title (e.g. True or False?)',
        'answer_label' => 'Answer Label (before feedback)',
        'final_heading'   => 'Final Result Heading (e.g., Thanks for taking the quiz!)',
        'final_subtext'   => 'Final Subtext (e.g., Please print your results...)',
        'print'           => 'Print Button Text (e.g., PRINT)',
        'print_label'     => 'Print Prompt Label (optional)',
        'score_label'     => 'Score Label (e.g., Your score)',
        'correct_label'         => 'Correct Answer Label (for print)',
    ];

    foreach ($fields as $field => $label) {
        add_settings_field($field, $label, function () use ($field) {
            $value = get_option('msq_' . $field, '');
            echo '<input type="text" name="msq_' . $field . '" value="' . esc_attr($value) . '" class="regular-text" />';
        }, 'msq-label-settings', 'msq_main_section');

        register_setting('msq_label_settings_group', 'msq_' . $field);
    }
}

add_action('admin_menu', 'msq_add_results_submenu');

function msq_add_results_submenu() {
    add_submenu_page(
        'edit.php?post_type=msq_quiz',
        'Custom Quiz Results',
        'Quiz Results',
        'manage_options',
        'msq-custom-results',
        'msq_render_custom_results_page'
    );
}