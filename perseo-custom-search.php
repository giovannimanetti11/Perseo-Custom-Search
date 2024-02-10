<?php
/**
 * Plugin Name: Perseo Custom Search
 * Plugin URI: https://github.com/giovannimanetti11
 * Description: Use the shortcode [perseo_custom_search] in the pages or posts where you want the Perseo Custom Search to appear.
 * Version: 1.0
 * Author: Giovanni Manetti
 * Author URI: https://github.com/giovannimanetti11
 */

function perseo_custom_search_scripts() {
    wp_enqueue_script('perseo-custom-search-js', plugin_dir_url(__FILE__) . 'functions.js', true);
    wp_enqueue_style('perseo-custom-search-css', plugin_dir_url(__FILE__) . 'style.css');
}
add_action('wp_enqueue_scripts', 'perseo_custom_search_scripts');

function perseo_custom_search_shortcode() {
    ob_start();
    ?>
    <div id="custom-search">
        <form id="custom-search-form" action="#" method="post" data-action-url="<?php echo admin_url('admin-ajax.php'); ?>">
            <div id="category-checkboxes" style="display: flex; flex-wrap: wrap; flex-direction: column;">
                <label><input type="checkbox" name="category[]" value="3"> Senza glutine</label>
                <label><input type="checkbox" name="category[]" value="4"> Vegan</label>
                <label><input type="checkbox" name="category[]" value="5"> Pesce</label>
            </div>
            <div id="tag-select-container"></div>
            <input type="text" id="keyword1" name="keyword1" placeholder="Ingredient 3, e.g., chili pepper">
            <input type="text" id="keyword2" name="keyword2" placeholder="Ingredient 2, e.g., black pepper">
            <input type="text" id="keyword3" name="keyword3" placeholder="Ingredient 1, e.g., parsley">
            <input type="hidden" name="search_type" value="custom">
            <button type="submit">Search</button>
            <div id="alert-container" class="alert">
                <span class="closebtn">&times;</span>
                <p id="alert-msg"></p>
            </div>
        </form>
    </div>
    <div id="search-results"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('perseo_custom_search', 'perseo_custom_search_shortcode');


function custom_ajax_search() {
    global $wpml;
    // Set WPML language if necessary
    $lang = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : (function_exists('wpml_get_current_language') ? wpml_get_current_language() : 'default');
    if(function_exists('wpml_switch_language')) {
        do_action('wpml_switch_language', $lang);
    }

    $user = wp_get_current_user();
    $permitted = is_user_logged_in() && !in_array('subscriber', (array) $user->roles);
    $posts_per_page = $permitted ? -1 : 2;

    $keyword1 = isset($_POST['keyword1']) ? sanitize_text_field($_POST['keyword1']) : '';
    $keyword2 = isset($_POST['keyword2']) ? sanitize_text_field($_POST['keyword2']) : '';
    $keyword3 = isset($_POST['keyword3']) ? sanitize_text_field($_POST['keyword3']) : '';

    $selected_categories = isset($_POST['category']) ? array_map('absint', $_POST['category']) : [];
    $selected_tags = isset($_POST['tags']) ? array_map('absint', $_POST['tags']) : [];

    // Translate category IDs to the current language
    $translated_categories = array_map(function($category_id) use ($lang) {
        return apply_filters('wpml_object_id', $category_id, 'category', true, $lang);
    }, $selected_categories);

    // Translate tag IDs to the current language
    $translated_tags = array_map(function($tag_id) use ($lang) {
        return apply_filters('wpml_object_id', $tag_id, 'post_tag', true, $lang);
    }, $selected_tags);


    // Prepare tax_query with an 'AND' relation
    $tax_query = ['relation' => 'AND'];

    // Always filter by the category with ID 30, translated
    $translated_category_30_id = apply_filters('wpml_object_id', 30, 'category', true, $lang);
    $tax_query[] = [
        'taxonomy' => 'category',
        'field' => 'term_id',
        'terms' => $translated_category_30_id,
        'include_children' => false,
    ];

    // Add selected categories to the tax_query
    if (!empty($translated_categories)) {
        $tax_query[] = [
            'taxonomy' => 'category',
            'field' => 'term_id',
            'terms' => $translated_categories,
            'include_children' => false,
        ];
    }

    // Add selected tags to the tax_query
    if (!empty($translated_tags)) {
        $tax_query[] = [
            'taxonomy' => 'post_tag',
            'field' => 'term_id',
            'terms' => $translated_tags,
            'include_children' => false,
        ];
    }

    // Configure the query parameters
    $args = [
        'post_type' => 'post',
        'posts_per_page' => $posts_per_page,
        'fields' => 'ids',
        'tax_query' => $tax_query,
    ];

    // Add keyword filter if present
    if ($keyword1 || $keyword2 || $keyword3) {
        $args['s'] = implode(' ', array_filter([$keyword1, $keyword2, $keyword3]));
    }

    // Execute the query
    $query = new WP_Query($args);

    // Generate the results HTML
    $results_html = '';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $post_thumbnail = get_the_post_thumbnail($post_id, 'full');
            $post_title = get_the_title($post_id);
            $post_link = get_permalink($post_id);
            $results_html .= '<a href="' . esc_url($post_link) . '" class="search-result-item">';
            $results_html .= '<div>';
            $results_html .= $post_thumbnail;
            $results_html .= '<p>' . esc_html($post_title) . '</p>';
            $results_html .= '</div>';
            $results_html .= '</a>';
        }
        wp_reset_postdata();
    }

    // Send the JSON response
    if (empty($results_html)) {
        wp_send_json_success(['message' => 'There are no recipes that match your search.', 'pagination' => '']);
    } else {
        wp_send_json_success(['html' => $results_html, 'pagination' => '']);
    }

    wp_die(); 
}

add_action('wp_ajax_custom_search', 'custom_ajax_search');
add_action('wp_ajax_nopriv_custom_search', 'custom_ajax_search');




function get_translations() {
    $translations = [
        'noResultsMsg' => __('There are no recipes that match your search.', 'custom-search'),
        'selectCategoryMsg' => __('Please select at least one category', 'custom-search'),
        'insertKeywordMsg' => __('Please enter at least one keyword', 'custom-search'),
        'searchErrorMsg' => __('Search error. Please try again.', 'custom-search'),
        'genericErrorMsg' => __('An error occurred during the search.', 'custom-search'),
        'placeholderKeyword1' => __('Ingredient 1, e.g., parsley', 'custom-search'),
        'placeholderKeyword2' => __('Ingredient 2, e.g., black pepper', 'custom-search'),
        'placeholderKeyword3' => __('Ingredient 3, e.g., chili pepper', 'custom-search'),
        'searchButtonText' => __('Search', 'custom-search'),
    ];
    $category_ids = [3, 4, 5];
    $translated_categories = [];
    foreach ($category_ids as $id) {
        $translated_id = apply_filters('wpml_object_id', $id, 'category', true);
        $category = get_category($translated_id);
        $translated_categories[$id] = $category->name;
    }
    $translations['categories'] = $translated_categories;
    $tags = get_tags(['hide_empty' => false]); 
    $tags_data = array_map(function($tag) {
        return [
            'id' => $tag->term_id,
            'name' => $tag->name
        ];
    }, $tags);
    $translations['tags'] = $tags_data;

    wp_send_json_success($translations);
}
add_action('wp_ajax_nopriv_get_translations', 'get_translations');
add_action('wp_ajax_get_translations', 'get_translations');

