<?php 


add_action('wp_ajax_nopriv_msq_save_quiz_result', 'msq_save_quiz_result');
add_action('wp_ajax_msq_save_quiz_result', 'msq_save_quiz_result');

function msq_save_quiz_result() {
    global $wpdb;

    $quiz_id = intval($_POST['quiz_id']);
    $score = intval($_POST['score']);
    $total = intval($_POST['total']);
    $results = $_POST['results'] ?? [];

    if (!$quiz_id || empty($results)) {
        wp_send_json_error(['message' => 'Invalid data']);
    }

    $table = $wpdb->prefix . 'msq_results';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $user_label = 'user' . ($count + 1);

    $wpdb->insert($table, [
        'user_label' => $user_label,
        'quiz_id' => $quiz_id,
        'score' => $score,
        'total' => $total,
        'submitted_at' => current_time('mysql'),
        'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'result_data' => maybe_serialize($results),
    ]);

    wp_send_json_success(['saved' => true]);
}



?>