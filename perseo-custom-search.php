<?php
/**
 * Plugin Name: Perseo Custom Search
 * Plugin URI: https://github.com/giovannimanetti11
 * Description: Custom search form for WordPress.
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
        <form id="custom-search-form" action="#" method="post" data-action-url="https://dev2.perseodesign.com/wp-admin/admin-ajax.php">
            <div id="category-checkboxes" style="display: flex; flex-wrap: wrap; flex-direction: column;">
                <label><input type="checkbox" name="category[]" value="3"> Senza glutine</label>
                <label><input type="checkbox" name="category[]" value="4"> Vegan</label>
                <label><input type="checkbox" name="category[]" value="5"> Pesce</label>
            </div>
            <input type="text" id="keyword1" name="keyword1" placeholder="Ingredient 3, e.g., chili pepper">
            <input type="text" id="keyword2" name="keyword2" placeholder="Ingredient 2, e.g., black pepper">
            <input type="text" id="keyword3" name="keyword3" placeholder="Ingredient 1, e.g., parsley">
            <input type="hidden" name="search_type" value="custom">
            <button type="submit">Search</button>
            <div id="alert-container" class="alert">
                <span class="closebtn" onclick="this.parentElement.style.display='none';">&times;</span> 
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
    $lang = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : $wpml->get_current_language();
    do_action('wpml_switch_language', $lang);
    error_log('Lingua ricevuta: ' . $lang);

    $user = wp_get_current_user();
    $permitted = is_user_logged_in() && !in_array('subscriber', (array) $user->roles);
    $posts_per_page = $permitted ? -1 : 2;

    $keyword1 = isset($_POST['keyword1']) ? sanitize_text_field($_POST['keyword1']) : '';
    $keyword2 = isset($_POST['keyword2']) ? sanitize_text_field($_POST['keyword2']) : '';
    $keyword3 = isset($_POST['keyword3']) ? sanitize_text_field($_POST['keyword3']) : '';

    $translated_category_30_id = apply_filters('wpml_object_id', 30, 'category', true);
    $category_30_query_args = [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [
            [
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms' => $translated_category_30_id,
                'include_children' => false,
            ],
        ],
    ];
    $category_30_query = new WP_Query($category_30_query_args);
    $category_30_post_ids = $category_30_query->posts;

    $selected_categories = isset($_POST['category']) ? array_map('absint', $_POST['category']) : [];
    error_log('Categorie ricevute: ' . implode(', ', $selected_categories));
    $translated_categories = [];
    foreach ($selected_categories as $category_id) {
        $translated_id = apply_filters('wpml_object_id', $category_id, 'category', true);
        if(!is_null($translated_id)) {
            $translated_categories[] = $translated_id;
        } else {
            error_log("Translation missing for category ID: {$category_id}");
        }
    }
    error_log('Categorie tradotte: ' . implode(', ', $translated_categories));

    $selected_categories = array_diff($translated_categories, [$translated_category_30_id]);
    $filtered_post_ids = [];
    foreach ($category_30_post_ids as $post_id) {
        foreach ($selected_categories as $category_id) {
            if (has_term($category_id, 'category', $post_id)) {
                $filtered_post_ids[] = $post_id;
                break;
            }
        }
    }

    $keywords = array_filter([$keyword1, $keyword2, $keyword3]);
    $final_post_ids = [];
    foreach ($filtered_post_ids as $post_id) {
        $content = get_post_field('post_content', $post_id);
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        $ingredients_section = $doc->getElementById('ingredienti');
        if ($ingredients_section) {
            $section_html = $doc->saveHTML($ingredients_section);
            $all_keywords_found = true;
            foreach ($keywords as $keyword) {
                if (stripos($section_html, trim($keyword)) === false) {
                    $all_keywords_found = false;
                    break;
                }
            }
            if ($all_keywords_found) {
                $final_post_ids[] = $post_id;
            }
        }
    }

    if (!$permitted && count($final_post_ids) > $posts_per_page) {
        $final_post_ids = array_slice($final_post_ids, 0, $posts_per_page);
    }

    $results_html = '';
    foreach ($final_post_ids as $post_id) {
        $post = get_post($post_id);
        setup_postdata($post);
        $post_thumbnail = get_the_post_thumbnail($post->ID, 'full');
        $post_title = get_the_title($post->ID);
        $post_link = get_permalink($post->ID);
        $results_html .= '<a href="' . esc_url($post_link) . '" class="search-result-item">';
        $results_html .= '<div>';
        $results_html .= $post_thumbnail;
        $results_html .= '<p>' . esc_html($post_title) . '</p>';
        $results_html .= '</div>';
        $results_html .= '</a>';
        wp_reset_postdata();
    }

    if (empty($results_html)) {
        wp_send_json_success(['message' => 'There are no recipes that match your search.', 'pagination' => '']);
    } else {
        wp_send_json_success(['html' => $results_html]);
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
    wp_send_json_success($translations);
}
add_action('wp_ajax_nopriv_get_translations', 'get_translations');
add_action('wp_ajax_get_translations', 'get_translations');

