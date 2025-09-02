<?php
defined('ABSPATH') || exit;

function msq_render_quiz_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => 0,
        'trigger' => '', // optional override
    ), $atts);

    $quiz_id = intval($atts['id']);
    if (!$quiz_id) return '';

    $questions = get_post_meta($quiz_id, '_msq_questions', true);
    if (empty($questions)) return '<p>No questions found.</p>';

    // Get trigger from meta if shortcode doesn't specify it
    $trigger = $atts['trigger'] ?: get_post_meta($quiz_id, '_msq_trigger_selector', true);

    ob_start();

    echo '<div class="msq-quiz-wrapper" data-quiz-id="' . esc_attr($quiz_id) . '">';

    // If no trigger, show default button
    if (empty($trigger)) {
        echo '<button class="msq-start-btn" data-quiz-id="' . esc_attr($quiz_id) . '">Take the Quiz</button>';
    } else {
        // Bind external button via JS
        echo '<script>
            window.msq_custom_triggers = window.msq_custom_triggers || [];
            window.msq_custom_triggers.push({ selector: "' . esc_js($trigger) . '", quizId: ' . $quiz_id . ' });
        </script>';
    }

    echo '<div class="msq-popup-overlay" style="display:none;" data-quiz-id="' . esc_attr($quiz_id) . '">';
    if (file_exists(MSQ_PLUGIN_DIR . 'templates/msq-quiz-popup.php')) {
        include MSQ_PLUGIN_DIR . 'templates/msq-quiz-popup.php';
    } else {
        echo '<p>Quiz layout missing.</p>';
    }
    echo '</div>';

    echo '<script>window.msq_quiz_data = window.msq_quiz_data || {}; window.msq_quiz_data[' . $quiz_id . '] = ' . json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';

    echo '</div>'; // .msq-quiz-wrapper

    return ob_get_clean();
}
add_shortcode('quiz_app', 'msq_render_quiz_shortcode');

