<?php
/**
 * Create test data for Parental Control plugin on ayimi sandbox
 * Run: wp eval-file create-parental-control-test-data.php
 * Or access directly: http://ayimi.test/create-parental-control-test-data.php
 */

// Load WordPress
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    wp_die('You must be logged in as an administrator to run this script.');
}

echo "<h2>Creating Test Posts for Parental Control Plugin</h2>";

// Define test posts
$test_posts = [
    [
        'title'       => 'Learning ABC - For Toddlers',
        'content'     => '<p>A simple educational content for toddlers learning the alphabet. This is perfect for 3+ year olds.</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        'age_groups'  => ['3+'],
        'genres'      => ['Educational'],
        'warnings'    => [],
    ],
    [
        'title'       => 'Space Adventure - For Kids',
        'content'     => '<p>An exciting science fiction story about exploring the moon. Perfect for ages 5 and up.</p><p>Join our space explorers as they discover new worlds and make amazing discoveries!</p>',
        'age_groups'  => ['5+'],
        'genres'      => ['Science Fiction', 'Adventure'],
        'warnings'    => [],
    ],
    [
        'title'       => 'Mystery in the Dark - For Teens',
        'content'     => '<p>A thrilling mystery story with some mildly scary moments. Recommended for teens 13 and older.</p><p>What secrets lurk in the abandoned mansion? Follow our detectives as they uncover the truth...</p>',
        'age_groups'  => ['13+'],
        'genres'      => ['Drama', 'Adventure'],
        'warnings'    => ['Scary/Intense'],
    ],
    [
        'title'       => 'Horror Movie Review - Mature Content',
        'content'     => '<p>A detailed review of classic horror films. Contains discussions of violence and scary content.</p><p>WARNING: This review discusses graphic violence and intense scenes from horror movies.</p>',
        'age_groups'  => ['18+'],
        'genres'      => ['Horror', 'Drama'],
        'warnings'    => ['Violence', 'Scary/Intense', 'Language'],
    ],
    [
        'title'       => 'Action Packed Documentary',
        'content'     => '<p>A documentary about action sports. Some scenes contain intense moments but no warnings.</p><p>Follow extreme athletes as they push the boundaries of what\'s possible!</p>',
        'age_groups'  => ['13+'],
        'genres'      => ['Documentary'],
        'warnings'    => [],
    ],
    [
        'title'       => 'Comedy Show Transcript',
        'content'     => '<p>Transcript from a comedy show. Contains mature language and adult humor.</p><p>WARNING: This content includes adult language and mature themes.</p>',
        'age_groups'  => ['18+'],
        'genres'      => ['Comedy', 'Drama'],
        'warnings'    => ['Language'],
    ],
    [
        'title'       => 'Animated Film Review',
        'content'     => '<p>A review of popular animated films suitable for all ages. From Disney classics to modern masterpieces.</p><p>These films are universally loved by audiences of all ages.</p>',
        'age_groups'  => ['3+', '5+', '13+', '18+'],
        'genres'      => ['Animation', 'Adventure'],
        'warnings'    => [],
    ],
];

// Create posts
$created_count = 0;
echo "<ul>";

foreach ($test_posts as $post_data) {
    // Create post
    $post_id = wp_insert_post([
        'post_title'   => $post_data['title'],
        'post_content' => $post_data['content'],
        'post_type'    => 'post',
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
    ]);

    if (is_wp_error($post_id)) {
        echo "<li style='color: red;'>❌ Error creating post: " . esc_html($post_data['title']) . "</li>";
        continue;
    }

    // Assign age groups
    if (!empty($post_data['age_groups'])) {
        $age_terms = [];
        foreach ($post_data['age_groups'] as $age_name) {
            $term = get_term_by('name', $age_name, 'pcpc_age_group');
            if ($term) {
                $age_terms[] = $term->term_id;
            }
        }
        if (!empty($age_terms)) {
            wp_set_post_terms($post_id, $age_terms, 'pcpc_age_group');
        }
    }

    // Assign genres
    if (!empty($post_data['genres'])) {
        $genre_terms = [];
        foreach ($post_data['genres'] as $genre_name) {
            $term = get_term_by('name', $genre_name, 'pcpc_genre');
            if ($term) {
                $genre_terms[] = $term->term_id;
            }
        }
        if (!empty($genre_terms)) {
            wp_set_post_terms($post_id, $genre_terms, 'pcpc_genre');
        }
    }

    // Assign warnings
    if (!empty($post_data['warnings'])) {
        $warning_terms = [];
        foreach ($post_data['warnings'] as $warning_name) {
            $term = get_term_by('name', $warning_name, 'pcpc_content_warning');
            if ($term) {
                $warning_terms[] = $term->term_id;
            }
        }
        if (!empty($warning_terms)) {
            wp_set_post_terms($post_id, $warning_terms, 'pcpc_content_warning');
        }
    }

    echo "<li style='color: green;'>✓ Created: <strong>" . esc_html($post_data['title']) . "</strong> (ID: {$post_id})</li>";
    $created_count++;
}

echo "</ul>";
echo "<p><strong>" . $created_count . " test posts created successfully!</strong></p>";
echo "<p><a href='/'>← Back to Home</a></p>";
?>
