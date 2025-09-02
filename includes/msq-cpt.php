<?php
defined('ABSPATH') || exit;

// Register Custom Post Type
function msq_register_quiz_cpt() {
    $args = array(
        'label' => 'Quizzes',
        'public' => true,
        'show_in_menu' => true,
        'supports' => array('title'),
        'menu_icon' => 'dashicons-welcome-learn-more',
    );
    register_post_type('msq_quiz', $args);
}
add_action('init', 'msq_register_quiz_cpt');

// Add Meta Box
function msq_add_quiz_meta_box() {
    add_meta_box('msq_quiz_meta', 'Quiz Questions', 'msq_quiz_meta_box_callback', 'msq_quiz');
}
add_action('add_meta_boxes', 'msq_add_quiz_meta_box');

function msq_quiz_meta_box_callback($post) {
    $questions = get_post_meta($post->ID, '_msq_questions', true);
    wp_nonce_field('msq_save_quiz_meta', 'msq_quiz_nonce');
    ?>
    <div id="msq-quiz-questions">
        <?php if (!empty($questions) && is_array($questions)) :
            foreach ($questions as $index => $q) : ?>
    <div class="msq-question">
        <input type="text" name="msq_questions[<?php echo $index; ?>][text]" value="<?php echo esc_attr($q['text']); ?>" placeholder="Question" style="width: 60%;" />
        <select name="msq_questions[<?php echo $index; ?>][answer]">
            <option value="true" <?php selected($q['answer'], 'true'); ?>>True</option>
            <option value="false" <?php selected($q['answer'], 'false'); ?>>False</option>
        </select>
        <input type="text" name="msq_questions[<?php echo $index; ?>][description]" value="<?php echo esc_attr($q['description'] ?? ''); ?>" placeholder="Answer description" style="width: 90%; margin-top: 5px;" />
        <button type="button" class="msq-remove-question" style="margin-top:5px; background:#dc3232; color:#fff; border:none; padding:2px 8px; border-radius:3px;">Remove</button>
    </div>
<?php endforeach;
        endif; ?>
    </div>
    <button type="button" onclick="msqAddQuestion()">Add Question</button>
    <script>
(function($) {
    function getNextIndex() {
        let lastIndex = 0;
        $('#msq-quiz-questions .msq-question').each(function() {
            const match = $(this).find('input[name^="msq_questions["]').attr('name').match(/\[(\d+)\]/);
            if (match && match[1]) {
                const i = parseInt(match[1]);
                if (i >= lastIndex) lastIndex = i + 1;
            }
        });
        return lastIndex;
    }

    window.msqAddQuestion = function () {
        const index = getNextIndex();
        const $html = $(`
            <div class="msq-question">
                <input type="text" name="msq_questions[${index}][text]" placeholder="Question" style="width: 60%;" />
                <select name="msq_questions[${index}][answer]">
                    <option value="true">True</option>
                    <option value="false">False</option>
                </select>
                <input type="text" name="msq_questions[${index}][description]" placeholder="Answer description" style="width: 90%; margin-top: 5px;" />
                <button type="button" class="msq-remove-question" style="margin-top:5px;background:#dc3232;color:#fff;border:none;padding:2px 8px;border-radius:3px;">Remove</button>
            </div>
        `);
        $('#msq-quiz-questions').append($html);
    };

    // ✅ Use delegated event handler
    $(document).on('click', '.msq-remove-question', function () {
        $(this).closest('.msq-question').remove();
    });

})(jQuery);
</script>
    <?php
}

function msq_save_quiz_meta($post_id) {
    if (!isset($_POST['msq_quiz_nonce']) || !wp_verify_nonce($_POST['msq_quiz_nonce'], 'msq_save_quiz_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['msq_questions'])) {
        $questions = array_map(function($q) {
            return [
                'text' => sanitize_text_field($q['text']),
                'answer' => sanitize_text_field($q['answer']),
                'description' => sanitize_text_field($q['description']),
            ];
        }, $_POST['msq_questions']);
        update_post_meta($post_id, '_msq_questions', $questions);
    }
}
add_action('save_post', 'msq_save_quiz_meta');

// Add Shortcode Display Meta Box
function msq_add_shortcode_meta_box() {
    add_meta_box('msq_quiz_shortcode', 'Quiz Shortcode', 'msq_quiz_shortcode_callback', 'msq_quiz', 'side', 'high');
}
add_action('add_meta_boxes', 'msq_add_shortcode_meta_box');

function msq_quiz_shortcode_callback($post) {
    $shortcode = '[quiz_app id="' . esc_attr($post->ID) . '"]';
    echo '<input type="text" readonly value="' . esc_attr($shortcode) . '" style="width:100%; font-family: monospace;" onclick="this.select();" />';
}


add_action('add_meta_boxes', 'msq_add_trigger_meta_box');
function msq_add_trigger_meta_box() {
    add_meta_box(
        'msq_trigger_meta',
        'Quiz Trigger Button (Optional)',
        'msq_render_trigger_meta_box',
        'msq_quiz',
        'side'
    );
}

function msq_render_trigger_meta_box($post) {
    $value = get_post_meta($post->ID, '_msq_trigger_selector', true);
    echo '<label for="msq_trigger_selector">CSS Selector:</label>';
    echo '<input type="text" name="msq_trigger_selector" id="msq_trigger_selector" value="' . esc_attr($value) . '" style="width:100%;" />';
    echo '<p style="font-size: 12px;">Enter a class (e.g. <code>.start-quiz-btn</code>) or ID (e.g. <code>#quiz-btn</code>) of the button that should open this quiz.</p>';
}

add_action('save_post', 'msq_save_trigger_selector');
function msq_save_trigger_selector($post_id) {
    if (array_key_exists('msq_trigger_selector', $_POST)) {
        update_post_meta($post_id, '_msq_trigger_selector', sanitize_text_field($_POST['msq_trigger_selector']));
    }
}
