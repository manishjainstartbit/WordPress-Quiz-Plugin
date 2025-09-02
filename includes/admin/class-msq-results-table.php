<?php

function msq_render_custom_results_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'msq_results';

    // ✅ Handle bulk delete
    if (isset($_POST['bulk_delete']) && !empty($_POST['result_ids']) && check_admin_referer('msq_bulk_delete_action')) {
        $ids = array_map('intval', $_POST['result_ids']);
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));
        echo '<div class="updated"><p>Selected results deleted.</p></div>';
    }

    // ✅ Handle single delete
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'msq_delete_result')) {
        $wpdb->delete($table, ['id' => intval($_GET['id'])]);
        echo '<div class="updated"><p>Result deleted.</p></div>';
    }

    // ✅ Review single result
    if (isset($_GET['action']) && $_GET['action'] === 'view' && !empty($_GET['id'])) {
        $id = intval($_GET['id']);
        $row = $wpdb->get_row("SELECT * FROM $table WHERE id = $id", ARRAY_A);

        echo '<div class="wrap"><h1>Review Result</h1>';
        if ($row) {
            $results = maybe_unserialize($row['result_data']);
            echo '<p><strong>User:</strong> ' . esc_html($row['user_label']) . '</p>';
            echo '<p><strong>Score:</strong> ' . esc_html($row['score'] . '/' . $row['total']) . '</p>';
            echo '<ul style="list-style:disc;padding-left:20px;">';
            foreach ($results as $i => $entry) {
                echo '<li><strong>Q' . ($i + 1) . ':</strong> ' . esc_html($entry['question']) . '<br>';
                echo 'Your Answer: ' . esc_html($entry['selected']) . ' | Correct: ' . esc_html($entry['correct']) . '<br>';
                if (!empty($entry['description'])) {
                    echo '<small>' . esc_html($entry['description']) . '</small>';
                }
                echo '</li><br>';
            }
            echo '</ul>';
        } else {
            echo '<p>Result not found.</p>';
        }

        echo '<p><a href="' . admin_url('edit.php?post_type=msq_quiz&page=msq-custom-results') . '" class="button">Back</a></p></div>';
        return;
    }

    // ✅ List with pagination
    $paged     = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page  = 10;
    $offset    = ($paged - 1) * $per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset),
        ARRAY_A
    );

    echo '<div class="wrap"><h1>Custom Quiz Results</h1>';

    if (empty($results)) {
        echo '<p>No results found.</p></div>';
        return;
    }

    echo '<form method="post">';
    wp_nonce_field('msq_bulk_delete_action');

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>
        <th><input type="checkbox" id="msq-select-all" /></th>
        <th>ID</th>
        <th>User</th>
        <th>Quiz ID</th>
        <th>Score</th>
        <th>Date</th>
        <th>IP</th>
        <th>Actions</th>
    </tr></thead><tbody>';

    foreach ($results as $row) {
        $view_url = add_query_arg([
            'page' => 'msq-custom-results',
            'action' => 'view',
            'id' => $row['id']
        ], admin_url('edit.php?post_type=msq_quiz'));

        $delete_url = wp_nonce_url(
            add_query_arg([
                'page' => 'msq-custom-results',
                'action' => 'delete',
                'id' => $row['id']
            ], admin_url('edit.php?post_type=msq_quiz')),
            'msq_delete_result'
        );

        echo '<tr>';
        echo '<td><input type="checkbox" name="result_ids[]" value="' . esc_attr($row['id']) . '"></td>';
        echo '<td>' . esc_html($row['id']) . '</td>';
        echo '<td>' . esc_html($row['user_label']) . '</td>';
        echo '<td>' . esc_html($row['quiz_id']) . '</td>';
        echo '<td>' . esc_html($row['score'] . '/' . $row['total']) . '</td>';
        echo '<td>' . esc_html($row['submitted_at']) . '</td>';
        echo '<td>' . esc_html($row['user_ip']) . '</td>';
        echo '<td>
            <a class="button" href="' . esc_url($view_url) . '">Review</a>
            <a class="button button-link-delete" href="' . esc_url($delete_url) . '">Delete</a>
        </td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<p><input type="submit" name="bulk_delete" class="button button-primary" value="Delete Selected" onclick="return confirm(\'Are you sure you want to delete selected results?\')"></p>';
    echo '</form>';

    // ✅ Pagination
    $total_pages = ceil($total_items / $per_page);
    $base_url = remove_query_arg(['action', 'id', '_wpnonce', 'paged'], admin_url('edit.php?post_type=msq_quiz&page=msq-custom-results'));

    if ($total_pages > 1) {
        echo '<div class="tablenav bottom"><div class="tablenav-pages"><span class="pagination-links">';

        // Previous
        if ($paged > 1) {
            echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $paged - 1, $base_url)) . '">&laquo;</a> ';
        }

        // Page Numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $paged) {
                echo '<span class="current-page button" style="background:#007cba;color:white;">' . $i . '</span> ';
            } else {
                echo '<a class="page-number button" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . $i . '</a> ';
            }
        }

        // Next
        if ($paged < $total_pages) {
            echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $paged + 1, $base_url)) . '">&raquo;</a>';
        }

        echo '</span></div></div>';
    }

    echo '</div>';

    // ✅ Select all checkbox JS
    echo '<script>
        document.getElementById("msq-select-all").addEventListener("click", function() {
            let checkboxes = document.querySelectorAll("input[name=\'result_ids[]\']");
            for (let cb of checkboxes) cb.checked = this.checked;
        });
    </script>';
}
